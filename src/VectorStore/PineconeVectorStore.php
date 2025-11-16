<?php

declare(strict_types=1);

// this_file: paragra-php/src/VectorStore/PineconeVectorStore.php

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
use function str_ends_with;
use function trim;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * HTTP adapter for Pinecone's 2024 data plane API.
 */
final class PineconeVectorStore implements VectorStoreInterface
{
    private ClientInterface $httpClient;

    private VectorNamespace $defaultNamespace;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $indexName,
        ?VectorNamespace $defaultNamespace = null,
        private readonly string $apiVersion = '2024-07',
        ?ClientInterface $httpClient = null,
    ) {
        $this->defaultNamespace = $defaultNamespace ?? new VectorNamespace('default');
        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => $this->normalizeBaseUrl($baseUrl),
            'timeout' => 30,
        ]);
    }

    public function getProvider(): string
    {
        return 'pinecone';
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
            'namespace' => $namespace->getName(),
            'vectors' => array_map(fn (array $record): array => [
                'id' => (string) $record['id'],
                'values' => array_map(static fn (float $value): float => $value, $record['values']),
                'metadata' => $record['metadata'] ?? [],
            ], $records),
        ];

        $response = $this->request('POST', 'vectors/upsert', $payload);
        $count = (int) ($response['upsertedCount'] ?? $response['upserted_count'] ?? count($records));

        return ['upserted' => $count, 'updated' => 0];
    }

    /**
     * @param list<string> $ids
     *
     * @return array{deleted: int}
     */
    public function delete(VectorNamespace $namespace, array $ids, array $options = []): array
    {
        $payload = [
            'namespace' => $namespace->getName(),
            'ids' => array_values($ids),
        ];

        $response = $this->request('POST', 'vectors/delete', $payload);

        return ['deleted' => (int) ($response['deletedCount'] ?? $response['deleted_count'] ?? count($ids))];
    }

    /**
     * @param list<float> $vector
     */
    public function query(VectorNamespace $namespace, array $vector, array $options = []): UnifiedResponse
    {
        $payload = [
            'namespace' => $namespace->getName(),
            'vector' => array_values($vector),
            'topK' => (int) ($options['top_k'] ?? 10),
            'includeMetadata' => true,
            'includeValues' => (bool) ($options['include_vectors'] ?? false),
        ];

        $filter = $options['filter'] ?? $this->buildMetadataFilter($namespace->getMetadata());
        if (is_array($filter) && $filter !== []) {
            $payload['filter'] = $filter;
        }

        $response = $this->request('POST', 'query', $payload);
        $chunks = $this->normalizeMatches($response['matches'] ?? []);

        $metadata = array_filter([
            'namespace' => $namespace->toArray(),
            'match_count' => count($chunks),
        ]);

        return UnifiedResponse::fromChunks(
            provider: $this->getProvider(),
            model: $this->indexName,
            chunks: $chunks,
            metadata: $metadata,
        );
    }

    /**
     * @param list<array<string, mixed>> $matches
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeMatches(array $matches): array
    {
        $chunks = [];

        foreach ($matches as $match) {
            if (!is_array($match)) {
                continue;
            }

            $metadata = is_array($match['metadata'] ?? null) ? $match['metadata'] : [];
            $text = $this->extractText($metadata);
            if ($text === null) {
                continue;
            }

            $chunk = [
                'text' => $text,
            ];

            if (isset($match['score'])) {
                $chunk['score'] = (float) $match['score'];
            }

            if (isset($match['id']) && trim((string) $match['id']) !== '') {
                $chunk['document_id'] = trim((string) $match['id']);
            }

            $documentName = $metadata['title'] ?? $metadata['document_name'] ?? null;
            if (is_string($documentName) && trim($documentName) !== '') {
                $chunk['document_name'] = trim($documentName);
                unset($metadata['title'], $metadata['document_name']);
            }

            unset($metadata['text'], $metadata['content']);
            if ($metadata !== []) {
                $chunk['metadata'] = $metadata;
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
        $filter = [];

        foreach ($metadata as $key => $value) {
            if (is_array($value)) {
                $filter[$key] = ['$in' => array_values($value)];
                continue;
            }

            $filter[$key] = ['$eq' => $value];
        }

        return $filter;
    }

    private function extractText(array &$metadata): ?string
    {
        foreach (['text', 'content', 'body'] as $key) {
            if (isset($metadata[$key]) && is_string($metadata[$key])) {
                $text = trim($metadata[$key]);
                unset($metadata[$key]);
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
        try {
            $response = $this->httpClient->request($method, $uri, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Api-Key' => $this->apiKey,
                    'X-Pinecone-API-Version' => $this->apiVersion,
                ],
                'json' => $payload,
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf('Pinecone request to "%s" failed: %s', $uri, $exception->getMessage()),
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

    private function normalizeBaseUrl(string $baseUrl): string
    {
        $trimmed = trim($baseUrl);
        if ($trimmed === '') {
            throw new RuntimeException('Pinecone base URL cannot be empty.');
        }

        return str_ends_with($trimmed, '/') ? $trimmed : $trimmed . '/';
    }
}
