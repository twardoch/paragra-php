<?php

declare(strict_types=1);

// this_file: paragra-php/src/ExternalSearch/TwatSearchRetriever.php

namespace ParaGra\ExternalSearch;

use JsonException;
use ParaGra\Response\UnifiedResponse;
use Symfony\Component\Process\Process;

use function array_filter;
use function array_map;
use function array_shift;
use function array_unique;
use function array_values;
use function count;
use function explode;
use function implode;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function microtime;
use function round;
use function sha1;
use function sprintf;
use function str_replace;
use function strtolower;
use function strrpos;
use function strpos;
use function substr;
use function trim;
use function time;
use function usleep;

use const JSON_THROW_ON_ERROR;

/**
 * Executes the `twat-search` CLI (`twat-search web q --json ...`) to fetch rich
 * snippets from engines such as Brave or DuckDuckGo, then converts the results
 * into `UnifiedResponse` chunks that ParaGra can feed into prompt builders.
 */
final class TwatSearchRetriever implements ExternalSearchRetrieverInterface
{
    private const PROVIDER = 'twat-search';
    private const MODEL = 'twat-search-cli';
    private const DEFAULT_BINARY = 'twat-search';
    private const DEFAULT_TIMEOUT = 30.0;
    private const DEFAULT_MAX_ATTEMPTS = 2;
    private const DEFAULT_RETRY_DELAY_MS = 250;
    private const DEFAULT_CACHE_TTL = 120;
    private const DEFAULT_CACHE_LIMIT = 32;
    private const DEFAULT_NUM_RESULTS = 5;
    private const DEFAULT_MAX_RESULTS = 8;

    /**
     * @var array<string, array{
     *     chunks: list<array<string, mixed>>,
     *     metadata: array<string, mixed>,
     *     cached_at: int,
     *     expires_at: int
     * }>
     */
    private array $cache = [];

    /**
     * FIFO list of cache keys for eviction.
     *
     * @var list<string>
     */
    private array $cacheOrder = [];

    /**
     * @var callable|null
     */
    private readonly $processRunner;

    /**
     * @param list<string> $defaultEngines
     * @param array<string, string> $environment
     * @param callable|null $processRunner Custom runner hook for tests.
     */
    public function __construct(
        private readonly string $binary = self::DEFAULT_BINARY,
        private readonly array $defaultEngines = [],
        private readonly int $defaultNumResults = self::DEFAULT_NUM_RESULTS,
        private readonly int $defaultMaxResults = self::DEFAULT_MAX_RESULTS,
        private readonly int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        private readonly int $retryDelayMs = self::DEFAULT_RETRY_DELAY_MS,
        private readonly float $timeoutSeconds = self::DEFAULT_TIMEOUT,
        private readonly int $cacheTtlSeconds = self::DEFAULT_CACHE_TTL,
        private readonly int $cacheLimit = self::DEFAULT_CACHE_LIMIT,
        private readonly array $environment = [],
        ?callable $processRunner = null,
    ) {
        if ($this->maxAttempts < 1) {
            throw new ExternalSearchException('maxAttempts must be at least 1.');
        }

        if ($this->defaultNumResults < 1) {
            throw new ExternalSearchException('defaultNumResults must be positive.');
        }

        if ($this->defaultMaxResults < 1) {
            throw new ExternalSearchException('defaultMaxResults must be positive.');
        }

        if ($this->cacheLimit < 1) {
            throw new ExternalSearchException('cacheLimit must be at least 1 entry.');
        }

        $this->processRunner = $processRunner;
    }

    #[\Override]
    public function getProvider(): string
    {
        return self::PROVIDER;
    }

    #[\Override]
    public function getModel(): string
    {
        return self::MODEL;
    }

