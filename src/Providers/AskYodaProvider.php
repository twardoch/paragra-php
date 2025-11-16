<?php

declare(strict_types=1);

// this_file: paragra-php/src/Providers/AskYodaProvider.php

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
use function is_array;
use function is_string;
use function json_decode;
use function sprintf;
use function trim;

final class AskYodaProvider extends AbstractProvider
{
    private const BASE_URI = 'https://api.edenai.run';

    private ClientInterface $httpClient;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        ProviderSpec $spec,
        ?ClientInterface $httpClient = null,
        private readonly array $config = [],
    ) {
        parent::__construct($spec, $config['capabilities'] ?? ['retrieval', 'llm_generation']);
        $this->httpClient = $httpClient ?? new HttpClient([
            'base_uri' => self::BASE_URI,
            'timeout' => 30,
        ]);
    }

    #[\Override]
    public function retrieve(string $query, array $options = []): UnifiedResponse
    {
        $clean = $this->sanitizeQuery($query);
        $payload = $this->buildPayload($clean, $options);
        $endpoint = sprintf('/v2/aiproducts/askyoda/v2/%s/ask_llm/', $this->getProjectId());

        $response = $this->httpClient->request('POST', $endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getApiKey(),
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $chunks = $this->normalizeChunks($data);

        $usage = $this->extractUsage($data['usage'] ?? null);
        $cost = isset($data['cost'])
            ? [
                'amount' => (float) $data['cost'],
                'currency' => $this->config['currency'] ?? 'USD',
            ]
            : null;

        $metadata = array_filter(array_merge(
            $this->baseMetadata(),
            [
                'solution_type' => 'askyoda',
                'answer' => $data['result'] ?? null,
                'llm_provider' => $data['llm_provider'] ?? null,
                'llm_model' => $data['llm_model'] ?? null,
            ],
            $this->config['metadata'] ?? []
        ));

        return UnifiedResponse::fromChunks(
            provider: $this->getProvider(),
            model: $this->getModel(),
            chunks: $chunks,
            metadata: $metadata,
            usage: $usage,
            cost: $cost,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(string $query, array $options): array
    {
        $defaults = $this->config['default_options'] ?? [];
        $merged = array_merge($defaults, $options);

        $payload = [
            'query' => $query,
            'k' => $merged['k'] ?? 10,
            'min_score' => $merged['min_score'] ?? 0.3,
            'temperature' => $merged['temperature'] ?? 0.99,
            'max_tokens' => $merged['max_tokens'] ?? 8000,
            'include_payload' => $merged['include_payload'] ?? true,
        ];

        $llmConfig = array_merge(
            $this->config['llm'] ?? [],
            $merged['llm'] ?? []
        );

        if (isset($llmConfig['provider'])) {
            $payload['llm_provider'] = $llmConfig['provider'];
        }

        if (isset($llmConfig['model'])) {
            $payload['llm_model'] = $llmConfig['model'];
        }

        if (isset($merged['system_prompt'])) {
            $payload['system_prompt'] = $merged['system_prompt'];
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeChunks(array $data): array
    {
        $chunks = $data['chunks'] ?? [];
        if (!is_array($chunks) || $chunks === []) {
            return $this->buildChunkPlaceholders($data['chunks_ids'] ?? []);
        }

        return array_values(array_filter(array_map(
            function (array $chunk): ?array {
                $text = $this->extractChunkText($chunk);
                if ($text === null) {
                    return null;
                }

                $normalized = [
                    'text' => $text,
                ];

                if (isset($chunk['score'])) {
                    $normalized['score'] = (float) $chunk['score'];
                }

                if (isset($chunk['chunk_id'])) {
                    $normalized['document_id'] = (string) $chunk['chunk_id'];
                }

                if (isset($chunk['metadata']) && is_array($chunk['metadata'])) {
                    $normalized['metadata'] = $chunk['metadata'];
                }

                return $normalized;
            },
            $chunks
        )));
    }

    /**
     * @param array<int, string> $chunkIds
     *
     * @return list<array<string, string>>
     */
    private function buildChunkPlaceholders(array $chunkIds): array
    {
        $chunkIds = array_filter($chunkIds, static fn ($id): bool => is_string($id) && trim($id) !== '');

        return array_values(array_map(
            function (string $id): array {
                $cleanId = trim($id);

                return [
                    'text' => sprintf('Chunk %s', $cleanId),
                    'metadata' => ['chunk_id' => $cleanId],
                ];
            },
            $chunkIds
        ));
    }

    private function extractChunkText(array $chunk): ?string
    {
        $payload = $chunk['payload'] ?? $chunk['chunk'] ?? null;
        if (is_string($payload)) {
            $payload = ['text' => $payload];
        }

        if (is_array($payload)) {
            $text = $payload['text']
                ?? $payload['content']
                ?? null;
            if (is_string($text)) {
                $clean = trim($text);
                return $clean === '' ? null : $clean;
            }
        }

        if (isset($chunk['chunk_text']) && is_string($chunk['chunk_text'])) {
            $clean = trim($chunk['chunk_text']);
            return $clean === '' ? null : $clean;
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $usage
     *
     * @return array<string, int>|null
     */
    private function extractUsage(?array $usage): ?array
    {
        if ($usage === null) {
            return null;
        }

        $input = isset($usage['input_tokens']) ? (int) $usage['input_tokens'] : null;
        $output = isset($usage['output_tokens']) ? (int) $usage['output_tokens'] : null;
        $total = isset($usage['total_tokens']) ? (int) $usage['total_tokens'] : null;

        return array_filter([
            'input_tokens' => $input,
            'output_tokens' => $output,
            'total_tokens' => $total,
        ], static fn ($value) => $value !== null);
    }

    private function getApiKey(): string
    {
        $solution = $this->getSolution();
        $key = $this->config['askyoda_api_key']
            ?? $solution['askyoda_api_key']
            ?? $solution['api_key']
            ?? null;

        if (!is_string($key) || trim($key) === '') {
            throw new RuntimeException('AskYoda API key is missing.');
        }

        return $key;
    }

    private function getProjectId(): string
    {
        $solution = $this->getSolution();
        $projectId = $this->config['project_id'] ?? $solution['project_id'] ?? null;
        if (!is_string($projectId) || trim($projectId) === '') {
            throw new RuntimeException('AskYoda project_id is missing.');
        }

        return trim($projectId);
    }
}
