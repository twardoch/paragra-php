<?php

declare(strict_types=1);

// this_file: paragra-php/src/VectorStore/ChromaVectorStore.php

namespace ParaGra\VectorStore;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use ParaGra\Response\UnifiedResponse;
use RuntimeException;
use Throwable;

use function array_map;
use function array_values;
use function count;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function max;
use function rawurlencode;
use function rtrim;
use function sprintf;
use function str_ends_with;
use function trim;

use const JSON_THROW_ON_ERROR;

/**
 * REST adapter for ChromaDB v2 collections.
 */
final class ChromaVectorStore implements VectorStoreInterface
{
    private ClientInterface $httpClient;

    private VectorNamespace $defaultNamespace;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $tenant,
        private readonly string $database,
        private readonly string $collection,
        ?VectorNamespace $defaultNamespace = null,
        private readonly ?string $authToken = null,
        ?ClientInterface $httpClient = null,
    ) {
        $this->defaultNamespace = $defaultNamespace ?? new VectorNamespace($collection, $collection);
        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => $this->normalizeBaseUrl($baseUrl),
            'timeout' => 30,
        ]);
    }

    #[\Override]
    public function getProvider(): string
    {
        return 'chroma';
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

        $tenant = $options['tenant'] ?? $this->tenant;
        $database = $options['database'] ?? $this->database;
        $collection = $this->resolveCollection($namespace);

        $ids = [];
        $embeddings = [];
        $metadatas = [];
        $documents = [];
        $hasDocuments = false;

        foreach ($records as $record) {
            $ids[] = (string) $record['id'];
            $embeddings[] = array_map(static fn (float $value): float => $value, array_values($record['values']));
            $metadata = is_array($record['metadata'] ?? null) ? $record['metadata'] : [];
            $metadatas[] = $metadata;

            $document = $this->extractDocument($metadata);
            if ($document !== null) {
                $documents[] = $document;
                $hasDocuments = true;
            } else {
                $documents[] = '';
            }
        }

        $payload = [
            'ids' => $ids,
            'embeddings' => $embeddings,
            'metadatas' => $metadatas,
        ];

        if ($hasDocuments) {
            $payload['documents'] = $documents;
        }

        $path = $this->buildCollectionPath($tenant, $database, $collection, 'upsert');
        $this->request('POST', $path, $payload, $options);

        return ['upserted' => count($records), 'updated' => 0];
    }

    #[\Override]
    public function delete(VectorNamespace $namespace, array $ids, array $options = []): array
    {
        if ($ids === []) {
            return ['deleted' => 0];
        }

        $tenant = $options['tenant'] ?? $this->tenant;
        $database = $options['database'] ?? $this->database;
        $collection = $this->resolveCollection($namespace);
        $path = $this->buildCollectionPath($tenant, $database, $collection, 'delete');

        $this->request('POST', $path, [
            'ids' => array_values($ids),
        ], $options);

        return ['deleted' => count($ids)];
    }

    #[\Override]
    public function query(VectorNamespace $namespace, array $vector, array $options = []): UnifiedResponse
    {
        $tenant = $options['tenant'] ?? $this->tenant;
        $database = $options['database'] ?? $this->database;
        $collection = $this->resolveCollection($namespace);

        $payload = [
            'query_embeddings' => [array_values($vector)],
            'n_results' => (int) ($options['top_k'] ?? 10),
            'include' => $this->buildIncludeList($options),
        ];

        $filter = $options['filter'] ?? $this->buildWhereFilter($namespace->getMetadata());
        if (is_array($filter) && $filter !== []) {
            $payload['where'] = $filter;
        }

        $path = $this->buildCollectionPath($tenant, $database, $collection, 'query');
        $response = $this->request('POST', $path, $payload, $options);

        $chunks = $this->normalizeQueryResults($response);
        $metadata = [
            'tenant' => $tenant,
            'database' => $database,
            'collection' => $collection,
            'match_count' => count($chunks),
        ];

        return UnifiedResponse::fromChunks(
            provider: $this->getProvider(),
            model: $collection,
            chunks: $chunks,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = [], array $options = []): array
    {
        $requestOptions = [
            'headers' => $this->buildDefaultHeaders(),
        ];

        if ($payload !== []) {
            $requestOptions['json'] = $payload;
        }

        if (isset($options['timeout_ms'])) {
            $timeout = (int) $options['timeout_ms'];
            if ($timeout > 0) {
                $requestOptions['timeout'] = $timeout / 1000;
            }
        }

        try {
            $response = $this->httpClient->request($method, $path, $requestOptions);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf('Chroma request failed: %s', $exception->getMessage()),
                0,
                $exception
            );
        }

        $body = (string) $response->getBody();
        if ($body === '') {
            return [];
        }

        return json_decode($body, true, 512, JSON_THROW_ON_ERROR) ?? [];
    }

    private function buildCollectionPath(string $tenant, string $database, string $collection, string $suffix): string
    {
        return sprintf(
            'tenants/%s/databases/%s/collections/%s/%s',
            rawurlencode($tenant),
            rawurlencode($database),
            rawurlencode($collection),
            trim($suffix, '/')
        );
    }

    /**
     * @param array<string, string|int|float|bool|list<string|int|float|bool>> $metadata
     */
    private function buildWhereFilter(array $metadata): array
    {
        if ($metadata === []) {
            return [];
        }

        $conditions = [];

        foreach ($metadata as $key => $value) {
            if (is_array($value)) {
                $conditions[] = [
                    $key => ['$in' => array_values($value)],
                ];
                continue;
            }

            $conditions[] = [
                $key => $value,
            ];
        }

        if (count($conditions) === 1) {
            return $conditions[0];
        }

        return ['$and' => $conditions];
    }

    /**
     * @return list<string>
     */
    private function buildIncludeList(array $options): array
    {
        $includes = ['documents', 'metadatas'];

        if (!empty($options['include_vectors'])) {
            $includes[] = 'embeddings';
        }

        return $includes;
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeQueryResults(array $response): array
    {
        $ids = $this->unwrapFirstList($response['ids'] ?? []);
        $documents = $this->unwrapFirstList($response['documents'] ?? []);
        $metadatas = $this->unwrapFirstList($response['metadatas'] ?? []);
        $distances = $this->unwrapFirstList($response['distances'] ?? []);

        $max = max(count($ids), count($documents), count($metadatas), count($distances));
        $chunks = [];

        for ($i = 0; $i < $max; $i++) {
            $metadata = is_array($metadatas[$i] ?? null) ? $metadatas[$i] : [];
            $document = isset($documents[$i]) && is_string($documents[$i])
                ? trim((string) $documents[$i])
                : null;

            $text = $document !== null && $document !== '' ? $document : $this->extractText($metadata);
            if ($text === null) {
                continue;
            }

            $chunk = ['text' => $text];

            if (isset($ids[$i]) && is_string($ids[$i]) && trim($ids[$i]) !== '') {
                $chunk['document_id'] = trim($ids[$i]);
            }

            if (isset($distances[$i]) && is_numeric($distances[$i])) {
                $chunk['score'] = $this->distanceToScore((float) $distances[$i]);
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

        return $chunks;
    }

    /**
     * @param mixed $list
     *
     * @return list<mixed>
     */
    private function unwrapFirstList(mixed $list): array
    {
        if (!is_array($list) || $list === []) {
            return [];
        }

        $first = $list[0] ?? null;
        if (is_array($first)) {
            return array_values($first);
        }

        return array_values($list);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function extractDocument(array $metadata): ?string
    {
        $text = $metadata['text'] ?? $metadata['content'] ?? null;
        if (!is_string($text)) {
            return null;
        }

        $clean = trim($text);

        return $clean === '' ? null : $clean;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function extractText(array $metadata): ?string
    {
        $text = $metadata['text'] ?? $metadata['content'] ?? null;
        if (!is_string($text)) {
            return null;
        }

        $clean = trim($text);

        return $clean === '' ? null : $clean;
    }

    private function resolveCollection(VectorNamespace $namespace): string
    {
        return $namespace->getCollection() ?? $namespace->getName() ?? $this->collection;
    }

    private function distanceToScore(float $distance): float
    {
        $normalized = $distance < 0 ? 0 : $distance;

        return 1.0 / (1.0 + $normalized);
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        $trimmed = rtrim($baseUrl, '/');
        if (!str_ends_with($trimmed, '/api/v2')) {
            $trimmed .= '/api/v2';
        }

        if (!str_ends_with($trimmed, '/')) {
            $trimmed .= '/';
        }

        return $trimmed;
    }

    /**
     * @return array<string, string>
     */
    private function buildDefaultHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
        ];

        if ($this->authToken !== null && trim($this->authToken) !== '') {
            $headers['Authorization'] = sprintf('Bearer %s', trim($this->authToken));
        }

        return $headers;
    }
}
