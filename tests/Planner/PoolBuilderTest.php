<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Planner/PoolBuilderTest.php

namespace ParaGra\Tests\Planner;

use ParaGra\Planner\PoolBuilder;
use ParaGra\ProviderCatalog\ProviderDiscovery;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_replace;

final class PoolBuilderTest extends TestCase
{
    public function test_build_free_tier_pool_structure(): void
    {
        $builder = $this->makeBuilder([
            'RAGIE_API_KEY' => 'ragie-key',
            'GOOGLE_API_KEY' => 'google-key',
            'GROQ_API_KEY' => 'groq-key',
            'GEMINI_DATASTORE_ID' => 'fileSearchStores/demo-store',
        ]);

        $pools = $builder->build(PoolBuilder::PRESET_FREE, [
            'ragie_defaults' => ['top_k' => 6],
        ]);

        self::assertCount(1, $pools, 'Free tier preset should emit a single rotation pool when no fallbacks set.');
        $pool = $pools[0];
        self::assertCount(2, $pool, 'Free tier rotation should include Gemini and Groq.');

        $gemini = $this->assertHasCatalogSlug($pool, 'gemini');
        self::assertSame('generation', $gemini['catalog']['model_type'] ?? null);
        self::assertSame('google-key', $gemini['catalog']['overrides']['api_key'] ?? null);
        self::assertSame('ragie-key', $gemini['catalog']['overrides']['solution']['ragie_api_key'] ?? null);
        self::assertSame(['top_k' => 6], $gemini['catalog']['overrides']['solution']['default_options'] ?? null);
        self::assertSame(
            ['datastore' => 'fileSearchStores/demo-store'],
            $gemini['catalog']['overrides']['solution']['vector_store'] ?? null
        );
        self::assertSame(
            'google-gemini-embedding',
            $gemini['catalog']['overrides']['solution']['metadata']['embedding_provider'] ?? null
        );
        self::assertSame('qdrant-cloud-free', $gemini['catalog']['overrides']['solution']['metadata']['vector_store'] ?? null);
        $geminiInsight = $gemini['catalog']['overrides']['solution']['metadata']['insight'] ?? null;
        self::assertIsArray($geminiInsight);
        self::assertSame('google-gemini-file-search', $geminiInsight['slug'] ?? null);
        self::assertSame(100, $geminiInsight['free_tier']['max_file_mb'] ?? null);

        $groq = $this->assertHasCatalogSlug($pool, 'groq');
        self::assertSame('groq-key', $groq['catalog']['overrides']['api_key'] ?? null);
        self::assertSame('ragie-key', $groq['catalog']['overrides']['solution']['ragie_api_key'] ?? null);
        self::assertSame('free-tier', $groq['catalog']['overrides']['solution']['metadata']['plan'] ?? null);
        $groqInsight = $groq['catalog']['overrides']['solution']['metadata']['insight'] ?? null;
        self::assertIsArray($groqInsight);
        self::assertSame('groq-llama', $groqInsight['slug'] ?? null);
    }

    public function test_build_hybrid_pool_includes_openai_with_overrides(): void
    {
        $builder = $this->makeBuilder([
            'RAGIE_API_KEY' => 'ragie-key',
            'GOOGLE_API_KEY' => 'google-key',
            'GROQ_API_KEY' => 'groq-key',
            'OPENAI_API_KEY' => 'openai-key',
            'GEMINI_DATASTORE_ID' => 'fileSearchStores/demo-store',
        ]);

        $pools = $builder->build(PoolBuilder::PRESET_HYBRID, [
            'ragie_partition' => 'support',
            'ragie_defaults' => ['top_k' => 7],
            'openai_model' => 'gpt-4.1-mini',
        ]);

        self::assertGreaterThanOrEqual(2, $pools, 'Hybrid preset should produce at least two pools.');
        $fallbackPool = $pools[1];
        $openai = $this->assertHasCatalogSlug($fallbackPool, 'openai');

        self::assertSame('gpt-4.1-mini', $openai['catalog']['overrides']['model'] ?? null);
        self::assertSame('support', $openai['catalog']['overrides']['solution']['ragie_partition'] ?? null);
        self::assertSame('openai-key', $openai['catalog']['overrides']['api_key'] ?? null);
        self::assertSame(
            'pinecone-starter',
            $openai['catalog']['overrides']['solution']['metadata']['vector_store'] ?? null
        );
        self::assertSame(
            'voyage-embeddings',
            $openai['catalog']['overrides']['solution']['metadata']['embedding_provider'] ?? null
        );
    }

