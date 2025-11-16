<?php

declare(strict_types=1);

// this_file: paragra-php/src/VectorStore/GeminiFileSearchVectorStore.php

namespace ParaGra\VectorStore;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use ParaGra\Response\UnifiedResponse;
use RuntimeException;
use Throwable;

use function array_chunk;
use function array_filter;
use function array_is_list;
use function count;
use function is_bool;
use function is_array;
use function is_numeric;
use function is_scalar;
use function is_string;
use function json_decode;
use function json_encode;
use function ltrim;
use function preg_replace;
use function rawurlencode;
use function sprintf;
use function str_starts_with;
use function strtolower;
use function trim;
use function uniqid;

use const JSON_THROW_ON_ERROR;

/**
 * Thin REST adapter for Gemini File Search stores (`fileSearchStores/*` or `corpora/*`).
 *
 * Google handles vector generation internally. ParaGra only needs to create documents
 * that contain chunk text plus metadata, delete them when requested, and issue semantic
 * queries that return normalized `UnifiedResponse` chunks.
 */
final class GeminiFileSearchVectorStore implements VectorStoreInterface
{
    private const MAX_BATCH_SIZE = 16;

    private ClientInterface $httpClient;

    private VectorNamespace $defaultNamespace;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $resourceName,
        ?VectorNamespace $defaultNamespace = null,
        ?ClientInterface $httpClient = null,
    ) {
        $this->defaultNamespace = $defaultNamespace ?? new VectorNamespace(
            name: 'gemini-file-search',
            collection: $this->resourceName,
            eventuallyConsistent: true,
        );

        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => 'https://generativelanguage.googleapis.com',
            'timeout' => 30,
        ]);
    }

    #[\Override]
    public function getProvider(): string
    {
        return 'gemini-file-search';
    }

    #[\Override]
    public function getDefaultNamespace(): VectorNamespace
    {
        return $this->defaultNamespace;
    }

    #[\Override]
    public function upsert(VectorNamespace $namespace, array $records, array $options = []): array
    {
        if ($records === []) {
            return ['upserted' => 0, 'updated' => 0];
        }

        $resource = $this->resolveResource($namespace, $options);
        $requests = [];

        foreach ($records as $record) {
            $payload = $this->buildDocumentRequest($record);
            if ($payload !== null) {
                $requests[] = $payload;
            }
        }

        if ($requests === []) {
            return ['upserted' => 0, 'updated' => 0];
        }

        foreach (array_chunk($requests, self::MAX_BATCH_SIZE) as $batch) {
            $this->request(
                'POST',
                $this->buildPath($resource, 'documents:batchCreate'),
                ['requests' => $batch]
            );
        }

        return ['upserted' => count($requests), 'updated' => 0];
    }

    #[\Override]
    public function delete(VectorNamespace $namespace, array $ids, array $options = []): array
    {
        if ($ids === []) {
            return ['deleted' => 0];
        }

        $resource = $this->resolveResource($namespace, $options);
        $deleted = 0;

        foreach ($ids as $id) {
            $documentId = $this->sanitizeDocumentId($id);
            $this->request(
                'DELETE',
                $this->buildPath($resource, sprintf('documents/%s', rawurlencode($documentId)))
            );
            $deleted++;
        }

        return ['deleted' => $deleted];
    }

    /**
     * @param list<float> $vector Ignored; Gemini queries require a text prompt supplied via $options['query'].
     */
    #[\Override]
    public function query(VectorNamespace $namespace, array $vector, array $options = []): UnifiedResponse
    {
        $query = $options['query'] ?? null;
        if (!is_string($query) || trim($query) === '') {
            throw new RuntimeException('Gemini File Search queries require a non-empty "query" option.');
        }

        $resource = $this->resolveResource($namespace, $options);
        $topK = (int) ($options['top_k'] ?? 5);
        if ($topK <= 0) {
            $topK = 5;
        }

        $payload = [
            'query' => trim($query),
            'chunkControl' => [
                'maxChunks' => $topK,
            ],
        ];

        $filters = $options['filter'] ?? $namespace->getMetadata();
        if (is_array($filters) && $filters !== []) {
            $payload['metadataFilters'] = $this->buildMetadataFilters($filters);
        }

        $response = $this->request(
            'POST',
            $this->buildPath($resource, 'documents:query'),
            $payload
        );

        $chunks = $this->normalizeRelevantChunks($response['relevantChunks'] ?? []);
        $metadata = array_filter([
            'resource' => $resource,
            'match_count' => count($chunks),
        ]);

        return UnifiedResponse::fromChunks(
            provider: $this->getProvider(),
            model: $namespace->getCollection() ?? $resource,
            chunks: $chunks,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $record
     */
    private function buildDocumentRequest(array $record): ?array
    {
        $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
        $text = $this->extractText($metadata);
        if ($text === null) {
            $recordId = isset($record['id']) ? trim((string) $record['id']) : '(unknown)';
            throw new RuntimeException(sprintf(
                'Gemini File Search upsert requires chunk text via metadata["text"] (record id: %s).',
                $recordId === '' ? '(empty)' : $recordId
            ));
        }

        $documentId = $this->sanitizeDocumentId($record['id'] ?? null);
        $displayName = $this->extractDisplayName($metadata, $documentId);
        $customMetadata = $this->formatCustomMetadata($metadata);

        $document = [
            'document' => [
                'displayName' => $displayName,
                'jsonData' => json_encode([
                    'id' => $documentId,
                    'text' => $text,
                    'metadata' => $metadata,
                ], JSON_THROW_ON_ERROR),
            ],
            'documentId' => $documentId,
        ];

        if ($customMetadata !== []) {
            $document['document']['customMetadata'] = $customMetadata;
        }

        return $document;
    }

    /**
     * @return list<array{key: string, value: string}>
     */
    private function formatCustomMetadata(array $metadata): array
    {
        $entries = [];
        foreach ($metadata as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (is_array($value) && array_is_list($value)) {
                foreach ($value as $entry) {
                    if (is_scalar($entry)) {
                        $entries[] = [
                            'key' => $key,
                            'value' => $this->stringifyMetadataValue($entry),
                        ];
                    }
                }
                continue;
            }

            if (is_scalar($value)) {
                $entries[] = [
                    'key' => $key,
                    'value' => $this->stringifyMetadataValue($value),
                ];
            }
        }

        return $entries;
    }

    private function stringifyMetadataValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return trim((string) $value);
    }

    private function extractText(array &$metadata): ?string
    {
        foreach (['text', 'body', 'content'] as $key) {
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

    private function extractDisplayName(array &$metadata, string $fallback): string
    {
        foreach (['display_name', 'title', 'name'] as $key) {
            if (isset($metadata[$key]) && is_string($metadata[$key])) {
                $value = trim($metadata[$key]);
                unset($metadata[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return $fallback;
    }

    /**
     * @param array<string, string|int|float|bool|list<string|int|float|bool>> $filters
     *
     * @return list<array{key: string, value: string}>
     */
    private function buildMetadataFilters(array $filters): array
    {
        $entries = [];
        foreach ($filters as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (is_array($value) && array_is_list($value)) {
                foreach ($value as $entry) {
                    if (is_scalar($entry)) {
                        $entries[] = ['key' => $key, 'value' => $this->stringifyMetadataValue($entry)];
                    }
                }
                continue;
            }

            if (is_scalar($value)) {
                $entries[] = ['key' => $key, 'value' => $this->stringifyMetadataValue($value)];
            }
        }

        return $entries;
    }

    /**
     * @param list<array<string, mixed>> $entries
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeRelevantChunks(array $entries): array
    {
        $chunks = [];

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $text = trim((string) ($entry['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $chunk = ['text' => $text];

            if (isset($entry['relevanceScore'])) {
                $chunk['score'] = (float) $entry['relevanceScore'];
            }

            if (isset($entry['documentId']) && trim((string) $entry['documentId']) !== '') {
                $chunk['document_id'] = trim((string) $entry['documentId']);
            }

            if (isset($entry['chunkId']) && trim((string) $entry['chunkId']) !== '') {
                $metadata = $chunk['metadata'] ?? [];
                $metadata['chunk_id'] = trim((string) $entry['chunkId']);
                $chunk['metadata'] = $metadata;
            }

            if (isset($entry['metadata']) && is_array($entry['metadata']) && $entry['metadata'] !== []) {
                $metadata = $chunk['metadata'] ?? [];
                $chunk['metadata'] = $metadata + $entry['metadata'];
            }

            if (isset($entry['pageSpan']) && is_array($entry['pageSpan'])) {
                $metadata = $chunk['metadata'] ?? [];
                $metadata['page_span'] = $entry['pageSpan'];
                $chunk['metadata'] = $metadata;
            }

            $chunks[] = $chunk;
        }

        return $chunks;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $options = [
            'query' => ['key' => $this->apiKey],
        ];

        if ($payload !== []) {
            $options['json'] = $payload;
        }

        try {
            $response = $this->httpClient->request($method, $path, $options);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf('Gemini File Search request failed: %s', $exception->getMessage()),
                (int) $exception->getCode(),
                $exception
            );
        }

        $body = (string) $response->getBody();
        if (trim($body) === '') {
            return [];
        }

        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }

    private function buildPath(string $resource, string $suffix): string
    {
        $resource = ltrim($resource, '/');
        if ($suffix === '') {
            return sprintf('/v1beta/%s', $resource);
        }

        return sprintf('/v1beta/%s/%s', $resource, $suffix);
    }

    private function resolveResource(VectorNamespace $namespace, array $options): string
    {
        if (isset($options['resource']) && is_string($options['resource']) && trim($options['resource']) !== '') {
            return $this->normalizeResource($options['resource']);
        }

        if ($namespace->getCollection() !== null) {
            return $this->normalizeResource($namespace->getCollection());
        }

        return $this->normalizeResource($this->resourceName);
    }

    private function normalizeResource(string $resource): string
    {
        $clean = trim($resource);
        if ($clean === '') {
            throw new RuntimeException('Gemini File Search resource names cannot be empty.');
        }

        $clean = ltrim($clean, '/');
        if (
            !str_starts_with($clean, 'fileSearchStores/')
            && !str_starts_with($clean, 'corpora/')
            && !str_starts_with($clean, 'projects/')
        ) {
            return sprintf('fileSearchStores/%s', $clean);
        }

        return $clean;
    }

    private function sanitizeDocumentId(mixed $value): string
    {
        $stringValue = is_string($value) ? trim($value) : '';
        if ($stringValue === '') {
            $stringValue = uniqid('doc-', true);
        }

        $safe = preg_replace('/[^a-zA-Z0-9\-\_\.]+/', '-', $stringValue) ?? $stringValue;
        $safe = trim($safe, '-_ .');
        if ($safe === '') {
            $safe = uniqid('doc-', true);
        }

        return strtolower($safe);
    }
}
