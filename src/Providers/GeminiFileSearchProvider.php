<?php

declare(strict_types=1);

// this_file: paragra-php/src/Providers/GeminiFileSearchProvider.php

namespace ParaGra\Providers;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface;
use ParaGra\Config\ProviderSpec;
use ParaGra\Response\UnifiedResponse;
use RuntimeException;
use const JSON_THROW_ON_ERROR;

use function array_filter;
use function array_map;
use function array_merge;
use function array_values;
use function count;
use function implode;
use function is_array;
use function is_string;
use function json_decode;
use function ltrim;
use function rawurlencode;
use function sprintf;
use function str_starts_with;
use function trim;

final class GeminiFileSearchProvider extends AbstractProvider
{
    private ClientInterface $httpClient;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        ProviderSpec $spec,
        ?ClientInterface $httpClient = null,
        private readonly array $config = [],
    ) {
        parent::__construct($spec, $config['capabilities'] ?? ['retrieval']);
        $this->httpClient = $httpClient ?? new HttpClient([
            'base_uri' => 'https://generativelanguage.googleapis.com',
            'timeout' => 30,
        ]);
    }

    #[\Override]
    public function retrieve(string $query, array $options = []): UnifiedResponse
    {
        $clean = $this->sanitizeQuery($query);
        $payload = $this->buildPayload($clean, $options);

        $response = $this->httpClient->request('POST', $this->buildEndpoint(), [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $chunks = $this->extractChunks($data);

        $metadata = array_filter(array_merge(
            $this->baseMetadata(),
            [
                'solution_type' => 'gemini-file-search',
                'answer' => $data['candidates'][0]['content']['parts'][0]['text'] ?? null,
                'source_count' => count($chunks),
            ],
            $this->config['metadata'] ?? []
        ));

        return UnifiedResponse::fromChunks(
            provider: $this->getProvider(),
            model: $this->getModel(),
            chunks: $chunks,
            metadata: $metadata,
            usage: $this->extractUsage($data['usageMetadata'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(string $query, array $options): array
    {
        $vectorStoreName = $this->resolveVectorStoreName(
            $this->config['vector_store']
            ?? $this->getSolution()['vector_store']
            ?? null
        );

        $generation = array_merge(
            $this->config['generation'] ?? [],
            $options['generation'] ?? []
        );

        return [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $query],
                    ],
                ],
            ],
            'tools' => [
                ['fileSearch' => new \stdClass()],
            ],
            'toolConfig' => [
                'fileSearch' => [
                    'vectorStore' => [
                        ['name' => $vectorStoreName],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => $generation['temperature'] ?? 0.2,
                'maxOutputTokens' => $generation['max_output_tokens'] ?? 1024,
            ],
            'safetySettings' => $this->config['safety'] ?? [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractChunks(array $data): array
    {
        $entries = $data['candidates'][0]['groundingMetadata']['searchEntries'] ?? [];
        if (!is_array($entries) || $entries === []) {
            return [];
        }

        return array_values(array_filter(array_map(
            function (array $entry): ?array {
                $chunk = $entry['chunk'] ?? [];
                $parts = $chunk['content']['parts'] ?? [];
                $textParts = array_filter(array_map(
                    static fn (array $part): ?string => isset($part['text']) ? trim((string) $part['text']) : null,
                    is_array($parts) ? $parts : []
                ));
                $text = trim(implode(' ', $textParts));
                if ($text === '') {
                    return null;
                }

                $normalized = [
                    'text' => $text,
                ];

                $score = $entry['score']
                    ?? $chunk['relevanceScore']
                    ?? null;
                if ($score !== null) {
                    $normalized['score'] = (float) $score;
                }

                $metadata = array_filter([
                    'chunk_id' => $chunk['chunkId'] ?? null,
                    'source' => $chunk['source'] ?? null,
                    'uri' => $entry['uri'] ?? null,
                    'title' => $entry['title'] ?? null,
                ], static fn ($value): bool => $value !== null && $value !== '');

                if ($metadata !== []) {
                    $normalized['metadata'] = $metadata;
                }

                return $normalized;
            },
            $entries
        )));
    }

    /**
     * @param array<string, int>|null $usage
     *
     * @return array<string, int>|null
     */
    private function extractUsage(?array $usage): ?array
    {
        if ($usage === null) {
            return null;
        }

        return array_filter([
            'prompt_tokens' => $usage['promptTokenCount'] ?? null,
            'output_tokens' => $usage['candidatesTokenCount'] ?? null,
            'total_tokens' => $usage['totalTokenCount'] ?? null,
        ], static fn ($value): bool => $value !== null);
    }

    private function buildEndpoint(): string
    {
        $apiKey = $this->apiKey();

        return sprintf(
            '/v1beta/models/%s:generateContent?key=%s',
            rawurlencode($this->getModel()),
            rawurlencode($apiKey)
        );
    }

    private function apiKey(): string
    {
        $key = $this->config['google_api_key'] ?? $this->getSolution()['google_api_key'] ?? $this->getSolution()['api_key'] ?? $this->config['api_key'] ?? null;
        if (!is_string($key) || trim($key) === '') {
            throw new RuntimeException('A Gemini API key is required.');
        }

        return trim($key);
    }

    /**
     * @param array<string, mixed>|string|null $vectorStore
     */
    private function resolveVectorStoreName(array|string|null $vectorStore): string
    {
        if ($vectorStore === null) {
            throw new RuntimeException('Gemini File Search requires a "vector_store" entry.');
        }

        if (is_string($vectorStore)) {
            return $this->normalizeVectorStoreName($vectorStore);
        }

        if (!is_array($vectorStore)) {
            throw new RuntimeException('Gemini File Search vector_store must be a string or array.');
        }

        $candidates = [
            $vectorStore['name'] ?? null,
            $vectorStore['resource'] ?? null,
            $vectorStore['datastore'] ?? null,
            $vectorStore['corpus'] ?? null,
            $vectorStore['vector_store'] ?? null,
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && trim($value) !== '') {
                return $this->normalizeVectorStoreName($value);
            }
        }

        throw new RuntimeException(
            'Gemini File Search vector_store must include a "name", "datastore", or "corpus" value.'
        );
    }

    private function normalizeVectorStoreName(string $value): string
    {
        $clean = trim($value);
        if ($clean === '') {
            throw new RuntimeException('Gemini File Search vector_store cannot be empty.');
        }

        $clean = ltrim($clean, '/');

        if (
            str_starts_with($clean, 'fileSearchStores/')
            || str_starts_with($clean, 'corpora/')
            || str_starts_with($clean, 'projects/')
        ) {
            return $clean;
        }

        return sprintf('fileSearchStores/%s', $clean);
    }
}
