<?php

declare(strict_types=1);

// this_file: paragra-php/src/VectorStore/WeaviateVectorStore.php

namespace ParaGra\VectorStore;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use ParaGra\Response\UnifiedResponse;
use RuntimeException;
use Throwable;

use function array_filter;
use function array_is_list;
use function array_map;
use function array_values;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function json_decode;
use function max;
use function preg_match;
use function preg_replace;
use function sprintf;
use function str_ends_with;
use function trim;

use const JSON_THROW_ON_ERROR;

/**
 * HTTP + GraphQL adapter covering Weaviate batch upserts, deletions, and vector queries.
 */
final class WeaviateVectorStore implements VectorStoreInterface
{
    private ClientInterface $httpClient;

    private VectorNamespace $defaultNamespace;

    /** @var list<string> */
    private array $defaultProperties;

    /**
     * @param list<string> $defaultProperties
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $className,
        private readonly ?string $apiKey = null,
        ?VectorNamespace $defaultNamespace = null,
        private readonly string $consistencyLevel = 'ONE',
        array $defaultProperties = ['text'],
        ?ClientInterface $httpClient = null,
    ) {
        $this->defaultNamespace = $defaultNamespace ?? new VectorNamespace($className, $className);
        $this->defaultProperties = $this->sanitizeProperties($defaultProperties);
        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => $this->normalizeBaseUrl($baseUrl),
            'timeout' => 30,
        ]);
    }

    #[\Override]
    public function getProvider(): string
    {
        return 'weaviate';
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

        $class = $this->resolveClass($namespace);
        $tenant = $this->resolveTenant($namespace, $options);

        $payload = [
            'objects' => array_map(
                fn (array $record): array => $this->formatUpsertRecord($class, $record, $tenant),
                $records
            ),
        ];

        $query = $this->buildConsistencyQuery($options);
        $headers = $this->buildHeaders($tenant);

        $this->request('POST', 'batch/objects', $payload, $query, $headers);

        return ['upserted' => count($records), 'updated' => 0];
    }

    #[\Override]
    public function delete(VectorNamespace $namespace, array $ids, array $options = []): array
    {
        if ($ids === []) {
            return ['deleted' => 0];
        }

        $class = $this->resolveClass($namespace);
        $tenant = $this->resolveTenant($namespace, $options);

        $where = $this->buildIdFilter($ids);
        $match = [
            'class' => $class,
            'where' => $where,
        ];

        if ($tenant !== null) {
            $match['tenant'] = $tenant;
        }

        $payload = [
            'match' => $match,
            'output' => 'minimal',
            'dryRun' => false,
        ];

        $headers = $this->buildHeaders($tenant);
        $query = $this->buildConsistencyQuery($options);
        $response = $this->request('DELETE', 'batch/objects', $payload, $query, $headers);

        $deleted = (int) ($response['results']['successful'] ?? $response['results']['successfullyDeleted'] ?? count($ids));

        return ['deleted' => $deleted];
    }

    #[\Override]
    public function query(VectorNamespace $namespace, array $vector, array $options = []): UnifiedResponse
    {
        $class = $this->resolveClass($namespace);
        $tenant = $this->resolveTenant($namespace, $options);
        $consistency = $this->resolveConsistencyLevel($options);

        $properties = $this->resolveProperties($options['properties'] ?? null);
        $limit = (int) ($options['top_k'] ?? 10);
        $includeVectors = (bool) ($options['include_vectors'] ?? false);

        $whereFilter = $options['filter'] ?? $this->buildMetadataFilter($namespace);

        $queryString = $this->buildGraphqlQuery($class, $limit, $properties, $includeVectors);
        $variables = [
            'vector' => array_map(static fn (float $value): float => $value, array_values($vector)),
            'where' => $whereFilter,
            'tenant' => $tenant,
            'consistency' => $consistency,
        ];

        $headers = $this->buildHeaders($tenant);
        $response = $this->request('POST', 'graphql', [
            'query' => $queryString,
            'variables' => $variables,
        ], [], $headers);

        if (isset($response['errors']) && is_array($response['errors']) && $response['errors'] !== []) {
            $message = (string) ($response['errors'][0]['message'] ?? 'Unknown GraphQL error');
            throw new RuntimeException(sprintf('Weaviate GraphQL query failed: %s', $message));
        }

        $results = $response['data']['Get'][$class] ?? [];
        $chunks = $this->normalizeGraphqlResults(is_array($results) ? $results : [], $includeVectors);

        $metadata = array_filter([
            'class' => $class,
            'match_count' => count($chunks),
            'tenant' => $tenant,
        ]);

        return UnifiedResponse::fromChunks(
            provider: $this->getProvider(),
            model: $class,
            chunks: $chunks,
            metadata: $metadata,
        );
    }

    /**
     * @param array{
     *     id: string,
     *     values: list<float>,
     *     metadata?: array<string, mixed>
     * } $record
     *
     * @return array<string, mixed>
     */
    private function formatUpsertRecord(string $class, array $record, ?string $tenant): array
    {
        $properties = $record['metadata'] ?? [];
        if (!is_array($properties)) {
            $properties = [];
        }

        $payload = [
            'id' => (string) $record['id'],
            'class' => $class,
            'vector' => array_map(static fn (float $value): float => $value, $record['values']),
            'properties' => $properties,
        ];

        if ($tenant !== null) {
            $payload['tenant'] = $tenant;
        }

        return $payload;
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, mixed>
     */
    private function buildIdFilter(array $ids): array
    {
        $ids = array_values($ids);
        if (count($ids) === 1) {
            return [
                'path' => ['id'],
                'operator' => 'Equal',
                'valueText' => $ids[0],
            ];
        }

        return [
            'path' => ['id'],
            'operator' => 'ContainsAny',
            'valueStringArray' => $ids,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildConsistencyQuery(array $options): array
    {
        $level = (string) ($options['consistency_level'] ?? $this->consistencyLevel);
        $trimmed = trim($level);

        return $trimmed === '' ? [] : ['consistency_level' => $trimmed];
    }

    private function resolveConsistencyLevel(array $options): ?string
    {
        $level = (string) ($options['consistency_level'] ?? $this->consistencyLevel);
        $trimmed = trim($level);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return list<string>
     */
    private function resolveProperties(?array $overrides): array
    {
        if ($overrides === null) {
            return $this->defaultProperties;
        }

        return $this->sanitizeProperties($overrides) ?: $this->defaultProperties;
    }

    /**
     * @param list<string> $properties
     *
     * @return list<string>
     */
    private function sanitizeProperties(array $properties): array
    {
        $sanitized = [];

        foreach ($properties as $property) {
            if (!is_string($property)) {
                continue;
            }

            $clean = trim($property);
            if ($clean === '') {
                continue;
            }

            $slug = (string) preg_replace('/[^A-Za-z0-9_]/', '', $clean);
            if ($slug === '' || in_array($slug, $sanitized, true)) {
                continue;
            }

            $sanitized[] = $slug;
        }

        return $sanitized === [] ? ['text'] : $sanitized;
    }

    private function resolveClass(VectorNamespace $namespace): string
    {
        return $namespace->getCollection() ?? $this->className;
    }

    private function resolveTenant(VectorNamespace $namespace, array $options): ?string
    {
        $tenant = $options['tenant'] ?? null;
        if (is_string($tenant) && trim($tenant) !== '') {
            return trim($tenant);
        }

        $metadata = $namespace->getMetadata();
        $candidate = $metadata['tenant'] ?? null;

        return is_string($candidate) && trim($candidate) !== '' ? trim($candidate) : null;
    }

    private function buildGraphqlQuery(string $class, int $limit, array $properties, bool $includeVectors): string
    {
        $propertySelection = '';
        foreach ($properties as $property) {
            $propertySelection .= '      ' . $property . PHP_EOL;
        }

        $additionalFields = ['id', 'score', 'distance', 'certainty'];
        if ($includeVectors) {
            $additionalFields[] = 'vector';
        }

        $additionalSelection = implode(PHP_EOL, array_values(array_unique($additionalFields)));

        return sprintf(
            <<<'GQL'
query($vector: [Float!], $where: WhereFilter, $tenant: String, $consistency: ConsistencyLevel) {
  Get {
    %s(
      limit: %d
      nearVector: { vector: $vector }
      where: $where
      tenant: $tenant
      consistencyLevel: $consistency
    ) {
%s      _additional {
        %s
      }
    }
  }
}
GQL,
            $class,
            $limit,
            $propertySelection,
            $additionalSelection
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeGraphqlResults(array $results, bool $includeVectors): array
    {
        $chunks = [];

        foreach ($results as $item) {
            if (!is_array($item)) {
                continue;
            }

            $additional = is_array($item['_additional'] ?? null) ? $item['_additional'] : [];
            unset($item['_additional']);

            $properties = $item;
            $text = $this->extractText($properties);
            if ($text === null) {
                continue;
            }

            $chunk = ['text' => $text];

            $score = $this->resolveScore($additional);
            if ($score !== null) {
                $chunk['score'] = $score;
            }

            if (isset($additional['id']) && is_string($additional['id']) && trim($additional['id']) !== '') {
                $chunk['document_id'] = trim($additional['id']);
            }

            $documentName = $properties['document_name'] ?? $properties['title'] ?? null;
            if (is_string($documentName) && trim($documentName) !== '') {
                $chunk['document_name'] = trim($documentName);
                unset($properties['document_name'], $properties['title']);
            } else {
                unset($properties['document_name'], $properties['title']);
            }

            if ($includeVectors && isset($additional['vector']) && is_array($additional['vector'])) {
                $chunk['vector'] = array_map(
                    static fn ($value): float => (float) $value,
                    array_values($additional['vector'])
                );
            }

            if ($properties !== []) {
                $chunk['metadata'] = $properties;
            }

            $chunks[] = $chunk;
        }

        return $chunks;
    }

    private function extractText(array &$properties): ?string
    {
        foreach (['text', 'content', 'body'] as $key) {
            if (isset($properties[$key]) && is_string($properties[$key])) {
                $text = trim($properties[$key]);
                unset($properties[$key]);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $metadata
     *
     * @return array<string, mixed>|null
     */
    private function buildMetadataFilter(VectorNamespace $namespace): ?array
    {
        $metadata = $namespace->getMetadata();
        unset($metadata['tenant']);

        if ($metadata === []) {
            return null;
        }

        $operands = [];
        foreach ($metadata as $key => $value) {
            if (is_array($value)) {
                if ($value === [] || !array_is_list($value)) {
                    continue;
                }

                $operands[] = [
                    'path' => [$key],
                    'operator' => 'ContainsAny',
                ] + $this->buildArrayValue($value);
                continue;
            }

            $operands[] = [
                'path' => [$key],
                'operator' => 'Equal',
            ] + $this->buildScalarValue($value);
        }

        if ($operands === []) {
            return null;
        }

        return ['operator' => 'And', 'operands' => $operands];
    }

    /**
     * @param list<string|int|float|bool> $values
     *
     * @return array<string, list<string|int|float|bool>>
     */
    private function buildArrayValue(array $values): array
    {
        $first = $values[0];

        if (is_int($first)) {
            return ['valueIntArray' => array_map(static fn (int $v): int => $v, $values)];
        }

        if (is_float($first)) {
            return ['valueNumberArray' => array_map(static fn (float $v): float => $v, $values)];
        }

        if (is_bool($first)) {
            return ['valueBooleanArray' => array_map(static fn (bool $v): bool => $v, $values)];
        }

        return ['valueStringArray' => array_map(static fn ($v): string => (string) $v, $values)];
    }

    /**
     * @return array<string, string|int|float|bool>
     */
    private function buildScalarValue(string|int|float|bool $value): array
    {
        if (is_int($value)) {
            return ['valueInt' => $value];
        }

        if (is_float($value)) {
            return ['valueNumber' => $value];
        }

        if (is_bool($value)) {
            return ['valueBoolean' => $value];
        }

        return ['valueText' => $value];
    }

    private function resolveScore(array $additional): ?float
    {
        foreach (['score', 'certainty'] as $key) {
            if (isset($additional[$key]) && is_numeric($additional[$key])) {
                return (float) $additional[$key];
            }
        }

        if (isset($additional['distance']) && is_numeric($additional['distance'])) {
            $distance = (float) $additional['distance'];

            return max(0.0, 1 - $distance);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $query
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $uri, array $payload, array $query, array $headers): array
    {
        $options = [
            'headers' => $headers,
        ];

        if ($payload !== []) {
            $options['json'] = $payload;
        }

        if ($query !== []) {
            $options['query'] = $query;
        }

        try {
            $response = $this->httpClient->request($method, $uri, $options);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf('Weaviate request to "%s" failed: %s', $uri, $exception->getMessage()),
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

    /**
     * @return array<string, string>
     */
    private function buildHeaders(?string $tenant): array
    {
        $headers = [
            'Accept' => 'application/json',
        ];

        if ($this->apiKey !== null && trim($this->apiKey) !== '') {
            $headers['Authorization'] = 'Bearer ' . trim($this->apiKey);
        }

        if ($tenant !== null) {
            $headers['X-Weaviate-Tenant'] = $tenant;
        }

        return $headers;
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        $trimmed = trim($baseUrl);
        if ($trimmed === '') {
            throw new RuntimeException('Weaviate base URL cannot be empty.');
        }

        $normalized = str_ends_with($trimmed, '/') ? $trimmed : $trimmed . '/';
        if (preg_match('#/v1/?$#', $normalized) === 1) {
            return str_ends_with($normalized, '/') ? $normalized : $normalized . '/';
        }

        return $normalized . 'v1/';
    }
}
