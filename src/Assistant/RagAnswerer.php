<?php

declare(strict_types=1);

// this_file: paragra-php/src/Assistant/RagAnswerer.php

namespace ParaGra\Assistant;

use ParaGra\Llm\AskYodaClient;
use ParaGra\Llm\ChatRequestOptions;
use ParaGra\Llm\ChatResponse;
use ParaGra\Llm\ChatUsage;
use ParaGra\Llm\OpenAiChatClient;
use ParaGra\Llm\OpenAiChatConfig;
use ParaGra\Llm\PromptBuilder;
use ParaGra\Moderation\ModerationException;
use ParaGra\Moderation\ModerationResult;
use ParaGra\Moderation\ModeratorInterface;
use ParaGra\Moderation\OpenAiModerator;
use ParaGra\Util\ConfigValidator;
use Ragie\Api\ApiException;
use Ragie\Client as RagieClient;
use Ragie\Logging\StructuredLogger;
use Ragie\Metrics\CostTracker;
use Ragie\Metrics\MetricsCollector;
use Ragie\RetrievalOptions;
use Ragie\RetrievalResult;
use Ragie\Validation\InputSanitizer;

final class RagAnswerer
{
    private PromptBuilder $promptBuilder;

    public function __construct(
        private RagieClient $ragieClient,
        private OpenAiChatClient $chatClient,
        ?PromptBuilder $promptBuilder = null,
        private ?RetrievalOptions $defaultRetrievalOptions = null,
        private ?ChatRequestOptions $defaultChatOptions = null,
        private ?AskYodaClient $askYodaClient = null,
        private ?ModeratorInterface $moderator = null,
        private ?MetricsCollector $metricsCollector = null,
        private ?CostTracker $costTracker = null,
        private ?StructuredLogger $structuredLogger = null,
        private ?AskYodaHostedAdapter $askYodaAdapter = null,
    ) {
        $this->promptBuilder = $promptBuilder ?? new PromptBuilder();
        $this->metricsCollector = $metricsCollector ?? $ragieClient->getMetricsCollector();
        $this->costTracker = $costTracker ?? $ragieClient->getCostTracker();
        $this->structuredLogger = $structuredLogger ?? $ragieClient->getStructuredLogger();
        $this->askYodaClient = $askYodaClient;
        $this->askYodaAdapter = $askYodaAdapter ?? ($askYodaClient !== null ? new AskYodaHostedAdapter($askYodaClient) : null);

        if ($this->structuredLogger !== null) {
            $this->ragieClient->withStructuredLogger($this->structuredLogger);
        }
    }

    /**
     * Create RagAnswerer from environment variables
     *
     * Required environment variables:
     * - RAGIE_API_KEY
     * - OPENAI_API_KEY
     *
     * Optional environment variables:
     * - OPENAI_BASE_URL, OPENAI_API_MODEL, OPENAI_API_TEMPERATURE, etc.
     * - EDENAI_API_KEY, EDENAI_ASKYODA_PROJECT (for AskYoda fallback)
     * - OPENAI_MODERATION_ENABLED=1 (to enable content moderation)
     *
     * @throws \ParaGra\Exception\ConfigurationException if required env vars missing
     */
    public static function fromEnv(): self
    {
        $ragieApiKey = ConfigValidator::requireEnv('RAGIE_API_KEY');
        $ragieClient = new RagieClient($ragieApiKey);

        $chatConfig = OpenAiChatConfig::fromEnv();
        $chatClient = new OpenAiChatClient($chatConfig);

        $promptBuilder = new PromptBuilder();

        // Optional AskYoda client for fallback
        $askYodaClient = null;
        $askYodaAdapter = null;
        try {
            $askYodaClient = AskYodaClient::fromEnv();
            $askYodaAdapter = new AskYodaHostedAdapter($askYodaClient);
        } catch (\RuntimeException $e) {
            // AskYoda not configured, that's ok - fallback won't be available
        }

        // Optional moderation (requires OPENAI_API_KEY and OPENAI_MODERATION_ENABLED=1)
        $moderator = null;
        $moderationEnabled = ConfigValidator::getEnv('OPENAI_MODERATION_ENABLED', '0');
        if ($moderationEnabled === '1' || $moderationEnabled === 'true') {
            try {
                $moderator = OpenAiModerator::fromEnv();
            } catch (\RuntimeException $e) {
                // Moderation not configured, that's ok
            }
        }

        $metricsCollector = new MetricsCollector();
        $costTracker = new CostTracker();
        $ragieClient
            ->withMetricsCollector($metricsCollector)
            ->withCostTracker($costTracker);

        return new self(
            $ragieClient,
            $chatClient,
            $promptBuilder,
            null,
            null,
            $askYodaClient,
            $moderator,
            $metricsCollector,
            $costTracker,
            null,
            $askYodaAdapter
        );
    }

