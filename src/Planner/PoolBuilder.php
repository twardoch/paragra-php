<?php

declare(strict_types=1);

// this_file: paragra-php/src/Planner/PoolBuilder.php

namespace ParaGra\Planner;

use ParaGra\ProviderCatalog\ProviderDiscovery;
use ParaGra\ProviderCatalog\ProviderSummary;
use RuntimeException;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_replace_recursive;
use function array_values;
use function explode;
use function getenv;
use function is_array;
use function is_string;
use function sprintf;
use function trim;

/**
 * Scenario-aware builder that assembles ParaGra priority pools from catalog data.
 */
final class PoolBuilder
{
    public const PRESET_FREE = 'free-tier';
    public const PRESET_HYBRID = 'hybrid';
    public const PRESET_HOSTED = 'hosted';

    /**
     * @param array<string, string> $environment
     */
    public function __construct(
        private readonly ProviderDiscovery $catalog,
        private readonly array $environment = [],
    ) {
    }

    public static function fromGlobals(ProviderDiscovery $catalog): self
    {
        return new self($catalog);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function build(string $preset, array $options = []): array
    {
        return match ($preset) {
            self::PRESET_FREE => $this->buildFreeTier($options),
            self::PRESET_HYBRID => $this->buildHybrid($options),
            self::PRESET_HOSTED => $this->buildHosted($options),
            default => throw new RuntimeException(sprintf('Unknown pool preset "%s".', $preset)),
        };
    }

    /**
     * @param array<string, mixed> $options
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function buildFreeTier(array $options): array
    {
        $ragieKey = $this->requireEnv('RAGIE_API_KEY');
        $defaults = $this->extractArrayOption($options, 'ragie_defaults');
        $partition = $this->resolvePartition($options);

        $pools = [];

        if ($cerebras = $this->buildCerebrasRotation($ragieKey, $defaults, $partition, $options)) {
            $pools[] = $cerebras;
        }

        $pools[] = $this->buildFreeRotation($ragieKey, $defaults, $partition, $options);

        return $pools;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function buildHybrid(array $options): array
    {
        $ragieKey = $this->requireEnv('RAGIE_API_KEY');
        $defaults = $this->extractArrayOption($options, 'ragie_defaults');
        $partition = $this->resolvePartition($options);

        $pools = [];

        if ($cerebras = $this->buildCerebrasRotation($ragieKey, $defaults, $partition, $options)) {
            $pools[] = $cerebras;
        }

        $pools[] = $this->buildFreeRotation($ragieKey, $defaults, $partition, $options);

        $pools[] = [
            $this->ragieCatalogEntry(
                slug: 'openai',
                apiKey: $this->requireEnv('OPENAI_API_KEY'),
                model: $this->resolveModel($options, 'openai_model', 'PARAGRA_OPENAI_MODEL', 'gpt-4o-mini', 'OPENAI_MODEL'),
                ragieKey: $ragieKey,
                partition: $partition,
                defaults: $defaults,
                metadata: [
                    'plan' => self::PRESET_HYBRID,
                    'tier' => 'paid',
                    'vector_store' => 'pinecone-starter',
                    'embedding_provider' => 'voyage-embeddings',
                ]
            ),
        ];

        return $pools;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function buildHosted(array $options): array
    {
        $askyodaKey = $this->requireEnv('EDENAI_API_KEY');
        $projectId = $this->requireEnv('EDENAI_ASKYODA_PROJECT');
        $defaults = $this->extractArrayOption($options, 'askyoda_options');
        $llm = $this->extractArrayOption($options, 'askyoda_llm');
        $askyodaInsight = $this->summarizeInsight('askyoda', 'eden-askyoda');

        $metadata = [
            'plan' => self::PRESET_HOSTED,
            'tier' => 'hosted',
            'latency_tier' => 'hosted',
            'insight' => $askyodaInsight,
            'hosted_recommendations' => [
                $this->summarizeInsight('vectara'),
                $this->summarizeInsight('bedrock-kb'),
            ],
        ];

        return [[
            [
                'catalog' => [
                    'slug' => 'askyoda',
                    'model_type' => 'generation',
                    'overrides' => [
                        'api_key' => $askyodaKey,
                        'solution' => [
                            'type' => 'askyoda',
                            'askyoda_api_key' => $askyodaKey,
                            'project_id' => $projectId,
                            'default_options' => $defaults,
                            'llm' => $llm,
                            'metadata' => $metadata,
                        ],
                    ],
                ],
            ],
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function buildCerebrasRotation(
        string $ragieKey,
        array $defaults,
        string $partition,
        array $options
    ): ?array {
        $keys = $this->envList('CEREBRAS_API_KEYS', 'CEREBRAS_API_KEY');
        if ($keys === []) {
            return null;
        }

        $model = $this->resolveModel($options, 'cerebras_model', 'CEREBRAS_MODEL', 'llama-3.3-70b');
        $pool = [];

        foreach ($keys as $index => $apiKey) {
            $pool[] = $this->ragieCatalogEntry(
                slug: 'cerebras',
                apiKey: $apiKey,
                model: $model,
                ragieKey: $ragieKey,
                partition: $partition,
                defaults: $defaults,
                metadata: [
                    'plan' => self::PRESET_FREE,
                    'tier' => 'free',
                    'slot' => $index + 1,
                    'embedding_provider' => 'google-gemini-embedding',
                    'vector_store' => 'qdrant-cloud-free',
                ]
            );
        }

        return $pool;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<int, array<string, mixed>>
     */
    private function buildFreeRotation(string $ragieKey, array $defaults, string $partition, array $options): array
    {
        $geminiInsight = $this->summarizeInsight('gemini', 'google-gemini-file-search');
        $vectorStore = $this->resolveGeminiVectorStore($options);

        return [
            $this->ragieCatalogEntry(
                slug: 'gemini',
                apiKey: $this->requireEnv('GOOGLE_API_KEY'),
                model: $this->resolveModel($options, 'gemini_model', 'GEMINI_MODEL', 'gemini-2.0-flash-exp'),
                ragieKey: $ragieKey,
                partition: $partition,
                defaults: $defaults,
                metadata: [
                    'plan' => self::PRESET_FREE,
                    'tier' => 'free',
                    'embedding_provider' => 'google-gemini-embedding',
                    'vector_store' => 'qdrant-cloud-free',
                    'insight' => $geminiInsight,
                ],
                solutionOverrides: [
                    'type' => 'gemini-file-search',
                    'vector_store' => $vectorStore,
                    'google_api_key' => $this->requireEnv('GOOGLE_API_KEY'),
                ]
            ),
            $this->ragieCatalogEntry(
                slug: 'groq',
                apiKey: $this->requireEnv('GROQ_API_KEY'),
                model: $this->resolveModel($options, 'groq_model', 'GROQ_MODEL', 'llama-3.1-8b-instant'),
                ragieKey: $ragieKey,
                partition: $partition,
                defaults: $defaults,
                metadata: [
                    'plan' => self::PRESET_FREE,
                    'tier' => 'free',
                    'embedding_provider' => 'google-gemini-embedding',
                    'vector_store' => 'qdrant-cloud-free',
                    'insight' => $this->summarizeInsight('groq', 'groq-llama'),
                ]
            ),
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function ragieCatalogEntry(
        string $slug,
        string $apiKey,
        string $model,
        string $ragieKey,
        string $partition,
        array $defaults,
        array $metadata,
        array $solutionOverrides = []
    ): array {
        $this->requireProvider($slug);

        $solution = array_replace_recursive([
            'type' => 'ragie',
            'ragie_api_key' => $ragieKey,
            'ragie_partition' => $partition,
            'default_options' => $defaults,
            'metadata' => array_filter($metadata, static fn (mixed $value): bool => $value !== null),
        ], $solutionOverrides);

        return [
            'catalog' => [
                'slug' => $slug,
                'model_type' => 'generation',
                'overrides' => [
                    'api_key' => $apiKey,
                    'model' => $model,
                    'solution' => $solution,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolvePartition(array $options): string
    {
        if (isset($options['ragie_partition']) && is_string($options['ragie_partition']) && $options['ragie_partition'] !== '') {
            return $options['ragie_partition'];
        }

        return $this->env('RAGIE_PARTITION') ?? 'default';
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function extractArrayOption(array $options, string $key): array
    {
        $value = $options[$key] ?? [];
        return is_array($value) ? $value : [];
    }

    /**
     * @return list<string>
     */
    private function envList(string $listKey, ?string $singleKey = null): array
    {
        $rawList = $this->env($listKey);
        if ($rawList !== null) {
            $parts = array_values(array_filter(array_map(
                static fn (string $value): string => trim($value),
                explode(',', $rawList)
            ), static fn (string $value): bool => $value !== ''));

            if ($parts !== []) {
                return $parts;
            }
        }

        if ($singleKey !== null) {
            $single = $this->env($singleKey);
            if ($single !== null) {
                return [$single];
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolveModel(
        array $options,
        string $optionKey,
        string $envKey,
        string $default,
        ?string $fallbackEnvKey = null
    ): string {
        if (isset($options[$optionKey]) && is_string($options[$optionKey]) && trim($options[$optionKey]) !== '') {
            return trim($options[$optionKey]);
        }

        $env = $this->env($envKey);
        if ($env === null && $fallbackEnvKey !== null) {
            $env = $this->env($fallbackEnvKey);
        }

        return $env ?? $default;
    }

    private function summarizeInsight(string $slug, ?string $insightSlug = null): array
    {
        $summary = $this->requireProvider($slug);
        $metadata = $summary->metadata();
        $insights = $metadata['insights'] ?? [];
        if (!is_array($insights) || $insights === []) {
            return [
                'slug' => $slug,
                'name' => $summary->displayName(),
            ];
        }

        $selected = null;
        if ($insightSlug !== null && isset($insights[$insightSlug]) && is_array($insights[$insightSlug])) {
            $selected = $insights[$insightSlug];
        }

        if ($selected === null) {
            foreach ($insights as $entry) {
                if (is_array($entry)) {
                    $selected = $entry;
                    break;
                }
            }
        }

        if (!is_array($selected)) {
            return [
                'slug' => $slug,
                'name' => $summary->displayName(),
            ];
        }

        return [
            'slug' => isset($selected['slug']) && is_string($selected['slug']) ? $selected['slug'] : $slug,
            'name' => isset($selected['name']) && is_string($selected['name']) ? $selected['name'] : $summary->displayName(),
            'recommended_roles' => is_array($selected['recommended_roles'] ?? null)
                ? array_values($selected['recommended_roles'])
                : [],
            'free_tier' => is_array($selected['free_tier'] ?? null) ? $selected['free_tier'] : [],
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, string>|string
     */
    private function resolveGeminiVectorStore(array $options): array|string
    {
        if (isset($options['gemini_vector_store'])) {
            $override = $options['gemini_vector_store'];
            if (is_array($override) && $override !== []) {
                return $override;
            }
            if (is_string($override) && trim($override) !== '') {
                return trim($override);
            }
        }

        $datastore = $this->env('GEMINI_DATASTORE_ID');
        if ($datastore !== null) {
            return ['datastore' => $datastore];
        }

        $corpus = $this->env('GEMINI_CORPUS_ID');
        if ($corpus !== null) {
            return ['corpus' => $corpus];
        }

        $alias = $this->env('GEMINI_VECTOR_STORE');
        if ($alias !== null) {
            return trim($alias);
        }

        throw new RuntimeException(
            'Set GEMINI_DATASTORE_ID, GEMINI_CORPUS_ID, or GEMINI_VECTOR_STORE for Gemini File Search pools.'
        );
    }

    private function requireProvider(string $slug): ProviderSummary
    {
        $summary = $this->catalog->get($slug);
        if ($summary === null) {
            throw new RuntimeException(sprintf('Provider slug "%s" missing from catalog.', $slug));
        }

        return $summary;
    }

    private function requireEnv(string $key): string
    {
        $value = $this->env($key);
        if ($value === null) {
            throw new RuntimeException(sprintf('Missing required environment variable "%s".', $key));
        }

        return $value;
    }

    private function env(string $key): ?string
    {
        if (array_key_exists($key, $this->environment)) {
            $value = $this->environment[$key];
        } else {
            $value = getenv($key);
            if ($value === false || $value === null) {
                $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
            }
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}