    public function test_build_hosted_pool_includes_hosted_recommendations(): void
    {
        $builder = $this->makeBuilder([
            'RAGIE_API_KEY' => 'ragie-key',
            'GOOGLE_API_KEY' => 'google-key',
            'GROQ_API_KEY' => 'groq-key',
            'EDENAI_API_KEY' => 'eden-key',
            'EDENAI_ASKYODA_PROJECT' => 'askyoda-project',
        ]);

        $pools = $builder->build(PoolBuilder::PRESET_HOSTED, [
            'askyoda_options' => ['k' => 8],
            'askyoda_llm' => ['provider' => 'google', 'model' => 'gemini-2.0-flash-exp'],
        ]);

        self::assertCount(1, $pools);
        $hosted = $this->assertHasCatalogSlug($pools[0], 'askyoda');
        $solution = $hosted['catalog']['overrides']['solution'] ?? [];

        self::assertSame('eden-key', $solution['askyoda_api_key'] ?? null);
        self::assertSame('askyoda-project', $solution['project_id'] ?? null);
        self::assertSame(['k' => 8], $solution['default_options'] ?? null);
        self::assertSame(['provider' => 'google', 'model' => 'gemini-2.0-flash-exp'], $solution['llm'] ?? null);

        $metadata = $solution['metadata'] ?? [];
        self::assertArrayHasKey('hosted_recommendations', $metadata);
        $recommendations = $metadata['hosted_recommendations'];
        self::assertIsArray($recommendations);
        self::assertSame('vectara-platform', $recommendations[0]['slug'] ?? null);
        self::assertSame('aws-bedrock-knowledge-bases', $recommendations[1]['slug'] ?? null);
        self::assertSame('hosted', $metadata['latency_tier'] ?? null);
        $insight = $metadata['insight'] ?? [];
        self::assertSame('eden-askyoda', $insight['slug'] ?? null);
        self::assertSame(60, $insight['free_tier']['starter_requests_per_minute'] ?? null);
    }