    public function withStructuredLogger(StructuredLogger $logger): self
    {
        $this->structuredLogger = $logger;
        $this->ragieClient->withStructuredLogger($logger);

        return $this;
    }

    public function answer(
        string $question,
        ?RetrievalOptions $retrievalOptions = null,
        ?ChatRequestOptions $chatOptions = null
    ): RagAnswer {
        $cleanQuestion = InputSanitizer::sanitizeAndValidate($question);

        // Optional: Check content moderation before processing
        if ($this->moderator !== null) {
            try {
                $moderationResult = $this->moderator->moderate($cleanQuestion);
                $this->structuredLogger?->logModerationDecision($moderationResult->isFlagged(), $moderationResult->getCategories());
                $this->recordModerationMetrics($moderationResult);
            } catch (ModerationException $e) {
                $this->structuredLogger?->logModerationDecision(true, $e->getFlaggedCategories());
                $this->metricsCollector?->recordModeration(true, $e->getFlaggedCategories());
                throw $e;
            }
        }

        $retrievalOptions = $retrievalOptions ?? $this->defaultRetrievalOptions ?? RetrievalOptions::create();
        $chatOptions = $chatOptions ?? $this->defaultChatOptions ?? new ChatRequestOptions();

        try {
            // Primary path: Ragie + OpenAI/Cerebras
            $start = microtime(true);
            $retrievalResult = $this->ragieClient->retrieve($cleanQuestion, $retrievalOptions);
            $context = $retrievalResult->getChunkTexts();
            $prompt = $this->promptBuilder->build($cleanQuestion, $context);
            $chatStart = microtime(true);
            $chatResponse = $this->chatClient->generateText($prompt, $chatOptions);
            $chatDuration = $this->elapsedMs($chatStart);
            $usage = $chatResponse->getUsage();
            $this->costTracker?->recordOpenAiUsage($usage->promptTokens, $usage->completionTokens);
            $this->structuredLogger?->logChatCompletion(
                $cleanQuestion,
                'openai',
                $chatDuration,
                $usage->promptTokens,
                $usage->completionTokens
            );
            $elapsed = (int) ((microtime(true) - $start) * 1000.0);

            return new RagAnswer(
                question: $cleanQuestion,
                answer: $chatResponse->getText(),
                retrievalResult: $retrievalResult,
                chatResponse: $chatResponse,
                prompt: $prompt,
                executionTimeMs: $elapsed
            );
        } catch (ApiException $e) {
            // Check if this is a rate limit error (429) and fallback is available
            if ($e->getCode() === 429 && $this->askYodaAdapter !== null) {
                return $this->answerWithAskYoda($cleanQuestion);
            }

            $this->structuredLogger?->logError('ragie.answer.api_exception', $e, [
                'question_preview' => $this->preview($cleanQuestion),
            ]);

            throw $e;
        } catch (\Throwable $e) {
            $this->structuredLogger?->logError('ragie.answer.unexpected', $e, [
                'question_preview' => $this->preview($cleanQuestion),
            ]);
            throw $e;
        }
    }

