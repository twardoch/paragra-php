<?php

declare(strict_types=1);

// this_file: paragra-php/src/Embedding/OpenAiEmbeddingProvider.php

namespace ParaGra\Embedding;

use InvalidArgumentException;
use OpenAI\Contracts\ClientContract as OpenAiClient;
use OpenAI\Contracts\Resources\EmbeddingsContract as OpenAiEmbeddingsResource;
use RuntimeException;

final class OpenAiEmbeddingProvider implements EmbeddingProviderInterface
{
    private OpenAiEmbeddingConfig $config;

    private OpenAiClient $client;

    public function __construct(OpenAiEmbeddingConfig $config, ?OpenAiClient $client = null)
    {
        $this->config = $config;
        $this->client = $client ?? $this->createClient($config);
    }

    public function getProvider(): string
    {
        return 'openai';
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
        $dimensions = [];

        foreach (OpenAiEmbeddingConfig::MODEL_DIMENSIONS as $dimension) {
            $dimensions[] = $dimension;
        }

        if ($this->config->defaultDimensions !== null) {
            $dimensions[] = $this->config->defaultDimensions;
        }

        $dimensions = array_values(array_unique(array_filter($dimensions, static fn (int $value): bool => $value > 0)));
        sort($dimensions);

        return $dimensions;
    }

    public function getMaxBatchSize(): int
    {
        return $this->config->maxBatchSize;
    }

    public function embed(EmbeddingRequest $request): array
    {
        if ($request->getBatchSize() > $this->config->maxBatchSize) {
            throw new InvalidArgumentException('Embedding batch exceeds the configured batch limit.');
        }

        $payload = [
            'model' => $this->config->model,
            'input' => array_map(
                static fn (array $input): string => $input['text'],
                $request->getInputs()
            ),
        ];

        $dimensions = $this->resolveDimensions($request);
        if ($dimensions !== null) {
            $payload['dimensions'] = $dimensions;
        }

        try {
            $response = $this->embeddingsResource()->create($payload);
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'Failed to generate embeddings: ' . $exception->getMessage(),
                (int) $exception->getCode(),
                $exception
            );
        }

        $vectors = [];
        $inputs = $request->getInputs();
        foreach ($response->embeddings as $index => $embedding) {
            $values = $embedding->embedding;

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

        $usage = null;
        if ($response->usage !== null) {
            $usage = [
                'prompt_tokens' => $response->usage->promptTokens,
                'total_tokens' => $response->usage->totalTokens,
            ];
        }

        return [
            'provider' => $this->getProvider(),
            'model' => $this->config->model,
            'dimensions' => $vectors !== [] ? count($vectors[0]['values']) : ($dimensions ?? 0),
            'vectors' => $vectors,
            'usage' => $usage,
        ];
    }

    private function resolveDimensions(EmbeddingRequest $request): ?int
    {
        if ($request->getDimensions() !== null) {
            return $request->getDimensions();
        }

        if ($this->config->defaultDimensions !== null) {
            return $this->config->defaultDimensions;
        }

        return OpenAiEmbeddingConfig::MODEL_DIMENSIONS[$this->config->model] ?? null;
    }

    /**
     * @param list<float> $vector
     * @return list<float>
     */
    private function normalizeVector(array $vector): array
    {
        $norm = 0.0;
        foreach ($vector as $value) {
            $norm += $value * $value;
        }
        $norm = sqrt($norm);

        if ($norm === 0.0) {
            return $vector;
        }

        return array_map(
            static fn (float $value): float => $value / $norm,
            $vector
        );
    }

    private function embeddingsResource(): OpenAiEmbeddingsResource
    {
        return $this->client->embeddings();
    }

    private function createClient(OpenAiEmbeddingConfig $config): OpenAiClient
    {
        $factory = \OpenAI::factory()->withApiKey($config->apiKey);

        if ($config->baseUrl !== null) {
            $factory = $factory->withBaseUri($config->baseUrl);
        }

        return $factory->make();
    }
}
