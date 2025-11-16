<?php

declare(strict_types=1);

// this_file: paragra-php/src/Embedding/CohereEmbeddingProvider.php

namespace ParaGra\Embedding;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use RuntimeException;

use function array_is_list;
use function array_map;
use function count;
use function is_array;
use function is_numeric;
use function ltrim;
use function reset;
use function rtrim;
use function sqrt;

final class CohereEmbeddingProvider implements EmbeddingProviderInterface
{
    private CohereEmbeddingConfig $config;

    private ClientInterface $client;

    public function __construct(CohereEmbeddingConfig $config, ?ClientInterface $client = null)
    {
        $this->config = $config;
        $this->client = $client ?? new HttpClient([
            'timeout' => 30,
        ]);
    }

    public function getProvider(): string
    {
        return 'cohere';
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

        foreach (CohereEmbeddingConfig::MODEL_DIMENSIONS as $dimension) {
            $dimensions[] = $dimension;
        }

        if ($this->config->defaultDimensions !== null) {
            $dimensions[] = $this->config->defaultDimensions;
        }

        $dimensions = array_values(array_unique(array_filter(
            $dimensions,
            static fn (int $value): bool => $value > 0
        )));
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

        $requestedDimensions = $request->getDimensions();
        if ($requestedDimensions !== null && $requestedDimensions !== $this->config->defaultDimensions) {
            throw new InvalidArgumentException('Cohere embeddings do not support overriding dimensions.');
        }

        $payload = [
            'model' => $this->config->model,
            'texts' => array_map(
                static fn (array $input): string => $input['text'],
                $request->getInputs()
            ),
            'input_type' => $this->config->inputType,
        ];

        if ($this->config->truncate !== null) {
            $payload['truncate'] = $this->config->truncate;
        }

        if ($this->config->embeddingTypes !== []) {
            $payload['embedding_types'] = $this->config->embeddingTypes;
        }

        $url = $this->buildUrl();

        try {
            $response = $this->client->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->config->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
            ]);
        } catch (GuzzleException $exception) {
            throw new RuntimeException(
                'Cohere embeddings request failed: ' . $exception->getMessage(),
                (int) $exception->getCode(),
                $exception
            );
        }

        try {
            $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException(
                'Failed to decode Cohere embeddings response: ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        $vectors = [];
        $inputs = $request->getInputs();

        foreach ($this->extractVectors($data['embeddings'] ?? null) as $index => $values) {
            $normalized = $request->shouldNormalize()
                ? $this->normalizeVector($values)
                : $this->castToFloatList($values);

            $input = $inputs[$index] ?? ['id' => null, 'metadata' => null];
            $vectors[] = [
                'id' => $input['id'],
                'values' => $normalized,
                'metadata' => $input['metadata'] ?? null,
            ];
        }

        $dimensions = $vectors !== []
            ? count($vectors[0]['values'])
            : ($this->config->defaultDimensions ?? 0);

        $usage = null;
        if (isset($data['meta']['billed_units']) && is_array($data['meta']['billed_units'])) {
            $usage = $data['meta']['billed_units'];
        }

        return [
            'provider' => $this->getProvider(),
            'model' => $this->config->model,
            'dimensions' => $dimensions,
            'vectors' => $vectors,
            'usage' => $usage,
        ];
    }

    private function buildUrl(): string
    {
        return rtrim($this->config->baseUri, '/') . '/' . ltrim($this->config->endpoint, '/');
    }

    /**
     * @param mixed $rawEmbeddings
     * @return list<list<float>>
     */
    private function extractVectors(mixed $rawEmbeddings): array
    {
        if (!is_array($rawEmbeddings)) {
            throw new RuntimeException('Cohere embeddings response missing embeddings payload.');
        }

        if (array_is_list($rawEmbeddings)) {
            return $this->castVectorsList($rawEmbeddings);
        }

        foreach ($this->config->embeddingTypes as $type) {
            if (isset($rawEmbeddings[$type]) && is_array($rawEmbeddings[$type])) {
                return $this->castVectorsList($rawEmbeddings[$type]);
            }
        }

        if (isset($rawEmbeddings['float']) && is_array($rawEmbeddings['float'])) {
            return $this->castVectorsList($rawEmbeddings['float']);
        }

        $first = reset($rawEmbeddings);
        if (is_array($first)) {
            return $this->castVectorsList($first);
        }

        throw new RuntimeException('Cohere embeddings response contained no usable vector data.');
    }

    /**
     * @param list<mixed> $vectors
     * @return list<list<float>>
     */
    private function castVectorsList(array $vectors): array
    {
        $results = [];

        foreach ($vectors as $vector) {
            if (!is_array($vector)) {
                throw new RuntimeException('Cohere embeddings vector entry must be an array of numbers.');
            }
            $results[] = $this->castToFloatList($vector);
        }

        return $results;
    }

    /**
     * @param list<float|int|string> $values
     * @return list<float>
     */
    private function castToFloatList(array $values): array
    {
        return array_map(
            static function ($value): float {
                if (!is_numeric($value)) {
                    throw new RuntimeException('Cohere embeddings vector must contain numeric values.');
                }

                return (float) $value;
            },
            $values
        );
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
}
