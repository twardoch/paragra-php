<?php

declare(strict_types=1);

// this_file: paragra-php/src/VectorStore/QdrantVectorStore.php

namespace ParaGra\VectorStore;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use ParaGra\Response\UnifiedResponse;
use RuntimeException;
use Throwable;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function is_array;
use function is_string;
use function json_decode;
use function sprintf;
use function str_ends_with;
use function trim;

use const JSON_THROW_ON_ERROR;

/**
 * HTTP adapter for Qdrant's REST API.
 */
final class QdrantVectorStore implements VectorStoreInterface
{
    private ClientInterface $httpClient;

    private VectorNamespace $defaultNamespace;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $collection,
        private readonly ?string $apiKey = null,
        ?VectorNamespace $defaultNamespace = null,
        ?ClientInterface $httpClient = null,
    ) {
        $this->defaultNamespace = $defaultNamespace ?? new VectorNamespace($collection);
        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => $this->normalizeBaseUrl($baseUrl),
            'timeout' => 30,
        ]);
    }

    public function getProvider(): string
    {
        return 'qdrant';
    }

    public function getDefaultNamespace(): VectorNamespace
    {
        return $this->defaultNamespace;
    }

    /**
     * @param list<array{
     *     id: string,
     *     values: list<float>,
     *     metadata?: array<string, mixed>
     * }> $records
     *
     * @return array{upserted: int, updated: int, task_id?: string}
     */
    public function upsert(VectorNamespace $namespace, array $records, array $options = []): array
    {
        $payload = [
            'wait' => (bool) ($options['wait_for_sync'] ?? false),
            'points' => array_map(fn (array $record): array => [
                'id' => (string) $record['id'],
                'vector' => array_map(static fn (float $value): float => $value, $record['values']),
                'payload' => $record['metadata'] ?? [],
            ], $records),
        ];

        $this->request('PUT', sprintf('collections/%s/points', $namespace->getName()), $payload);

        return ['upserted' => count($records), 'updated' => 0];
    }

    /**
     * @param list<string> $ids
     *
     * @return array{deleted: int}
     */
    public function delete(VectorNamespace $namespace, array $ids, array $options = []): array
    {
        $payload = [
            'points' => array_values($ids),
            'wait' => (bool) ($options['wait_for_sync'] ?? false),
        ];

        $this->request('POST', sprintf('collections/%s/points/delete', $namespace->getName()), $payload);

        return ['deleted' => count($ids)];
    }

    /**
     * @param list<float> $vector
     */
    public function query(VectorNamespace $namespace, array $vector, array $options = []): UnifiedResponse
    {
        $payload = [
            'vector' => array_values($vector),
            'limit' => (int) ($options['top_k'] ?? 10),
            'with_payload' => true,
            'with_vector' => (bool) ($options['include_vectors'] ?? false),
        ];

        $filter = $options['filter'] ?? $this->buildMetadataFilter($namespace->getMetadata());
        if (is_array($filter) && $filter !== []) {
            $payload['filter'] = $filter;
        }

        $response = $this->request('POST', sprintf('collections/%s/points/search', $namespace->getName()), $payload);
        $result = $this->normalizeHits($response['result'] ?? []);

        $metadata = array_filter([
            'collection' => $namespace->getName(),
            'match_count' => count($result),
        ]);

        return UnifiedResponse::fromChunks(
            provider: $this->getProvider(),
            model: $namespace->getName(),
            chunks: $result,
            metadata: $metadata,
        );
    }

    /**
     * @param list<array<string, mixed>> $hits
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeHits(array $hits): array
    {
        $chunks = [];

        foreach ($hits as $hit) {
            if (!is_array($hit)) {
                continue;
            }

            $payload = is_array($hit['payload'] ?? null) ? $hit['payload'] : [];
            $text = $this->extractText($payload);
            if ($text === null) {
                continue;
            }

            $chunk = ['text' => $text];

            if (isset($hit['score'])) {
                $chunk['score'] = (float) $hit['score'];
            }

            if (isset($hit['id']) && trim((string) $hit['id']) !== '') {
                $chunk['document_id'] = trim((string) $hit['id']);
            }

            $documentName = $payload['title'] ?? $payload['document_name'] ?? null;
            if (is_string($documentName) && trim($documentName) !== '') {
                $chunk['document_name'] = trim($documentName);
                unset($payload['title'], $payload['document_name']);
            }

            if ($payload !== []) {
                $chunk['metadata'] = $payload;
            }

            $chunks[] = $chunk;
        }

        return array_values($chunks);
    }

    /**
     * @param array<string, string|int|float|bool|list<string|int|float|bool>> $metadata
     */
    private function buildMetadataFilter(array $metadata): array
    {
        if ($metadata === []) {
            return [];
        }

        $conditions = [];

        foreach ($metadata as $key => $value) {
            if (is_array($value)) {
                $conditions[] = [
                    'key' => $key,
                    'match' => ['any' => array_values($value)],
                ];
                continue;
            }

            $conditions[] = [
                'key' => $key,
                'match' => ['value' => $value],
            ];
        }

        return ['must' => $conditions];
    }

    private function extractText(array &$payload): ?string
    {
        foreach (['text', 'content', 'body'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key])) {
                $text = trim($payload[$key]);
                unset($payload[$key]);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $uri, array $payload): array
    {
        $headers = [
            'Accept' => 'application/json',
        ];

        if ($this->apiKey !== null && trim($this->apiKey) !== '') {
            $headers['api-key'] = $this->apiKey;
        }

        try {
            $response = $this->httpClient->request($method, $uri, [
                'headers' => $headers,
                'json' => $payload,
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf('Qdrant request to "%s" failed: %s', $uri, $exception->getMessage()),
                (int) $exception->getCode(),
                $exception
            );
        }

        $contents = (string) $response->getBody();
        if ($contents === '') {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function normalizeBaseUrl(string $url): string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            throw new RuntimeException('Qdrant base URL cannot be empty.');
        }

        return str_ends_with($trimmed, '/') ? $trimmed : $trimmed . '/';
    }
}