    /**
     * @param array<string, mixed> $options
     */
    #[\Override]
    public function search(string $query, array $options = []): UnifiedResponse
    {
        $cleanQuery = $this->sanitizeQuery($query);
        $engines = $this->resolveEngines($options['engines'] ?? null);
        $numResults = $this->resolveNumResults($options['num_results'] ?? $options['count'] ?? null);
        $maxResults = $this->resolveMaxResults($options['max_results'] ?? null);
        $allowCache = (bool) ($options['allow_cache'] ?? true);
        $cacheTtl = $this->resolveCacheTtl($options['cache_ttl'] ?? null);
        $timeout = $this->resolveTimeout($options['timeout'] ?? null);
        $environment = array_merge($this->environment, is_array($options['env'] ?? null) ? $options['env'] : []);
        $cacheKey = $this->buildCacheKey($cleanQuery, $engines, $numResults, $maxResults);

        if ($allowCache) {
            $cached = $this->readCache($cacheKey);
            if ($cached !== null) {
                return UnifiedResponse::fromChunks(
                    provider: $this->getProvider(),
                    model: $this->getModel(),
                    chunks: $cached['chunks'],
                    metadata: array_merge(
                        $cached['metadata'],
                        [
                            'cache_hit' => true,
                            'cached_at' => $cached['cached_at'],
                        ]
                    )
                );
            }
        }

        $attempt = 0;
        $lastError = null;

        while ($attempt < $this->maxAttempts) {
            $attempt++;
            $start = microtime(true);

            try {
                $rawResults = $this->runQuery($cleanQuery, $engines, $numResults, $timeout, $environment);
                $chunks = $this->normalizeChunks($rawResults, $maxResults);

                $metadata = [
                    'binary' => $this->binary,
                    'engines' => $engines,
                    'num_results' => $numResults,
                    'max_results' => $maxResults,
                    'result_count' => count($chunks),
                    'retry_count' => $attempt,
                    'duration_ms' => (int) round((microtime(true) - $start) * 1000),
                    'cache_hit' => false,
                ];

                if ($allowCache) {
                    $this->storeCache($cacheKey, $chunks, $metadata, $cacheTtl);
                }

                return UnifiedResponse::fromChunks(
                    provider: $this->getProvider(),
                    model: $this->getModel(),
                    chunks: $chunks,
                    metadata: $metadata,
                );
            } catch (ExternalSearchException $exception) {
                $lastError = $exception;
                if ($attempt >= $this->maxAttempts) {
                    throw $exception;
                }

                usleep($this->retryDelayMs * 1000 * $attempt);
            }
        }

        throw $lastError ?? new ExternalSearchException('twat-search failed unexpectedly.');
    }

    /**
     * @param list<string> $engines
     * @param array<string, string> $environment
     *
     * @return list<array<string, mixed>>
     */
    private function runQuery(
        string $query,
        array $engines,
        int $numResults,
        float $timeout,
        array $environment
    ): array {
        $command = $this->buildCommand($query, $engines, $numResults);
        $result = $this->executeProcess($command, $environment, $timeout);

        if ($result['exit_code'] !== 0) {
            $stderr = trim($result['stderr']);
            $hint = $stderr !== '' ? $stderr : 'unknown error';

            throw new ExternalSearchException(sprintf(
                'twat-search exited with code %d: %s',
                $result['exit_code'],
                $hint
            ));
        }

        $payload = $this->extractJsonPayload($result['stdout']);

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ExternalSearchException(
                'Unable to decode twat-search JSON output: ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        if (!is_array($decoded)) {
            throw new ExternalSearchException('twat-search JSON response must be an array.');
        }

        /** @var list<array<string, mixed>> $results */
        $results = array_values(array_filter(
            $decoded,
            static fn(mixed $value): bool => is_array($value)
        ));

        return $results;
    }

    /**
     * @param list<string> $engines
     *
     * @return list<string>
     */
    private function normalizeEngines(array $engines): array
    {
        $normalized = array_map(
            static fn(string $engine): string => strtolower(trim($engine)),
            $engines
        );

        return array_values(array_filter(
            array_unique($normalized),
            static fn(string $engine): bool => $engine !== ''
        ));
    }

    /**
     * @return list<string>
     */
    private function resolveEngines(mixed $engines): array
    {
        if (is_string($engines)) {
            $candidates = explode(',', str_replace(' ', '', $engines));
            return $this->normalizeEngines($candidates);
        }

        if (is_array($engines)) {
            return $this->normalizeEngines(array_map(
                static fn(mixed $value): string => (string) $value,
                $engines
            ));
        }

        return $this->normalizeEngines($this->defaultEngines);
    }

    private function resolveNumResults(mixed $candidate): int
    {
        $value = (int) ($candidate ?? $this->defaultNumResults);

        return $value > 0 ? $value : $this->defaultNumResults;
    }

    private function resolveMaxResults(mixed $candidate): int
    {
        $value = (int) ($candidate ?? $this->defaultMaxResults);

        return $value > 0 ? $value : $this->defaultMaxResults;
    }

    private function resolveCacheTtl(mixed $candidate): int
    {
        $value = (int) ($candidate ?? $this->cacheTtlSeconds);

        return $value > 0 ? $value : $this->cacheTtlSeconds;
    }

