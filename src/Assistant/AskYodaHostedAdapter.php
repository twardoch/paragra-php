<?php

declare(strict_types=1);

// this_file: paragra-php/src/Assistant/AskYodaHostedAdapter.php

namespace ParaGra\Assistant;

use ParaGra\Llm\AskYodaClient;
use Throwable;

use function array_filter;
use function is_callable;
use function is_numeric;
use function max;
use function microtime;
use function preg_replace;
use function round;
use function substr;
use function trim;

/**
 * Bridges Ragie fallback semantics with the Eden AskYoda hosted workflow.
 * It records timing/counter metadata and optionally emits telemetry events
 * so higher-level orchestrators can log or monitor hosted fallback usage.
 */
final class AskYodaHostedAdapter
{
    /**
     * @param callable(string, array<string, mixed>):void|null $telemetryHook
     */
    public function __construct(
        private readonly AskYodaClient $client,
        private readonly $telemetryHook = null,
    ) {
    }

    /**
     * @param array{
     *     k?: int,
     *     min_score?: float,
     *     temperature?: float,
     *     max_tokens?: int
     * } $options
     * @param callable(string, array<string, mixed>):void|null $telemetry Additional telemetry consumer
     */
    public function ask(
        string $question,
        array $options = [],
        ?callable $telemetry = null
    ): AskYodaHostedResult {
        $start = microtime(true);
        $k = $this->intOption($options, 'k', 10, 1);
        $minScore = $this->floatOption($options, 'min_score', 0.3, 0.0, 1.0);
        $temperature = $this->floatOption($options, 'temperature', 0.99, 0.0, 2.0);
        $maxTokens = $this->intOption($options, 'max_tokens', 8000, 1);

        try {
            $response = $this->client->ask(
                query: $question,
                k: $k,
                minScore: $minScore,
                temperature: $temperature,
                maxTokens: $maxTokens
            );
        } catch (Throwable $exception) {
            $this->emitTelemetry('askyoda.failure', [
                'duration_ms' => $this->elapsed($start),
                'question_preview' => $this->preview($question),
                'error' => $exception->getMessage(),
            ], $telemetry);

            throw $exception;
        }

        $result = new AskYodaHostedResult(
            $response,
            $this->elapsed($start)
        );

        $this->emitTelemetry('askyoda.success', [
            'duration_ms' => $result->getDurationMs(),
            'chunk_count' => $response->getChunkCount(),
            'question_preview' => $this->preview($question),
            'llm_provider' => $response->getLlmProvider(),
            'llm_model' => $response->getLlmModel(),
            'cost' => $response->getCost(),
        ], $telemetry);

        return $result;
    }

    private function emitTelemetry(string $event, array $payload, ?callable $telemetry): void
    {
        $handlers = array_filter([$telemetry, $this->telemetryHook], static fn ($candidate): bool => is_callable($candidate));
        foreach ($handlers as $handler) {
            $handler($event, $payload);
        }
    }

    private function elapsed(float $start): int
    {
        return (int) max(0, round((microtime(true) - $start) * 1000.0));
    }

    private function preview(string $text): string
    {
        $flattened = trim((string) preg_replace('/\s+/u', ' ', $text));
        return substr($flattened, 0, 160);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function intOption(array $options, string $key, int $default, int $min): int
    {
        $value = $options[$key] ?? $default;
        if (!is_numeric($value)) {
            return $default;
        }

        $parsed = (int) $value;
        if ($parsed < $min) {
            return $min;
        }

        return $parsed;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function floatOption(array $options, string $key, float $default, float $min, float $max): float
    {
        $value = $options[$key] ?? $default;
        if (!is_numeric($value)) {
            return $default;
        }

        $parsed = (float) $value;
        if ($parsed < $min) {
            return $min;
        }
        if ($parsed > $max) {
            return $max;
        }

        return $parsed;
    }
}
