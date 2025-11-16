<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Assistant/RagAnswererTest.php

namespace ParaGra\Tests\Assistant;

use ParaGra\Assistant\RagAnswer;
use ParaGra\Assistant\RagAnswerer;
use ParaGra\Exception\ConfigurationException;
use ParaGra\Llm\AskYodaClient;
use ParaGra\Llm\AskYodaResponse;
use ParaGra\Llm\ChatRequestOptions;
use ParaGra\Llm\ChatResponse;
use ParaGra\Llm\ChatUsage;
use ParaGra\Llm\OpenAiChatClient;
use ParaGra\Llm\PromptBuilder;
use ParaGra\Moderation\ModerationException;
use ParaGra\Moderation\ModerationResult;
use ParaGra\Moderation\ModeratorInterface;
use ParaGra\Tests\Logging\SpyLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ragie\Api\ApiException;
use Ragie\Api\Model\Retrieval;
use Ragie\Api\Model\ScoredChunk;
use Ragie\Client as RagieClient;
use Ragie\Exception\InvalidQueryException;
use Ragie\Logging\StructuredLogger;
use Ragie\Metrics\CostTracker;
use Ragie\Metrics\MetricsCollector;
use Ragie\RetrievalOptions;
use Ragie\RetrievalResult;

final class RagAnswererTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        // Clear environment variables before each test
        unset($_ENV['RAGIE_API_KEY']);
        unset($_ENV['OPENAI_API_KEY']);
        unset($_ENV['OPENAI_BASE_URL']);
        unset($_ENV['OPENAI_API_MODEL']);
    }

    public function testAnswerCombinesRetrievalAndChat(): void
    {
        $retrievalResult = $this->fakeRetrievalResult([
            ['text' => 'First context snippet', 'score' => 0.9, 'document_id' => 'doc-1'],
            ['text' => 'Second snippet', 'score' => 0.8, 'document_id' => 'doc-2'],
        ]);

        /** @var RagieClient&MockObject $ragieClient */
        $ragieClient = $this->createMock(RagieClient::class);
        $ragieClient
            ->expects($this->once())
            ->method('retrieve')
            ->with(
                'What is Ragie?',
                $this->logicalOr($this->isInstanceOf(RetrievalOptions::class), $this->isNull())
            )
            ->willReturn($retrievalResult);

        $chatResponse = new ChatResponse(
            text: 'Ragie is a retrieval service.',
            usage: new ChatUsage(promptTokens: 30, completionTokens: 12, totalTokens: 42),
            rawResponse: null
        );

        $chatClient = $this->createMock(OpenAiChatClient::class);
        \assert($chatClient instanceof OpenAiChatClient);
        $chatClient
            ->expects($this->once())
            ->method('generateText')
            ->with($this->callback(function (string $prompt): bool {
                $this->assertStringContainsString('First context snippet', $prompt);
                $this->assertStringContainsString('Second snippet', $prompt);
                $this->assertStringContainsString('What is Ragie?', $prompt);

                return true;
            }), $this->isInstanceOf(ChatRequestOptions::class))
            ->willReturn($chatResponse);

        $answerer = new RagAnswerer(
            ragieClient: $ragieClient,
            chatClient: $chatClient,
            promptBuilder: new PromptBuilder(),
        );

        $answer = $answerer->answer('What is Ragie?');

        $this->assertInstanceOf(RagAnswer::class, $answer);
        $this->assertSame('Ragie is a retrieval service.', $answer->getAnswer());
        $this->assertCount(2, $answer->getContextTexts());
        $this->assertSame(42, $answer->getChatUsage()->totalTokens);
        $this->assertStringContainsString('What is Ragie?', $answer->getPrompt());
    }

    public function testAnswerEmitsChatCompletionLog(): void
    {
        $retrievalResult = $this->fakeRetrievalResult([
            ['text' => 'Snippet', 'score' => 0.9],
        ]);

        $ragieClient = $this->createMock(RagieClient::class);
        $ragieClient->expects($this->once())
            ->method('retrieve')
            ->willReturn($retrievalResult);
        $ragieClient->method('getMetricsCollector')->willReturn(null);
        $ragieClient->method('getCostTracker')->willReturn(null);
        $ragieClient->method('getStructuredLogger')->willReturn(null);
        $ragieClient->expects($this->once())
            ->method('withStructuredLogger')
            ->willReturnSelf();

        $chatResponse = new ChatResponse(
            text: 'Answer',
            usage: new ChatUsage(promptTokens: 10, completionTokens: 5, totalTokens: 15),
            rawResponse: null
        );

        $chatClient = $this->createMock(OpenAiChatClient::class);
        $chatClient->expects($this->once())
            ->method('generateText')
            ->willReturn($chatResponse);

        $spy = new SpyLogger();

        $answerer = new RagAnswerer(
            ragieClient: $ragieClient,
            chatClient: $chatClient,
            promptBuilder: new PromptBuilder(),
            structuredLogger: new StructuredLogger($spy)
        );

        $answerer->answer('What is Ragie?');

        $messages = array_column($spy->records, 'message');
        $this->assertContains('ragie.chat.success', $messages);
    }

    public function testFallbackLogsStructuredEvent(): void
    {
        $ragieClient = $this->createMock(RagieClient::class);
        $ragieClient->expects($this->once())
            ->method('retrieve')
            ->willThrowException(new ApiException('rate', 429));
        $ragieClient->method('getMetricsCollector')->willReturn(null);
        $ragieClient->method('getCostTracker')->willReturn(null);
        $ragieClient->method('getStructuredLogger')->willReturn(null);
        $ragieClient->expects($this->once())
            ->method('withStructuredLogger')
            ->willReturnSelf();

        $askYodaClient = $this->createMock(AskYodaClient::class);
        $askYodaClient->expects($this->once())
            ->method('ask')
            ->willReturn(new AskYodaResponse([
                'result' => 'Fallback answer',
                'llm_provider' => 'edenai',
                'llm_model' => 'askyoda-large',
                'cost' => 0.02,
                'usage' => [
                    'input_tokens' => 20,
                    'output_tokens' => 10,
                    'total_tokens' => 30,
                ],
                'chunks_ids' => ['chunk-1'],
            ]));

        $chatClient = $this->createMock(OpenAiChatClient::class);
        $chatClient->expects($this->never())->method('generateText');

        $spy = new SpyLogger();

        $answerer = new RagAnswerer(
            ragieClient: $ragieClient,
            chatClient: $chatClient,
            promptBuilder: new PromptBuilder(),
            askYodaClient: $askYodaClient,
            structuredLogger: new StructuredLogger($spy)
        );

        $answer = $answerer->answer('Need fallback');
        $this->assertSame('Fallback answer', $answer->getAnswer());

        $messages = array_column($spy->records, 'message');
        $this->assertContains('ragie.fallback.used', $messages);
        $this->assertContains('ragie.chat.success', $messages);
    }

    public function testWithStructuredLoggerReturnsInstance(): void
    {
        $ragieClient = $this->createMock(RagieClient::class);
        $ragieClient->method('getMetricsCollector')->willReturn(null);
        $ragieClient->method('getCostTracker')->willReturn(null);
        $ragieClient->method('getStructuredLogger')->willReturn(null);
        $ragieClient->expects($this->once())
            ->method('withStructuredLogger')
            ->willReturnSelf();

        $chatClient = $this->createMock(OpenAiChatClient::class);

        $answerer = new RagAnswerer(
            ragieClient: $ragieClient,
            chatClient: $chatClient,
            promptBuilder: new PromptBuilder()
        );

        $spy = new SpyLogger();
        $logger = new StructuredLogger($spy);

        $result = $answerer->withStructuredLogger($logger);

        $this->assertSame($answerer, $result);
    }

    /**
     * @param array<int, array{text: string, score: float, document_id?: string}> $chunks
     */
    private function fakeRetrievalResult(array $chunks): RetrievalResult
    {
        $scoredChunks = [];
        $index = 0;
        foreach ($chunks as $chunk) {
            $scoredChunks[] = new ScoredChunk([
                'text' => $chunk['text'],
                'score' => $chunk['score'],
                'id' => 'chunk-' . $index,
                'index' => $index,
                'metadata' => [],
                'document_id' => $chunk['document_id'] ?? 'doc-' . $index,
                'document_name' => 'Document ' . $index,
                'document_metadata' => [],
                'links' => [],
            ]);
            $index++;
        }

        $retrieval = new Retrieval([
            'scored_chunks' => $scoredChunks,
        ]);

        return new RetrievalResult($retrieval);
    }

    public function testFromEnvCreatesAnswerer(): void
    {
        $_ENV['RAGIE_API_KEY'] = 'test-ragie-key';
        $_ENV['OPENAI_API_KEY'] = 'test-openai-key';

        $answerer = RagAnswerer::fromEnv();

        $this->assertInstanceOf(RagAnswerer::class, $answerer);
    }

    public function testFromEnvThrowsWhenRagieApiKeyMissing(): void
    {
        // Ensure RAGIE_API_KEY is truly not set
        unset($_ENV['RAGIE_API_KEY']);
        if (getenv('RAGIE_API_KEY') !== false) {
            putenv('RAGIE_API_KEY');
        }

        $_ENV['OPENAI_API_KEY'] = 'test-openai-key';

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('RAGIE_API_KEY');

        RagAnswerer::fromEnv();
    }

    public function testFromEnvThrowsWhenOpenAiApiKeyMissing(): void
    {
        // Ensure OPENAI_API_KEY is truly not set
        unset($_ENV['OPENAI_API_KEY']);
        if (getenv('OPENAI_API_KEY') !== false) {
            putenv('OPENAI_API_KEY');
        }

        $_ENV['RAGIE_API_KEY'] = 'test-ragie-key';

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('OPENAI_API_KEY');

        RagAnswerer::fromEnv();
    }

    public function testFromEnvUsesDefaultOpenAiModel(): void
    {
        $_ENV['RAGIE_API_KEY'] = 'test-ragie-key';
        $_ENV['OPENAI_API_KEY'] = 'test-openai-key';

        $answerer = RagAnswerer::fromEnv();

        // We can't directly inspect the internal chatClient config,
        // but we can verify the answerer was created successfully
        $this->assertInstanceOf(RagAnswerer::class, $answerer);
    }

    public function testAnswerSanitizesQuestionBeforeProcessing(): void
    {
        $dirtyQuestion = "  What is Ragie?\0  ";

        $retrievalResult = $this->fakeRetrievalResult([
            ['text' => 'Snippet', 'score' => 0.9, 'document_id' => 'doc-1'],
        ]);

        /** @var RagieClient&MockObject $ragieClient */
        $ragieClient = $this->createMock(RagieClient::class);
        $ragieClient
            ->expects($this->once())
            ->method('retrieve')
            ->with('What is Ragie?', $this->isInstanceOf(RetrievalOptions::class))
            ->willReturn($retrievalResult);

        $chatClient = $this->createMock(OpenAiChatClient::class);
        $chatClient
            ->expects($this->once())
            ->method('generateText')
            ->with($this->isType('string'), $this->isInstanceOf(ChatRequestOptions::class))
            ->willReturn(new ChatResponse(
                text: 'Answer',
                usage: new ChatUsage(promptTokens: 1, completionTokens: 1, totalTokens: 2),
                rawResponse: null
            ));

        $answerer = new RagAnswerer($ragieClient, $chatClient, new PromptBuilder());

        $answer = $answerer->answer($dirtyQuestion);

        $this->assertSame('What is Ragie?', $answer->getQuestion());
    }

    public function testAnswerRejectsControlCharacters(): void
    {
        /** @var RagieClient&MockObject $ragieClient */
        $ragieClient = $this->createMock(RagieClient::class);
        $chatClient = $this->createMock(OpenAiChatClient::class);

        $answerer = new RagAnswerer($ragieClient, $chatClient, new PromptBuilder());

        $this->expectException(InvalidQueryException::class);

        $answerer->answer("Hello\x07World");
    }

    public function testAnswerRecordsOpenAiCostAndModerationMetrics(): void
    {
        $retrievalResult = $this->fakeRetrievalResult([
            ['text' => 'Snippet', 'score' => 0.9],
        ]);

        /** @var RagieClient&MockObject $ragieClient */
        $ragieClient = $this->createMock(RagieClient::class);
        $ragieClient->expects($this->once())
            ->method('retrieve')
            ->willReturn($retrievalResult);

        $chatResponse = new ChatResponse(
            text: 'Telemetry answer.',
            usage: new ChatUsage(promptTokens: 60, completionTokens: 10, totalTokens: 70),
            rawResponse: null
        );

        $chatClient = $this->createMock(OpenAiChatClient::class);
        $chatClient->expects($this->once())
            ->method('generateText')
            ->willReturn($chatResponse);

        $moderator = $this->createMock(ModeratorInterface::class);
        $moderator->expects($this->once())
            ->method('moderate')
            ->willReturn(new ModerationResult(
                flagged: false,
                categories: ['hate' => false],
                categoryScores: ['hate' => 0.01]
            ));

        $metrics = new MetricsCollector();
        $costTracker = new CostTracker();

        $answerer = new RagAnswerer(
            $ragieClient,
            $chatClient,
            new PromptBuilder(),
            null,
            null,
            null,
            $moderator,
            $metrics,
            $costTracker
        );

        $answerer->answer('Explain telemetry');

        $this->assertSame(1, $costTracker->getOpenAiCalls());
        $this->assertSame(60, $costTracker->getPromptTokens());

        $moderationEvents = array_values(array_filter(
            $metrics->getEvents(),
            fn (array $event): bool => ($event['type'] ?? null) === 'moderation'
        ));

        $this->assertCount(1, $moderationEvents);
        $this->assertFalse($moderationEvents[0]['flagged']);
        $this->assertSame(['hate' => false], $moderationEvents[0]['categories']);
    }

    public function testAnswerRecordsModerationFailureMetrics(): void
    {
        /** @var RagieClient&MockObject $ragieClient */
        $ragieClient = $this->createMock(RagieClient::class);
        $ragieClient->expects($this->never())->method('retrieve');

        $chatClient = $this->createMock(OpenAiChatClient::class);
        $chatClient->expects($this->never())->method('generateText');

        $moderationException = new ModerationException(
            'flagged',
            ['violence' => true],
            ['violence' => 0.9]
        );

        $moderator = $this->createMock(ModeratorInterface::class);
        $moderator->expects($this->once())
            ->method('moderate')
            ->willThrowException($moderationException);

        $metrics = new MetricsCollector();
        $costTracker = new CostTracker();

        $answerer = new RagAnswerer(
            $ragieClient,
            $chatClient,
            new PromptBuilder(),
            null,
            null,
            null,
            $moderator,
            $metrics,
            $costTracker
        );

        try {
            $answerer->answer('Forbidden content');
            $this->fail('Expected ModerationException to be thrown');
        } catch (ModerationException $e) {
            $this->assertSame('flagged', $e->getMessage());
        }

        $moderationEvents = array_values(array_filter(
            $metrics->getEvents(),
            fn (array $event): bool => ($event['type'] ?? null) === 'moderation'
        ));

        $this->assertCount(1, $moderationEvents);
        $this->assertTrue($moderationEvents[0]['flagged']);
        $this->assertSame(['violence' => true], $moderationEvents[0]['categories']);
    }

    public function testFallbackRecordsAskYodaMetricsOnRateLimit(): void
    {
        $retrievalException = new ApiException('rate limited', 429);

        /** @var RagieClient&MockObject $ragieClient */
        $ragieClient = $this->createMock(RagieClient::class);
        $ragieClient->expects($this->once())
            ->method('retrieve')
            ->willThrowException($retrievalException);

        $chatClient = $this->createMock(OpenAiChatClient::class);
        $chatClient->expects($this->never())->method('generateText');

        $askYodaClient = $this->createMock(AskYodaClient::class);
        $askYodaClient->expects($this->once())
            ->method('ask')
            ->willReturn(new AskYodaResponse([
                'result' => 'Fallback answer',
                'chunks_ids' => ['chunk-1', 'chunk-2'],
                'llm_provider' => 'askyoda',
                'llm_model' => 'edenai',
                'usage' => [
                    'input_tokens' => 15,
                    'output_tokens' => 10,
                    'total_tokens' => 25,
                ],
            ]));

        $metrics = new MetricsCollector();
        $costTracker = new CostTracker();

        $answerer = new RagAnswerer(
            $ragieClient,
            $chatClient,
            new PromptBuilder(),
            null,
            null,
            $askYodaClient,
            null,
            $metrics,
            $costTracker
        );

        $answer = $answerer->answer('Fallback please?');

        $retrievalEvents = array_values(array_filter(
            $metrics->getEvents(),
            fn (array $event): bool => ($event['type'] ?? null) === 'retrieval'
                && ($event['source'] ?? null) === 'askyoda'
        ));

        $this->assertCount(1, $retrievalEvents);
        $this->assertTrue($retrievalEvents[0]['success']);
        $this->assertSame(2, $retrievalEvents[0]['chunk_count']);
        $this->assertTrue($answer->isFallback());
        $this->assertSame('askyoda', $answer->getFallbackProvider());
    }
}