    private function resolveTimeout(mixed $candidate): float
    {
        $value = (float) ($candidate ?? $this->timeoutSeconds);

        return $value > 0 ? $value : $this->timeoutSeconds;
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $environment
     *
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    private function executeProcess(array $command, array $environment, float $timeout): array
    {
        if ($this->processRunner !== null) {
            $result = ($this->processRunner)($command, $environment, $timeout);

            return [
                'exit_code' => (int) ($result['exit_code'] ?? 1),
                'stdout' => (string) ($result['stdout'] ?? ''),
                'stderr' => (string) ($result['stderr'] ?? ''),
            ];
        }

        $process = new Process($command, null, $environment, null, $timeout);
        $process->run();

        return [
            'exit_code' => $process->getExitCode() ?? 1,
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput(),
        ];
    }

    /**
     * @param list<string> $engines
     *
     * @return list<string>
     */
    private function buildCommand(string $query, array $engines, int $numResults): array
    {
        $command = [$this->binary, 'web', 'q', $query, '--json'];

        if ($engines !== []) {
            $command[] = '-e';
            $command[] = implode(',', $engines);
        }

        $command[] = '--num_results';
        $command[] = (string) $numResults;

        return $command;
    }

    private function extractJsonPayload(string $stdout): string
    {
        $trimmed = trim($stdout);
        if ($trimmed === '') {
            throw new ExternalSearchException('twat-search returned empty output.');
        }

        if ($trimmed[0] === '[') {
            return $trimmed;
        }

        $start = strpos($trimmed, '[');
        $end = strrpos($trimmed, ']');

        if ($start === false || $end === false || $end <= $start) {
            throw new ExternalSearchException('Failed to locate JSON array in twat-search output.');
        }

        return substr($trimmed, (int) $start, (int) ($end - $start + 1));
    }

    /**
     * @param list<array<string, mixed>> $results
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeChunks(array $results, int $maxResults): array
    {
        $chunks = [];

        foreach ($results as $result) {
            $title = $this->stringOrNull($result['title'] ?? null);
            $snippet = $this->stringOrNull($result['snippet'] ?? null);
            $url = $this->stringOrNull($result['url'] ?? null);

            $textParts = array_filter([$title, $snippet], static fn(?string $value): bool => $value !== null && $value !== '');
            $text = trim(implode("\n\n", $textParts));

            if ($text === '') {
                continue;
            }

            $chunk = [
                'text' => $text,
            ];

            $score = $result['score'] ?? null;
            if (is_numeric($score)) {
                $chunk['score'] = (float) $score;
            }

            if ($url !== null) {
                $chunk['document_id'] = $url;
            }

            if ($title !== null) {
                $chunk['document_name'] = $title;
            }

            $metadata = [
                'engine' => $this->stringOrNull($result['source_engine'] ?? null),
                'url' => $url,
                'title' => $title,
                'snippet' => $snippet,
                'position' => isset($result['position']) && is_numeric($result['position'])
                    ? (int) $result['position']
                    : null,
                'timestamp' => $this->stringOrNull($result['timestamp'] ?? null),
            ];

            if (isset($result['extra_info']) && is_array($result['extra_info']) && $result['extra_info'] !== []) {
                $metadata['extra_info'] = $result['extra_info'];
            }

            if (isset($result['raw']) && is_array($result['raw']) && $result['raw'] !== []) {
                $metadata['raw'] = $result['raw'];
            }

            $metadata = array_filter(
                $metadata,
                static fn(mixed $value): bool => $value !== null && $value !== []
            );

            if ($metadata !== []) {
                $chunk['metadata'] = $metadata;
            }

            $chunks[] = $chunk;

            if (count($chunks) >= $maxResults) {
                break;
            }
        }

        return $chunks;
    }

    private function sanitizeQuery(string $query): string
    {
        $clean = trim($query);
        if ($clean === '') {
            throw new ExternalSearchException('Query cannot be empty.');
        }

        return $clean;
    }

    /**
     * @return array{
     *     chunks: list<array<string, mixed>>,
     *     metadata: array<string, mixed>,
     *     cached_at: int,
     *     expires_at: int
     * }|null
     */
    private function readCache(string $key): ?array
    {
        if (!isset($this->cache[$key])) {
            return null;
        }

        $entry = $this->cache[$key];
        if ($entry['expires_at'] < time()) {
            unset($this->cache[$key]);

            return null;
        }

        return $entry;
    }

    /**
     * @param list<array<string, mixed>> $chunks
     * @param array<string, mixed> $metadata
     */
    private function storeCache(string $key, array $chunks, array $metadata, int $cacheTtl): void
    {
        $now = time();

        $this->cache[$key] = [
            'chunks' => $chunks,
            'metadata' => $metadata,
            'cached_at' => $now,
            'expires_at' => $now + $cacheTtl,
        ];

        $this->cacheOrder[] = $key;

        if (count($this->cacheOrder) > $this->cacheLimit) {
            $evictedKey = array_shift($this->cacheOrder);
            if ($evictedKey !== null && $evictedKey !== $key) {
                unset($this->cache[$evictedKey]);
            }
        }
    }

    /**
     * @param list<string> $engines
     */
    private function buildCacheKey(string $query, array $engines, int $numResults, int $maxResults): string
    {
        $normalized = strtolower(trim($query));

        return sha1($normalized . '|' . implode(',', $engines) . '|' . $numResults . '|' . $maxResults);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $clean = trim($value);

        return $clean === '' ? null : $clean;
    }
}