    /**
     * Answer using AskYoda fallback (EdenAI)
     *
     * This is used when Ragie hits rate limits (429 errors).
     * AskYoda is a complete RAG+LLM solution.
     *
     * @param string $question The question to answer
     * @return RagAnswer
     */
    private function answerWithAskYoda(string $question): RagAnswer
    {
        if ($this->askYodaAdapter === null) {
            throw new \RuntimeException('AskYoda fallback is not configured.');
        }

        $telemetry = function (string $event, array $context) use ($question): void {
            $duration = (int) ($context['duration_ms'] ?? 0);
            $chunkCount = (int) ($context['chunk_count'] ?? 0);
            $success = $event === 'askyoda.success';

            $this->metricsCollector?->recordRetrieval('askyoda', $duration, $chunkCount, $success);

            if (!$success && $this->structuredLogger !== null) {
                $message = $context['error'] ?? 'AskYoda fallback failed.';
                $this->structuredLogger->logError('ragie.fallback.askyoda_failed', new \RuntimeException($message), [
                    'question_preview' => $this->preview($question),
                ]);
            }
        };

        $result = $this->askYodaAdapter->ask($question, telemetry: $telemetry);
        $askYodaResponse = $result->getResponse();
        $elapsed = $result->getDurationMs();

        // Create a ChatResponse-compatible object from AskYoda response
        $chatResponse = new ChatResponse(
            text: $askYodaResponse->getResult(),
            usage: new ChatUsage(
                promptTokens: $askYodaResponse->getInputTokens(),
                completionTokens: $askYodaResponse->getOutputTokens(),
                totalTokens: $askYodaResponse->getTotalTokens()
            ),
            rawResponse: $askYodaResponse->toArray()
        );

        // Create a minimal RetrievalResult since AskYoda doesn't provide chunk details
        // We create an empty result with metadata about the fallback
        $retrievalResult = new RetrievalResult(
            new \Ragie\Api\Model\Retrieval([
                'scored_chunks' => [],
            ])
        );

        // Build a pseudo-prompt to maintain consistency
        $prompt = "Fallback to AskYoda: " . $question;

        $answer = new RagAnswer(
            question: $question,
            answer: $askYodaResponse->getResult(),
            retrievalResult: $retrievalResult,
            chatResponse: $chatResponse,
            prompt: $prompt,
            executionTimeMs: $elapsed,
            metadata: [
                'fallback_used' => true,
                'fallback_provider' => 'askyoda',
                'llm_provider' => $askYodaResponse->getLlmProvider(),
                'llm_model' => $askYodaResponse->getLlmModel(),
                'cost' => $askYodaResponse->getCost(),
                'usage' => $askYodaResponse->getUsage(),
                'chunk_count' => $askYodaResponse->getChunkCount(),
                'chunk_ids' => $askYodaResponse->getChunkIds(),
            ]
        );

        $this->structuredLogger?->logFallback('askyoda', $question, [
            'duration_ms' => $elapsed,
            'chunk_count' => $askYodaResponse->getChunkCount(),
            'llm_provider' => $askYodaResponse->getLlmProvider(),
            'llm_model' => $askYodaResponse->getLlmModel(),
        ]);

        $this->structuredLogger?->logChatCompletion(
            $question,
            'askyoda',
            $elapsed,
            $askYodaResponse->getInputTokens(),
            $askYodaResponse->getOutputTokens()
        );

        return $answer;
    }

    private function recordModerationMetrics(ModerationResult $result): void
    {
        $this->metricsCollector?->recordModeration($result->isFlagged(), $result->getCategories());
    }

    private function elapsedMs(float $startTime): int
    {
        return (int) max(0, round((microtime(true) - $startTime) * 1000.0));
    }

    private function preview(string $value): string
    {
        $flattened = trim((string) preg_replace('/\s+/u', ' ', $value));
        return mb_substr($flattened, 0, 120) ?: '';
    }
}
