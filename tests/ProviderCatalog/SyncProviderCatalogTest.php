<?php

declare(strict_types=1);

// this_file: paragra-php/tests/ProviderCatalog/SyncProviderCatalogTest.php

namespace ParaGra\Tests\ProviderCatalog;

use PHPUnit\Framework\TestCase;

use function array_map;
use function escapeshellarg;
use function exec;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function json_decode;
use function json_encode;
use function mkdir;
use function sprintf;
use function sys_get_temp_dir;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

final class SyncProviderCatalogTest extends TestCase
{
    public function testSyncIncludesReferenceInsights(): void
    {
        $fixture = $this->createSyncFixture();

        $toolPath = realpath(__DIR__ . '/../../tools/sync_provider_catalog.php');
        self::assertNotFalse($toolPath, 'Sync tool should be resolvable.');

        $command = sprintf(
            '%s %s --source=%s --output=%s --insights=%s --quiet',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($toolPath),
            escapeshellarg($fixture['source']),
            escapeshellarg($fixture['output']),
            escapeshellarg($fixture['insights'])
        );

        exec($command, $output, $exitCode);
        self::assertSame(0, $exitCode, 'Sync script exited with an error.');

        $catalogPath = $fixture['output'] . '/catalog.json';
        self::assertFileExists($catalogPath, 'Catalog JSON was not generated.');

        /** @var array<string, mixed> $catalog */
        $catalog = json_decode((string) file_get_contents($catalogPath), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('providers', $catalog);

        $providers = [];
        foreach ($catalog['providers'] as $provider) {
            $providers[$provider['slug']] = $provider;
        }

        $expectations = [
            'gemini' => [
                ['slug' => 'google-gemini-flash', 'role' => 'frontline_free_llm', 'quota' => 'requests_per_day'],
                ['slug' => 'google-gemini-file-search', 'role' => 'managed_file_rag', 'quota' => 'max_file_mb'],
            ],
            'groq' => [
                ['slug' => 'groq-llama', 'role' => 'latency_critical_llm', 'quota' => 'models'],
            ],
            'openrouter' => [
                ['slug' => 'openrouter', 'role' => 'multi_model_experiments', 'quota' => 'requests_per_minute'],
            ],
            'mistral' => [
                ['slug' => 'mistral-la-plateforme', 'role' => 'evaluation_only', 'quota' => 'tokens_per_minute'],
            ],
            'voyage' => [
                ['slug' => 'voyage-embeddings', 'role' => 'code_search', 'quota' => 'one_time_tokens'],
            ],
            'cloudflare' => [
                ['slug' => 'cloudflare-workers-ai', 'role' => 'edge_embeddings', 'quota' => 'neurons_per_day'],
            ],
            'pinecone' => [
                ['slug' => 'pinecone-starter', 'role' => 'starter_rag_stack', 'quota' => 'storage_gb'],
            ],
            'qdrant' => [
                ['slug' => 'qdrant-cloud-free', 'role' => 'forever_free_vector', 'quota' => 'vector_capacity'],
            ],
            'dify' => [
                ['slug' => 'dify-platform', 'role' => 'hosted_rag_builder', 'quota' => 'notes'],
            ],
            'vectara' => [
                ['slug' => 'vectara-platform', 'role' => 'hosted_enterprise_rag', 'quota' => 'trial_days'],
            ],
            'bedrock-kb' => [
                ['slug' => 'aws-bedrock-knowledge-bases', 'role' => 'managed_graph_rag', 'quota' => 'monthly_cost_usd'],
            ],
            'askyoda' => [
                ['slug' => 'eden-askyoda', 'role' => 'hosted_fallback_rag', 'quota' => 'starter_requests_per_minute'],
            ],
        ];

        foreach ($expectations as $providerSlug => $insightExpectations) {
            self::assertArrayHasKey($providerSlug, $providers, sprintf('Missing provider %s.', $providerSlug));

            $metadata = $providers[$providerSlug]['metadata'] ?? [];
            self::assertIsArray($metadata, sprintf('Provider %s metadata missing.', $providerSlug));

            $insights = $metadata['insights'] ?? [];
            self::assertIsArray($insights, sprintf('Provider %s insights missing.', $providerSlug));

            foreach ($insightExpectations as $expectation) {
                self::assertArrayHasKey(
                    $expectation['slug'],
                    $insights,
                    sprintf('Provider %s missing insight %s.', $providerSlug, $expectation['slug'])
                );

                $insight = $insights[$expectation['slug']];
                self::assertContains(
                    $expectation['role'],
                    $insight['recommended_roles'] ?? [],
                    sprintf('Provider %s insight %s missing role.', $providerSlug, $expectation['slug'])
                );

                $freeTier = $insight['free_tier'] ?? [];
                self::assertIsArray(
                    $freeTier,
                    sprintf('Provider %s insight %s missing free tier.', $providerSlug, $expectation['slug'])
                );
                self::assertArrayHasKey(
                    $expectation['quota'],
                    $freeTier,
                    sprintf('Provider %s insight %s missing quota key %s.', $providerSlug, $expectation['slug'], $expectation['quota'])
                );
            }
        }
    }

    /**
     * @return array{source: string, output: string, insights: string}
     */
    private function createSyncFixture(): array
    {
        $base = sys_get_temp_dir() . '/paragra-sync-' . uniqid('', true);
        $source = $base . '/source';
        $output = $base . '/output';
        $insightsPath = $source . '/config/provider_insights.json';

        foreach ([
            $source,
            $source . '/external',
            $source . '/models',
            $source . '/config',
            $output,
        ] as $dir) {
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                self::fail(sprintf('Unable to create directory %s', $dir));
            }
        }

        file_put_contents($source . '/external/dump_models.py', $this->buildDumpScript());

        $modelSlugs = ['gemini', 'groq', 'openrouter', 'mistral', 'cloudflare', 'pinecone', 'qdrant', 'voyage', 'dify'];
        foreach ($modelSlugs as $slug) {
            $payload = ['data' => [['id' => $slug . '-1'], ['id' => $slug . '-2']]];
            file_put_contents(
                $source . '/models/' . $slug . '.json',
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
            );
        }

        file_put_contents(
            $insightsPath,
            json_encode($this->buildInsightsPayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );

        return [
            'source' => $source,
            'output' => $output,
            'insights' => $insightsPath,
        ];
    }

    private function buildDumpScript(): string
    {
        $urlLines = [
            'GEMINI_API_OPENAI, https://generativelanguage.googleapis.com/v1beta/openai',
            'GROQ_API_OPENAI, https://api.groq.com/openai/v1',
            'OPENROUTER_API_OPENAI, https://openrouter.ai/api/v1',
            'MISTRAL_API_OPENAI, https://api.mistral.ai/v1',
            'CLOUDFLARE_API_OPENAI, https://api.cloudflare.com/client/v4',
            'PINECONE_API_OPENAI, https://controller.us-east1-gcp.pinecone.io',
            'QDRANT_API_OPENAI, https://api.qdrant.tech',
            'VOYAGE_API_OPENAI, https://api.voyageai.com/v1',
            'DIFY_API_OPENAI, https://dify.example/api',
        ];

        $providerLines = [
            'gemini, oai, GOOGLE_API_KEY, GEMINI_API_OPENAI',
            'groq, oai, GROQ_API_KEY, GROQ_API_OPENAI',
            'openrouter, oai, OPENROUTER_API_KEY, OPENROUTER_API_OPENAI',
            'mistral, oai, MISTRAL_API_KEY, MISTRAL_API_OPENAI',
            'cloudflare, oai, CLOUDFLARE_API_TOKEN, CLOUDFLARE_API_OPENAI',
            'pinecone, url, PINECONE_API_KEY, PINECONE_API_OPENAI',
            'qdrant, url, QDRANT_API_KEY, QDRANT_API_OPENAI',
            'voyage, oai, VOYAGE_API_KEY, VOYAGE_API_OPENAI',
            'dify, url, DIFY_API_KEY, DIFY_API_OPENAI',
        ];

        return <<<PY
PROVIDER_URL_CONFIG = """
{$this->indentLines($urlLines)}
""".strip()

PROVIDER_CONFIG = """
{$this->indentLines($providerLines)}
""".strip()
PY;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInsightsPayload(): array
    {
        $source = [
            'path' => 'reference/research/demo.md',
            'sha256' => str_repeat('a', 64),
        ];

        $entries = [
            [
                'slug' => 'google-gemini-flash',
                'name' => 'Gemini Flash',
                'category' => 'llm',
                'modalities' => ['text'],
                'recommended_roles' => ['frontline_free_llm'],
                'reset_window' => 'daily',
                'commercial_use' => 'Allowed.',
                'free_quota' => ['requests_per_day' => 250],
                'notes' => 'Gemini free tier.',
                'sources' => [$source],
            ],
            [
                'slug' => 'google-gemini-file-search',
                'name' => 'Gemini File Search',
                'category' => 'rag_tool',
                'modalities' => ['rag', 'vector_store'],
                'recommended_roles' => ['managed_file_rag'],
                'free_quota' => [
                    'max_file_mb' => 100,
                    'store_quota_gb_free' => 1,
                ],
                'sources' => [$source],
            ],
            [
                'slug' => 'groq-llama',
                'name' => 'Groq Llama',
                'category' => 'llm',
                'modalities' => ['text'],
                'recommended_roles' => ['latency_critical_llm'],
                'free_quota' => [
                    'requests_per_minute' => 40,
                    'models' => [
                        ['model' => 'llama-3.1-8b', 'requests_per_day' => 14400],
                    ],
                ],
                'sources' => [$source],
            ],
            [
                'slug' => 'openrouter',
                'name' => 'OpenRouter Free',
                'category' => 'router',
                'modalities' => ['text'],
                'recommended_roles' => ['multi_model_experiments'],
                'free_quota' => ['requests_per_minute' => 20],
                'sources' => [$source],
            ],
            [
                'slug' => 'mistral-la-plateforme',
                'name' => 'Mistral Experiment',
                'category' => 'llm',
                'modalities' => ['text'],
                'recommended_roles' => ['evaluation_only'],
                'free_quota' => ['tokens_per_minute' => 500000],
                'sources' => [$source],
            ],
            [
                'slug' => 'voyage-embeddings',
                'name' => 'Voyage Embeddings',
                'category' => 'embedding',
                'modalities' => ['embedding'],
                'recommended_roles' => ['code_search'],
                'free_quota' => ['one_time_tokens' => 200000000],
                'sources' => [$source],
            ],
            [
                'slug' => 'cloudflare-workers-ai',
                'name' => 'Workers AI',
                'category' => 'edge_ai',
                'modalities' => ['embedding'],
                'recommended_roles' => ['edge_embeddings'],
                'free_quota' => ['neurons_per_day' => 10000],
                'sources' => [$source],
            ],
            [
                'slug' => 'pinecone-starter',
                'name' => 'Pinecone Starter',
                'category' => 'vector_store',
                'modalities' => ['vector'],
                'recommended_roles' => ['starter_rag_stack'],
                'free_quota' => ['storage_gb' => 2],
                'sources' => [$source],
            ],
            [
                'slug' => 'qdrant-cloud-free',
                'name' => 'Qdrant Free',
                'category' => 'vector_store',
                'modalities' => ['vector'],
                'recommended_roles' => ['forever_free_vector'],
                'free_quota' => ['vector_capacity' => 1000000],
                'sources' => [$source],
            ],
            [
                'slug' => 'dify-platform',
                'name' => 'Dify Platform',
                'category' => 'platform',
                'modalities' => ['workflow'],
                'recommended_roles' => ['hosted_rag_builder'],
                'free_quota' => ['notes' => 'Self-host cost only.'],
                'sources' => [$source],
            ],
            [
                'slug' => 'vectara-platform',
                'name' => 'Vectara Managed RAG',
                'category' => 'hosted_rag',
                'modalities' => ['rag', 'llm'],
                'recommended_roles' => ['hosted_enterprise_rag'],
                'free_quota' => [
                    'trial_days' => 30,
                    'approx_queries_per_month' => 15000,
                ],
                'sources' => [$source],
            ],
            [
                'slug' => 'eden-askyoda',
                'name' => 'Eden AskYoda Hosted Workflow',
                'category' => 'hosted_rag',
                'modalities' => ['rag', 'workflow'],
                'recommended_roles' => ['hosted_fallback_rag'],
                'free_quota' => [
                    'starter_requests_per_minute' => 60,
                    'personal_requests_per_minute' => 300,
                    'professional_requests_per_minute' => 1000,
                ],
                'sources' => [$source],
            ],
            [
                'slug' => 'aws-bedrock-knowledge-bases',
                'name' => 'AWS Bedrock Knowledge Bases',
                'category' => 'managed_rag',
                'modalities' => ['rag', 'graph'],
                'recommended_roles' => ['managed_graph_rag'],
                'free_quota' => [
                    'monthly_cost_usd' => 500,
                    'setup_days' => 5,
                ],
                'sources' => [$source],
            ],
        ];

        return [
            '__meta__' => [
                'this_file' => 'reference/catalog/provider_insights.json',
                'schema_version' => 1,
            ],
            'providers' => $entries,
        ];
    }

    /**
     * @param list<string> $lines
     */
    private function indentLines(array $lines): string
    {
        return implode("\n", array_map(static fn (string $line): string => '    ' . $line, $lines));
    }
}
