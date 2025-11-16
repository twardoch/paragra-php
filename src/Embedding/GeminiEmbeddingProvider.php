<?php

declare(strict_types=1);

// this_file: paragra-php/src/Embedding/GeminiEmbeddingProvider.php

namespace ParaGra\Embedding;

use Gemini\Contracts\ClientContract as GeminiClient;
use Gemini\Contracts\Resources\EmbeddingModalContract;
use Gemini\Requests\GenerativeModel\EmbedContentRequest;
use InvalidArgumentException;
use RuntimeException;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function sort;
use function sqrt;

final class GeminiEmbeddingProvider implements EmbeddingProviderInterface
{
    private GeminiEmbeddingConfig $config;

    private GeminiClient $client;

    public function __construct(GeminiEmbeddingConfig $config, ?GeminiClient $client = null)
    {
        $this->config = $config;
        $this->client = $client ?? $this->createClient($config);
    }

    public function getProvider(): string
    {
        return 'gemini';
    }

    public function getModel(): string
    {
        return $this->config->model;
    }

    /**
     * @return list<int>
     */
    public function getSupportedDimensions(): array
    {
        $dimensions = array_filter([
            $this->config->defaultDimensions,
            $this->config->canonicalDimensions(),
        ], static fn (?int $value): bool => $value !== null && $value > 0);

        $unique = array_values(array_unique(array_map(
            static fn (int $value): int => $value,
            $dimensions
        )));
        sort($unique);

        return $unique;
    }

    public function getMaxBatchSize(): int
    {
        return $this->config->maxBatchSize;
    }

    public function embed(EmbeddingRequest $request): array
    {
        $batchSize = $request->getBatchSize();
        if ($batchSize > $this->config->maxBatchSize) {
            throw new InvalidArgumentException('Embedding batch exceeds the configured batch limit.');
        }

        $outputDimensions = $this->resolveOutputDimensions($request);
        $inputs = $request->getInputs();
        $payloads = $this->buildRequests($inputs, $outputDimensions);

        try {
            $response = $this->embeddingResource()->batchEmbedContents(...$payloads);
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'Gemini embeddings request failed: ' . $exception->getMessage(),
                (int) $exception->getCode(),
                $exception
            );
        }

        $vectors = [];
        foreach ($response->embeddings as $index => $embedding) {
            $values = $embedding->values;
            if ($request->shouldNormalize()) {
                $values = $this->normalizeVector($values);
            }

            $input = $inputs[$index] ?? ['id' => null, 'metadata' => null];
            $vectors[] = [
                'id' => $input['id'],
                'values' => $values,
                'metadata' => $input['metadata'] ?? null,
            ];
        }

        $dimensions = $vectors !== []
            ? count($vectors[0]['values'])
            : ($outputDimensions ?? $this->fallbackDimensions());

        return [
            'provider' => $this->getProvider(),
            'model' => $this->config->model,
            'dimensions' => $dimensions,
            'vectors' => $vectors,
            'usage' => null,
        ];
    }

    /**
     * @param list<array{id: string|null, text: string, metadata: array<string, mixed>|null}> $inputs
     * @return list<EmbedContentRequest>
     */
    private function buildRequests(array $inputs, ?int $dimensions): array
    {
        return array_map(
            fn (array $input): EmbedContentRequest => new EmbedContentRequest(
                model: $this->config->model,
                part: $input['text'],
                taskType: $this->config->taskType,
                title: $this->config->title,
                outputDimensionality: $dimensions
            ),
            $inputs
        );
    }

    private function resolveOutputDimensions(EmbeddingRequest $request): ?int
    {
        $requested = $request->getDimensions();
        if ($requested !== null) {
            if (!$this->config->allowsCustomDimensions()) {
                throw new InvalidArgumentException('Gemini model does not support overriding dimensions.');
            }

            return $requested;
        }

        if (!$this->config->allowsCustomDimensions()) {
            return null;
        }

        if ($this->config->defaultDimensions !== null) {
            return $this->config->defaultDimensions;
        }

        return $this->config->canonicalDimensions();
    }

    private function fallbackDimensions(): int
    {
        return $this->config->defaultDimensions
            ?? $this->config->canonicalDimensions()
            ?? 0;
    }

    /**
     * @param list<float> $values
     * @return list<float>
     */
    private function normalizeVector(array $values): array
    {
        $norm = 0.0;
        foreach ($values as $value) {
            $norm += $value * $value;
        }
        $norm = sqrt($norm);

        if ($norm === 0.0) {
            return $values;
        }

        return array_map(
            static fn (float $value): float => $value / $norm,
            $values
        );
    }

    private function embeddingResource(): EmbeddingModalContract
    {
        return $this->client->embeddingModel($this->config->model);
    }

    private function createClient(GeminiEmbeddingConfig $config): GeminiClient
    {
        $factory = \Gemini::factory()->withApiKey($config->apiKey);

        if ($config->baseUrl !== null) {
            $factory = $factory->withBaseUrl($config->baseUrl);
        }

        return $factory->make();
    }
}
