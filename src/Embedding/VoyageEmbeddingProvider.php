<?php

declare(strict_types=1);

// this_file: paragra-php/src/Embedding/VoyageEmbeddingProvider.php

namespace ParaGra\Embedding;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use RuntimeException;

use function array_map;
use function count;
use function is_array;
use function is_numeric;
use function json_decode;
use function ltrim;
use function rtrim;
use function sqrt;

final class VoyageEmbeddingProvider implements EmbeddingProviderInterface
{
    private VoyageEmbeddingConfig $config;

    private ClientInterface $client;

    public function __construct(VoyageEmbeddingConfig $config, ?ClientInterface $client = null)
    {
        $this->config = $config;
        $this->client = $client ?? new HttpClient([
            'timeout' => $config->timeout,
        ]);
    }

    public function getProvider(): string
    {
        return 'voyage';
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
        return $this->config->supportedDimensions();
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
            'truncate' => $this->config->truncate,
            'encoding_format' => $this->config->encodingFormat,
        ];

        if ($this->config->inputType !== null) {
            $payload['input_type'] = $this->config->inputType;
        }

        $dimensions = $this->resolveDimensions($request);
        if ($dimensions !== null) {
            $payload['output_dimension'] = $dimensions;
        }

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->config->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => $payload,
            'timeout' => $this->config->timeout,
        ];

        try {
            $response = $this->client->request('POST', $this->buildUrl(), $options);
        } catch (GuzzleException $exception) {
            throw new RuntimeException(
                'Voyage embeddings request failed: ' . $exception->getMessage(),
                (int) $exception->getCode(),
                $exception
            );
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException(
                'Failed to decode Voyage embeddings response: ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        $vectorsData = $this->extractVectors($data);
        if ($vectorsData === []) {
            throw new RuntimeException('Voyage embeddings response missing vectors payload.');
        }

        $vectors = [];
        $inputs = $request->getInputs();
        foreach ($vectorsData as $index => $values) {
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
            : ($dimensions ?? $this->config->defaultDimensions ?? 0);

        $usage = isset($data['usage']) && is_array($data['usage'])
            ? $data['usage']
            : null;

        return [
            'provider' => $this->getProvider(),
            'model' => $this->config->model,
            'dimensions' => $dimensions,
            'vectors' => $vectors,
            'usage' => $usage,
        ];
    }

    private function resolveDimensions(EmbeddingRequest $request): ?int
    {
        if ($request->getDimensions() !== null) {
            return $request->getDimensions();
        }

        return $this->config->defaultDimensions;
    }

    private function buildUrl(): string
    {
        return rtrim($this->config->baseUri, '/') . '/' . ltrim($this->config->endpoint, '/');
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<list<float|int|string>>
     */
    private function extractVectors(array $payload): array
    {
        $vectors = [];

        if (isset($payload['data']) && is_array($payload['data'])) {
            foreach ($payload['data'] as $entry) {
                if (isset($entry['embedding']) && is_array($entry['embedding'])) {
                    $vectors[] = $entry['embedding'];
                }
            }
        }

        if ($vectors === [] && isset($payload['embeddings']) && is_array($payload['embeddings'])) {
            foreach ($payload['embeddings'] as $embedding) {
                if (is_array($embedding)) {
                    $vectors[] = $embedding;
                }
            }
        }

        return $vectors;
    }

    /**
     * @param list<float|int|string> $values
     * @return list<float>
     */
    private function castToFloatList(array $values): array
    {
        $result = [];

        foreach ($values as $value) {
            if (!is_numeric($value)) {
                throw new RuntimeException('Voyage embeddings returned non-numeric vector values.');
            }

            $result[] = (float) $value;
        }

        return $result;
    }

    /**
     * @param list<float|int|string> $vector
     * @return list<float>
     */
    private function normalizeVector(array $vector): array
    {
        $values = $this->castToFloatList($vector);

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
}