    public function test_build_free_tier_without_required_env_throws(): void
    {
        $builder = $this->makeBuilder([
            'RAGIE_API_KEY' => 'ragie-key',
            'GEMINI_DATASTORE_ID' => 'fileSearchStores/demo-store',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GOOGLE_API_KEY');

        $builder->build(PoolBuilder::PRESET_FREE);
    }

    public function test_build_free_tier_missing_catalog_provider_throws(): void
    {
        $builder = $this->makeBuilder(
            [
                'RAGIE_API_KEY' => 'ragie-key',
                'GOOGLE_API_KEY' => 'google-key',
                'GROQ_API_KEY' => 'groq-key',
                'GEMINI_DATASTORE_ID' => 'fileSearchStores/demo-store',
            ],
            [],
            ['groq']
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('groq');

        $builder->build(PoolBuilder::PRESET_FREE);
    }

    public function test_build_free_tier_prefers_corpus_env_when_datastore_missing(): void
    {
        $builder = $this->makeBuilder([
            'RAGIE_API_KEY' => 'ragie-key',
            'GOOGLE_API_KEY' => 'google-key',
            'GROQ_API_KEY' => 'groq-key',
            'GEMINI_CORPUS_ID' => 'corpora/demo-corpus',
        ]);

        $pools = $builder->build(PoolBuilder::PRESET_FREE);
        $gemini = $this->assertHasCatalogSlug($pools[0], 'gemini');

        self::assertSame(
            ['corpus' => 'corpora/demo-corpus'],
            $gemini['catalog']['overrides']['solution']['vector_store'] ?? null
        );
    }

    public function test_build_free_tier_without_vector_store_env_throws(): void
    {
        $builder = $this->makeBuilder([
            'RAGIE_API_KEY' => 'ragie-key',
            'GOOGLE_API_KEY' => 'google-key',
            'GROQ_API_KEY' => 'groq-key',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GEMINI_DATASTORE_ID');

        $builder->build(PoolBuilder::PRESET_FREE);
    }

    /**
     * @param array<string, string> $env
     * @param array<string, array<string, mixed>> $catalogOverrides
     * @param list<string> $omit
     */
    private function makeBuilder(array $env, array $catalogOverrides = [], array $omit = []): PoolBuilder
    {
        $guardedEnv = array_replace([
            'RAGIE_API_KEY' => '',
            'GOOGLE_API_KEY' => '',
            'GROQ_API_KEY' => '',
            'OPENAI_API_KEY' => '',
            'PARAGRA_OPENAI_API_KEY' => '',
            'PARAGRA_OPENAI_MODEL' => '',
            'OPENAI_MODEL' => '',
            'GEMINI_MODEL' => '',
            'GROQ_MODEL' => '',
            'CEREBRAS_API_KEYS' => '',
            'CEREBRAS_API_KEY' => '',
            'CEREBRAS_MODEL' => '',
            'EDENAI_API_KEY' => '',
            'EDENAI_ASKYODA_PROJECT' => '',
            'EDENAI_LLM_PROVIDER' => '',
            'EDENAI_LLM_MODEL' => '',
            'GEMINI_DATASTORE_ID' => '',
            'GEMINI_CORPUS_ID' => '',
            'GEMINI_VECTOR_STORE' => '',
        ], $env);

        return new PoolBuilder($this->makeCatalog($catalogOverrides, $omit), $guardedEnv);
    }

    /**
     * @param array<string, array<string, mixed>> $catalogOverrides
     * @param list<string> $omit
     */
    private function makeCatalog(array $catalogOverrides = [], array $omit = []): ProviderDiscovery
    {
        $providers = $this->baseProviders();
        foreach ($omit as $slug) {
            unset($providers[$slug]);
        }

        foreach ($catalogOverrides as $slug => $override) {
            if (!isset($providers[$slug])) {
                continue;
            }
            $providers[$slug] = array_replace_recursive($providers[$slug], $override);
        }

        return ProviderDiscovery::fromCatalogArray(['providers' => array_values($providers)]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function baseProviders(): array
    {
        $capabilities = static fn (): array => [
            'llm_chat' => true,
            'embeddings' => true,
            'vector_store' => true,
            'moderation' => false,
            'image_generation' => false,
            'byok' => true,
        ];

        return [
            'gemini' => [
                'slug' => 'gemini',
                'display_name' => 'Google Gemini',
                'description' => '',
                'api_key_env' => 'GOOGLE_API_KEY',
                'base_url' => null,
                'capabilities' => $capabilities(),
                'model_count' => 2,
                'models' => [],
                'embedding_dimensions' => [],
                'preferred_vector_store' => null,
                'default_models' => [
                    'generation' => 'gemini-2.0-flash-exp',
                    'embedding' => 'text-embedding-004',
                ],
                'default_solution' => [
                    'type' => 'gemini-file-search',
                ],
                'metadata' => [
                    'tier' => 'paid',
                    'insights' => [
                        'google-gemini-flash' => [
                            'slug' => 'google-gemini-flash',
                            'name' => 'Gemini 2.5 Flash',
                            'recommended_roles' => ['frontline_free_llm'],
                            'free_tier' => ['requests_per_day' => 250],
                        ],
                        'google-gemini-embedding' => [
                            'slug' => 'google-gemini-embedding',
                            'name' => 'Gemini Embedding',
                            'recommended_roles' => ['frontline_embeddings'],
                            'free_tier' => ['requests_per_day' => 1000],
                        ],
                        'google-gemini-file-search' => [
                            'slug' => 'google-gemini-file-search',
                            'name' => 'Gemini File Search',
                            'recommended_roles' => ['managed_file_rag'],
                            'free_tier' => ['max_file_mb' => 100],
                        ],
                    ],
                ],
            ],
            'groq' => [
                'slug' => 'groq',
                'display_name' => 'Groq',
                'description' => '',
                'api_key_env' => 'GROQ_API_KEY',
                'base_url' => null,
                'capabilities' => $capabilities(),
                'model_count' => 1,
                'models' => [],
                'embedding_dimensions' => [],
                'preferred_vector_store' => null,
                'default_models' => [
                    'generation' => 'llama-3.1-8b-instant',
                ],
                'default_solution' => [
                    'type' => 'ragie',
                ],
                'metadata' => [
                    'tier' => 'free',
                    'insights' => [
                        'groq-llama' => [
                            'slug' => 'groq-llama',
                            'name' => 'Groq Llama',
                            'recommended_roles' => ['latency_critical_llm'],
                            'free_tier' => ['models' => [['model' => 'llama-3.1-8b', 'requests_per_day' => 14400]]],
                        ],
                    ],
                ],
            ],
            'openai' => [
                'slug' => 'openai',
                'display_name' => 'OpenAI',
                'description' => '',
                'api_key_env' => 'OPENAI_API_KEY',
                'base_url' => null,
                'capabilities' => $capabilities(),
                'model_count' => 1,
                'models' => [],
                'embedding_dimensions' => [],
                'preferred_vector_store' => 'ragie',
                'default_models' => [
                    'generation' => 'gpt-4o-mini',
                ],
                'default_solution' => [
                    'type' => 'ragie',
                ],
                'metadata' => [
                    'tier' => 'paid',
                ],
            ],
            'askyoda' => [
                'slug' => 'askyoda',
                'display_name' => 'AskYoda',
                'description' => '',
                'api_key_env' => 'EDENAI_API_KEY',
                'base_url' => null,
                'capabilities' => $capabilities(),
                'model_count' => 1,
                'models' => [],
                'embedding_dimensions' => [],
                'preferred_vector_store' => 'askyoda',
                'default_models' => [
                    'generation' => 'askyoda-default',
                ],
                'default_solution' => [
                    'type' => 'askyoda',
                ],
                'metadata' => [
                    'tier' => 'fallback',
                    'latency_tier' => 'hosted',
                    'insights' => [
                        'eden-askyoda' => [
                            'slug' => 'eden-askyoda',
                            'name' => 'Eden AI AskYoda Hosted Workflow',
                            'recommended_roles' => ['hosted_fallback_rag'],
                            'free_tier' => [
                                'starter_requests_per_minute' => 60,
                                'personal_requests_per_minute' => 300,
                                'professional_requests_per_minute' => 1000,
                            ],
                        ],
                    ],
                ],
            ],
            'vectara' => [
                'slug' => 'vectara',
                'display_name' => 'Vectara',
                'description' => '',
                'capabilities' => $capabilities(),
                'model_count' => 0,
                'models' => [],
                'embedding_dimensions' => [],
                'preferred_vector_store' => null,
                'default_models' => [],
                'metadata' => [
                    'insights' => [
                        'vectara-platform' => [
                            'slug' => 'vectara-platform',
                            'name' => 'Vectara Managed RAG',
                            'recommended_roles' => ['hosted_enterprise_rag'],
                            'free_tier' => ['trial_days' => 30],
                        ],
                    ],
                ],
            ],
            'bedrock-kb' => [
                'slug' => 'bedrock-kb',
                'display_name' => 'AWS Bedrock KB',
                'description' => '',
                'capabilities' => [
                    'llm_chat' => false,
                    'embeddings' => false,
                    'vector_store' => true,
                    'moderation' => false,
                    'image_generation' => false,
                    'byok' => true,
                ],
                'model_count' => 0,
                'models' => [],
                'embedding_dimensions' => [],
                'preferred_vector_store' => null,
                'default_models' => [],
                'metadata' => [
                    'insights' => [
                        'aws-bedrock-knowledge-bases' => [
                            'slug' => 'aws-bedrock-knowledge-bases',
                            'name' => 'Bedrock Knowledge Bases',
                            'recommended_roles' => ['managed_graph_rag'],
                            'free_tier' => ['monthly_cost_usd' => 500],
                        ],
                    ],
                ],
            ],
            'pinecone' => [
                'slug' => 'pinecone',
                'display_name' => 'Pinecone',
                'description' => '',
                'capabilities' => [
                    'llm_chat' => false,
                    'embeddings' => false,
                    'vector_store' => true,
                    'moderation' => false,
                    'image_generation' => false,
                    'byok' => false,
                ],
                'model_count' => 0,
                'models' => [],
                'embedding_dimensions' => [],
                'preferred_vector_store' => null,
                'default_models' => [],
                'metadata' => [
                    'insights' => [
                        'pinecone-starter' => [
                            'slug' => 'pinecone-starter',
                            'name' => 'Pinecone Starter',
                            'recommended_roles' => ['starter_rag_stack'],
                            'free_tier' => ['storage_gb' => 2],
                        ],
                    ],
                ],
            ],
            'qdrant' => [
                'slug' => 'qdrant',
                'display_name' => 'Qdrant',
                'description' => '',
                'capabilities' => [
                    'llm_chat' => false,
                    'embeddings' => false,
                    'vector_store' => true,
                    'moderation' => false,
                    'image_generation' => false,
                    'byok' => false,
                ],
                'model_count' => 0,
                'models' => [],
                'embedding_dimensions' => [],
                'preferred_vector_store' => null,
                'default_models' => [],
                'metadata' => [
                    'insights' => [
                        'qdrant-cloud-free' => [
                            'slug' => 'qdrant-cloud-free',
                            'name' => 'Qdrant Cloud Free',
                            'recommended_roles' => ['forever_free_vector'],
                            'free_tier' => ['vector_capacity' => 1000000],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $pool
     * @return array<string, mixed>
     */
    private function assertHasCatalogSlug(array $pool, string $slug): array
    {
        foreach ($pool as $entry) {
            $catalog = $entry['catalog'] ?? null;
            if (is_array($catalog) && ($catalog['slug'] ?? null) === $slug) {
                return $entry;
            }
        }

        self::fail(sprintf('Pool missing catalog entry for %s', $slug));
    }
}
