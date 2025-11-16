#!/usr/bin/env php
<?php

declare(strict_types=1);

// this_file: paragra-php/tools/sync_provider_catalog.php

const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

$rootDir = dirname(__DIR__);
$options = getopt('', ['source::', 'output::', 'insights::', 'quiet']);

$sourcePath = isset($options['source']) ? (string) $options['source'] : $rootDir . '/../vexy-co-model-catalog';
$outputPath = isset($options['output']) ? (string) $options['output'] : $rootDir . '/config/providers';
$insightsPath = isset($options['insights']) ? (string) $options['insights'] : $sourcePath . '/config/provider_insights.json';
$quiet = isset($options['quiet']);

if (!is_dir($sourcePath)) {
    fail(sprintf('Source path "%s" does not exist. Pass --source to override.', $sourcePath));
}

$modelsPath = $sourcePath . '/models';
$dumpScriptPath = $sourcePath . '/external/dump_models.py';

$providerConfigs = parseProviderConfigs($dumpScriptPath);
$modelData = loadModelFiles($modelsPath);
$presets = capabilityPresets();
$insights = loadProviderInsights($insightsPath);
$insightMap = insightMappings();
$catalog = buildCatalog($sourcePath, $providerConfigs, $modelData, $presets, $insights, $insightMap);

if (!is_dir($outputPath) && !mkdir($outputPath, 0775, true) && !is_dir($outputPath)) {
    fail(sprintf('Unable to create output directory "%s".', $outputPath));
}

writeCatalogFiles($outputPath, $catalog);

if (!$quiet) {
    fwrite(
        STDOUT,
        sprintf(
            "Synced %d provider entries into %s\n",
            count($catalog['providers']),
            $outputPath
        )
    );
}

/**
 * @return array<string, array{api_key_env: string|null, base_url: string|null}>
 */
function parseProviderConfigs(string $dumpScriptPath): array
{
    if (!is_file($dumpScriptPath)) {
        fwrite(
            STDERR,
            sprintf("Warning: provider config source %s not found; relying on presets only.\n", $dumpScriptPath)
        );

        return [];
    }

    $contents = file_get_contents($dumpScriptPath);
    if ($contents === false) {
        throw new RuntimeException(sprintf('Unable to read provider config file "%s".', $dumpScriptPath));
    }

    $urlMap = parseKeyValueBlock($contents, 'PROVIDER_URL_CONFIG');
    $providerLines = parseListBlock($contents, 'PROVIDER_CONFIG');

    $providers = [];
    foreach ($providerLines as $line) {
        $parts = array_map('trim', explode(',', $line));
        if (count($parts) !== 4) {
            continue;
        }

        [$name, /* kind */, $apiEnv, $urlEnv] = $parts;
        $urlEnv = $urlEnv !== '' ? $urlEnv : null;
        $baseUrl = $urlEnv && isset($urlMap[$urlEnv]) ? rtrim($urlMap[$urlEnv], '/') : null;

        if ($baseUrl !== null && str_ends_with($baseUrl, '/models')) {
            $baseUrl = rtrim(substr($baseUrl, 0, -7), '/');
        }

        $providers[$name] = [
            'api_key_env' => $apiEnv !== '' ? $apiEnv : null,
            'base_url' => $baseUrl,
        ];
    }

    return $providers;
}

/**
 * @return array<string, string>
 */
function parseKeyValueBlock(string $contents, string $identifier): array
{
    $lines = parseListBlock($contents, $identifier);
    $map = [];
    foreach ($lines as $line) {
        $parts = array_map('trim', explode(',', $line));
        if (count($parts) !== 2) {
            continue;
        }
        $map[$parts[0]] = $parts[1];
    }

    return $map;
}

/**
 * @return list<string>
 */
function parseListBlock(string $contents, string $identifier): array
{
    $pattern = sprintf('/%s\s*=\s*"""\s*(.*?)\s*"""\.strip/s', preg_quote($identifier, '/'));
    if (!preg_match($pattern, $contents, $matches)) {
        return [];
    }

    $block = trim($matches[1]);
    if ($block === '') {
        return [];
    }

    $lines = array_map('trim', explode("\n", $block));

    return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
}

/**
 * @return array<string, array{ids: list<string>, raw: mixed}>
 */
function loadModelFiles(string $modelsPath): array
{
    if (!is_dir($modelsPath)) {
        fwrite(STDERR, sprintf("Warning: models directory %s not found; skipping model ingestion.\n", $modelsPath));

        return [];
    }

    $files = glob($modelsPath . '/*.json') ?: [];
    $result = [];
    foreach ($files as $file) {
        $slug = basename($file, '.json');
        $contents = file_get_contents($file);
        if ($contents === false) {
            fwrite(STDERR, sprintf("Warning: unable to read model file %s.\n", $file));
            continue;
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            fwrite(STDERR, sprintf("Warning: invalid JSON in %s (%s).\n", $file, $e->getMessage()));
            continue;
        }

        $ids = extractModelIds($decoded);
        sort($ids, SORT_NATURAL | SORT_FLAG_CASE);

        $result[$slug] = [
            'ids' => $ids,
            'raw' => $decoded,
        ];
    }

    return $result;
}

/**
 * @param mixed $decoded
 * @return list<string>
 */
function extractModelIds(mixed $decoded): array
{
    if (is_array($decoded)) {
        if (isset($decoded['data']) && is_array($decoded['data'])) {
            $ids = [];
            foreach ($decoded['data'] as $row) {
                if (is_array($row) && isset($row['id']) && is_string($row['id'])) {
                    $ids[] = $row['id'];
                }
            }

            return $ids;
        }

        $ids = [];
        foreach ($decoded as $key => $value) {
            if ($key === 'sample_spec') {
                continue;
            }
            if (is_string($key) && $key !== '') {
                $ids[] = $key;
            } elseif (is_array($value) && isset($value['id']) && is_string($value['id'])) {
                $ids[] = $value['id'];
            }
        }

        return $ids;
    }

    return [];
}

/**
 * @param array<string, array{api_key_env: string|null, base_url: string|null}> $providerConfigs
 * @param array<string, array{ids: list<string>, raw: mixed}> $modelData
 * @param array<string, array<string, mixed>> $presets
 * @param array<string, array<string, mixed>> $insights
 * @param array<string, list<string>> $insightMap
 *
 * @return array{
 *     generated_at: string,
 *     source: string,
 *     providers: list<array{
 *         slug: string,
 *         display_name: string,
 *         description: string,
 *         api_key_env: string|null,
 *         base_url: string|null,
 *         capabilities: array<string, bool>,
 *         model_count: int,
 *         models: list<string>,
 *         embedding_dimensions: array<string, int>,
 *         preferred_vector_store: string|null,
 *         default_models: array<string, string>,
 *         default_solution: array<string, mixed>|null,
 *         metadata: array<string, mixed>
 *     }>
 * }
 */
function buildCatalog(
    string $sourcePath,
    array $providerConfigs,
    array $modelData,
    array $presets,
    array $insights,
    array $insightMap
): array {
    $slugs = array_unique(array_merge(array_keys($providerConfigs), array_keys($modelData), array_keys($presets)));
    sort($slugs);

    $providers = [];
    foreach ($slugs as $slug) {
        $meta = $providerConfigs[$slug] ?? ['api_key_env' => null, 'base_url' => null];
        $preset = $presets[$slug] ?? [];
        $models = $modelData[$slug]['ids'] ?? [];

        $providers[] = [
            'slug' => $slug,
            'display_name' => (string) ($preset['display_name'] ?? ucfirst($slug)),
            'description' => (string) ($preset['description'] ?? ''),
            'api_key_env' => $preset['api_key_env'] ?? $meta['api_key_env'],
            'base_url' => $preset['base_url'] ?? $meta['base_url'],
            'capabilities' => buildCapabilityMap($preset['capabilities'] ?? []),
            'model_count' => count($models),
            'models' => $models,
            'embedding_dimensions' => $preset['embedding_dimensions'] ?? [],
            'preferred_vector_store' => $preset['preferred_vector_store'] ?? null,
            'default_models' => $preset['recommended_models'] ?? [],
            'default_solution' => $preset['default_solution'] ?? null,
            'metadata' => applyInsightMetadata(
                $slug,
                $preset['metadata'] ?? [],
                $insights,
                $insightMap
            ),
        ];
    }

    return [
        'generated_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        'source' => realpath($sourcePath) ?: $sourcePath,
        'providers' => $providers,
    ];
}

/**
 * @param array<string, mixed> $capabilities
 * @return array<string, bool>
 */
function buildCapabilityMap(array $capabilities): array
{
    $defaults = [
        'llm_chat' => false,
        'embeddings' => false,
        'vector_store' => false,
        'moderation' => false,
        'image_generation' => false,
        'byok' => false,
    ];

    foreach ($defaults as $key => $value) {
        if (array_key_exists($key, $capabilities)) {
            $defaults[$key] = (bool) $capabilities[$key];
        }
    }

    return $defaults;
}

/**
 * @return array<string, array<string, mixed>>
 */
function capabilityPresets(): array
{
    return [
        'askyoda' => [
            'display_name' => 'EdenAI AskYoda',
            'description' => 'Full retrieval + answer pipeline via EdenAI AskYoda.',
            'api_key_env' => 'EDENAI_API_KEY',
            'base_url' => 'https://api.edenai.run/v2',
            'capabilities' => [
                'llm_chat' => true,
                'embeddings' => false,
                'vector_store' => false,
                'moderation' => false,
                'image_generation' => false,
                'byok' => false,
            ],
            'recommended_models' => [
                'generation' => 'askyoda:gemini-2.5-flash-lite',
            ],
            'preferred_vector_store' => 'askyoda',
            'default_solution' => [
                'type' => 'askyoda',
                'defaults' => [
                    'k' => 10,
                    'min_score' => 0.3,
                ],
            ],
            'metadata' => [
                'tier' => 'hosted',
                'latency' => 'medium',
                'latency_tier' => 'hosted',
            ],
        ],
        'cloudflare' => [
            'display_name' => 'Cloudflare Workers AI',
            'description' => 'Edge-hosted inference with EmbeddingGemma and 10k neuron daily renewals.',
            'api_key_env' => 'CLOUDFLARE_API_TOKEN',
            'base_url' => 'https://api.cloudflare.com/client/v4',
            'capabilities' => [
                'llm_chat' => true,
                'embeddings' => true,
                'vector_store' => true,
                'moderation' => false,
                'image_generation' => false,
                'byok' => true,
            ],
            'recommended_models' => [
                'generation' => '@cf/meta/llama-3.1-8b-instruct',
                'embedding' => '@cf/google/gemma-embedding-002',
            ],
            'preferred_vector_store' => 'workers-ai',
            'metadata' => [
                'tier' => 'free',
                'latency' => 'low',
            ],
        ],
        'dify' => [
            'display_name' => 'Dify Orchestrator',
            'description' => 'Self-hostable RAG + automation builder with visual flows.',
            'capabilities' => [
                'llm_chat' => true,
                'embeddings' => true,
                'vector_store' => true,
                'moderation' => false,
                'image_generation' => false,
                'byok' => true,
            ],
            'default_solution' => [
                'type' => 'dify',
            ],
            'metadata' => [
                'tier' => 'self_hosted',
                'latency' => 'dependent',
            ],
        ],
        'cerebras' => [
            'display_name' => 'Cerebras',
            'description' => 'Fast, low-cost Llama-3.3 hosting and generous free tier.',
            'capabilities' => [
                'llm_chat' => true,
                'embeddings' => false,
                'vector_store' => false,
                'moderation' => false,
                'image_generation' => false,
                'byok' => false,
            ],
            'recommended_models' => [
                'generation' => 'llama-3.3-70b',
                'fast_generation' => 'llama-3.1-8b',
            ],
            'preferred_vector_store' => 'ragie',
            'default_solution' => [
                'type' => 'ragie',
                'ragie_partition' => 'default',
                'metadata' => ['tier' => 'free'],
            ],
            'metadata' => [
                'tier' => 'free',
                'latency' => 'medium',
            ],
        ],
        'gemini' => [
            'display_name' => 'Google Gemini',
            'description' => 'Gemini API with File Search and text-embedding-004.',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
            'capabilities' => [
                'llm_chat' => true,
                'embeddings' => true,
                'vector_store' => true,
                'moderation' => false,
                'image_generation' => true,
                'byok' => true,
            ],
            'embedding_dimensions' => [
                'text-embedding-004' => 3072,
            ],
            'recommended_models' => [
                'generation' => 'gemini-2.0-flash-exp',
                'embedding' => 'text-embedding-004',
            ],
            'preferred_vector_store' => 'gemini-file-search',
            'default_solution' => [
                'type' => 'gemini-file-search',
                'vector_store' => [
                    'corpus' => 'default',
                ],
            ],
            'metadata' => [
                'tier' => 'paid',
                'latency' => 'medium',
            ],
        ],
        'groq' => [
            'display_name' => 'Groq',
            'description' => 'Ultra-low latency OpenAI-compatible endpoint for Llama/Mixtral.',
            'capabilities' => [
                'llm_chat' => true,
                'embeddings' => false,
                'vector_store' => false,
                'moderation' => false,
                'image_generation' => false,
                'byok' => true,
            ],
            'recommended_models' => [
                'generation' => 'llama-3.1-70b-versatile',
                'fast_generation' => 'llama-3.1-8b-instant',
            ],
            'preferred_vector_store' => 'ragie',
            'metadata' => [
                'tier' => 'free',
                'latency' => 'low',
            ],
        ],
        'mistral' => [
            'display_name' => 'Mistral AI',
            'description' => 'La Plateforme access to Mixtral + Large models under the experiment tier.',
            'api_key_env' => 'MISTRAL_API_KEY',
            'base_url' => 'https://api.mistral.ai/v1',
            'capabilities' => [
                'llm_chat' => true,
                'embeddings' => true,
                'vector_store' => false,
                'moderation' => false,
                'image_generation' => false,
                'byok' => false,
            ],
            'recommended_models' => [
                'generation' => 'mistral-large-latest',
                'fast_generation' => 'mistral-small-latest',
                'embedding' => 'mistral-embed',
            ],
            'preferred_vector_store' => 'ragie',
            'metadata' => [
                'tier' => 'free',
                'latency' => 'medium',
            ],
        ],
        'openrouter' => [
            'display_name' => 'OpenRouter',
            'description' => 'Router for 60+ hosted LLMs with renewable communal credits.',
            'api_key_env' => 'OPENROUTER_API_KEY',
            'base_url' => 'https://openrouter.ai/api/v1',
            'capabilities' => [
                'llm_chat' => true,
                'embeddings' => false,
                'vector_store' => false,
                'moderation' => false,
                'image_generation' => false,
                'byok' => true,
            ],
            'recommended_models' => [
                'generation' => 'meta-llama/llama-3.1-70b-instruct',
                'fast_generation' => 'qwen/qwen-2.5-14b-instruct',
            ],
            'metadata' => [
                'tier' => 'free',
                'latency' => 'medium',
            ],
        ],
        'pinecone' => [
            'display_name' => 'Pinecone',
            'description' => 'Managed vector store with starter pods for prototypes.',
            'api_key_env' => 'PINECONE_API_KEY',
            'base_url' => 'https://controller.us-east1-gcp.pinecone.io',
            'capabilities' => [
                'llm_chat' => false,
                'embeddings' => false,
                'vector_store' => true,
                'moderation' => false,
                'image_generation' => false,
                'byok' => false,
            ],
            'preferred_vector_store' => 'pinecone',
            'default_solution' => [
                'type' => 'pinecone',
            ],
            'metadata' => [
                'tier' => 'free',
                'latency' => 'medium',
            ],
        ],
        'qdrant' => [
            'display_name' => 'Qdrant Serverless',
            'description' => 'Fully-managed vector store with forever-free tier.',
            'api_key_env' => 'QDRANT_API_KEY',
            'base_url' => 'https://api.qdrant.tech',
            'capabilities' => [
                'llm_chat' => false,
                'embeddings' => false,
                'vector_store' => true,
                'moderation' => false,
                'image_generation' => false,
                'byok' => false,
            ],
            'preferred_vector_store' => 'qdrant',
            'default_solution' => [
                'type' => 'qdrant',
            ],
            'metadata' => [
                'tier' => 'free',
                'latency' => 'medium',
            ],
        ],
        'openai' => [
            'display_name' => 'OpenAI',
            'description' => 'Flagship GPT-4o/4.1 models plus moderation + embeddings.',
            'capabilities' => [
                'llm_chat' => true,
                'embeddings' => true,
                'vector_store' => false,
                'moderation' => true,
                'image_generation' => true,
                'byok' => false,
            ],
            'embedding_dimensions' => [
                'text-embedding-3-small' => 1536,
                'text-embedding-3-large' => 3072,
            ],
            'recommended_models' => [
                'generation' => 'gpt-4o-mini',
                'fast_generation' => 'gpt-4o-mini',
                'embedding' => 'text-embedding-3-small',
                'moderation' => 'omni-moderation-latest',
            ],
            'preferred_vector_store' => 'ragie',
            'default_solution' => [
                'type' => 'ragie',
                'ragie_partition' => 'default',
                'default_options' => [
                    'top_k' => 8,
                    'rerank' => true,
                ],
            ],
            'metadata' => [
                'tier' => 'paid',
                'latency' => 'medium',
            ],
        ],
        'voyage' => [
            'display_name' => 'Voyage AI',
            'description' => 'High-quality embeddings + rerankers tuned for reasoning tasks.',
            'api_key_env' => 'VOYAGE_API_KEY',
            'base_url' => 'https://api.voyageai.com/v1',
            'capabilities' => [
                'llm_chat' => false,
                'embeddings' => true,
                'vector_store' => false,
                'moderation' => false,
                'image_generation' => false,
                'byok' => false,
            ],
            'embedding_dimensions' => [
                'voyage-3-large' => 3072,
            ],
            'recommended_models' => [
                'embedding' => 'voyage-3-large',
            ],
            'metadata' => [
                'tier' => 'free',
                'latency' => 'medium',
            ],
        ],
        'vectara' => [
            'display_name' => 'Vectara',
            'description' => 'Managed RAG stack with ingestion, hallucination defense, and Cerebras/OpenAI routing.',
            'capabilities' => [
                'llm_chat' => true,
                'embeddings' => true,
                'vector_store' => true,
                'moderation' => false,
                'image_generation' => false,
                'byok' => false,
            ],
            'metadata' => [
                'tier' => 'hosted',
                'latency' => 'medium',
            ],
        ],
        'bedrock-kb' => [
            'display_name' => 'AWS Bedrock Knowledge Bases',
            'description' => 'Managed retrieval layer that pairs OpenSearch Serverless with AWS-hosted LLMs.',
            'capabilities' => [
                'llm_chat' => false,
                'embeddings' => false,
                'vector_store' => true,
                'moderation' => false,
                'image_generation' => false,
                'byok' => true,
            ],
            'metadata' => [
                'tier' => 'managed',
                'latency' => 'dependent',
            ],
        ],
        'ragie' => [
            'display_name' => 'Ragie',
            'description' => 'Primary retrieval layer powering ParaGra.',
            'api_key_env' => 'RAGIE_API_KEY',
            'base_url' => 'https://api.ragie.ai',
            'capabilities' => [
                'llm_chat' => false,
                'embeddings' => false,
                'vector_store' => true,
                'moderation' => false,
                'image_generation' => false,
                'byok' => false,
            ],
            'preferred_vector_store' => 'ragie',
            'metadata' => [
                'tier' => 'paid',
                'latency' => 'medium',
            ],
        ],
    ];
}

/**
 * @return array<string, array<string, mixed>>
 */
function loadProviderInsights(?string $path): array
{
    if ($path === null || trim($path) === '') {
        return [];
    }

    if (!is_file($path)) {
        fwrite(STDERR, sprintf("Warning: provider insights file %s not found.\n", $path));

        return [];
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        fwrite(STDERR, sprintf("Warning: unable to read provider insights file %s.\n", $path));

        return [];
    }

    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        fwrite(STDERR, sprintf("Warning: invalid JSON in %s (%s).\n", $path, $exception->getMessage()));

        return [];
    }

    if (!isset($decoded['providers']) || !is_array($decoded['providers'])) {
        fwrite(STDERR, sprintf("Warning: insights payload %s missing providers list.\n", $path));

        return [];
    }

    $index = [];
    foreach ($decoded['providers'] as $entry) {
        if (!is_array($entry) || !isset($entry['slug']) || !is_string($entry['slug']) || $entry['slug'] === '') {
            continue;
        }

        $index[$entry['slug']] = $entry;
    }

    return $index;
}

/**
 * @return array<string, list<string>>
 */
function insightMappings(): array
{
    return [
        'askyoda' => ['eden-askyoda'],
        'cloudflare' => ['cloudflare-workers-ai'],
        'dify' => ['dify-platform'],
        'gemini' => ['google-gemini-flash', 'google-gemini-embedding', 'google-gemini-file-search'],
        'groq' => ['groq-llama'],
        'mistral' => ['mistral-la-plateforme'],
        'openrouter' => ['openrouter'],
        'pinecone' => ['pinecone-starter'],
        'qdrant' => ['qdrant-cloud-free'],
        'voyage' => ['voyage-embeddings'],
        'vectara' => ['vectara-platform'],
        'bedrock-kb' => ['aws-bedrock-knowledge-bases'],
    ];
}

/**
 * @param array<string, mixed> $metadata
 * @param array<string, array<string, mixed>> $insights
 * @param array<string, list<string>> $insightMap
 * @return array<string, mixed>
 */
function applyInsightMetadata(
    string $slug,
    array $metadata,
    array $insights,
    array $insightMap
): array {
    $insightSlugs = $insightMap[$slug] ?? [];
    if ($insightSlugs === [] && isset($insights[$slug])) {
        $insightSlugs = [$slug];
    }

    $entries = [];
    foreach ($insightSlugs as $insightSlug) {
        if (!isset($insights[$insightSlug])) {
            continue;
        }

        $normalized = normalizeInsightEntry($insights[$insightSlug]);
        if ($normalized !== []) {
            $entries[$insightSlug] = $normalized;
        }
    }

    if ($entries !== []) {
        $metadata['insights'] = $entries;
    }

    return $metadata;
}

/**
 * @param array<string, mixed> $entry
 * @return array<string, mixed>
 */
function normalizeInsightEntry(array $entry): array
{
    if (!isset($entry['slug']) || !is_string($entry['slug']) || trim($entry['slug']) === '') {
        return [];
    }

    $normalized = [
        'slug' => trim((string) $entry['slug']),
    ];

    if (isset($entry['name']) && $entry['name'] !== '') {
        $normalized['name'] = (string) $entry['name'];
    }

    if (isset($entry['category']) && $entry['category'] !== '') {
        $normalized['category'] = (string) $entry['category'];
    }

    foreach (['reset_window', 'commercial_use', 'notes'] as $key) {
        if (isset($entry[$key]) && $entry[$key] !== '') {
            $normalized[$key] = (string) $entry[$key];
        }
    }

    $modalities = normalizeStringList($entry['modalities'] ?? []);
    if ($modalities !== []) {
        $normalized['modalities'] = $modalities;
    }

    $roles = normalizeStringList($entry['recommended_roles'] ?? []);
    if ($roles !== []) {
        $normalized['recommended_roles'] = $roles;
    }

    $freeTier = normalizeFreeTier($entry['free_quota'] ?? []);
    if ($freeTier !== []) {
        $normalized['free_tier'] = $freeTier;
    }

    if (isset($entry['sources']) && is_array($entry['sources'])) {
        $sources = [];
        foreach ($entry['sources'] as $source) {
            $normalizedSource = normalizeInsightSource($source);
            if ($normalizedSource !== null) {
                $sources[] = $normalizedSource;
            }
        }

        if ($sources !== []) {
            $normalized['sources'] = $sources;
        }
    }

    return $normalized;
}

/**
 * @param mixed $values
 * @return list<string>
 */
function normalizeStringList(mixed $values): array
{
    if (!is_array($values)) {
        return [];
    }

    $result = [];
    foreach ($values as $value) {
        if ($value === null) {
            continue;
        }

        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            continue;
        }

        $result[] = $stringValue;
    }

    return array_values(array_unique($result));
}

/**
 * @param mixed $freeQuota
 * @return array<string, mixed>
 */
function normalizeFreeTier(mixed $freeQuota): array
{
    if (!is_array($freeQuota)) {
        return [];
    }

    $normalized = [];
    foreach ($freeQuota as $key => $value) {
        if ($key === 'models' && is_array($value)) {
            $models = [];
            foreach ($value as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $modelEntry = [];
                if (isset($row['model']) && $row['model'] !== '') {
                    $modelEntry['model'] = (string) $row['model'];
                }
                foreach (['requests_per_day', 'requests_per_minute', 'tokens_per_minute'] as $metric) {
                    if (isset($row[$metric])) {
                        $modelEntry[$metric] = (int) $row[$metric];
                    }
                }

                if ($modelEntry !== []) {
                    $models[] = $modelEntry;
                }
            }

            if ($models !== []) {
                $normalized['models'] = $models;
            }
            continue;
        }

        if ($value === null || $value === '') {
            continue;
        }

        if (is_numeric($value)) {
            $normalized[(string) $key] = (int) $value;
        } else {
            $normalized[(string) $key] = $value;
        }
    }

    return $normalized;
}

/**
 * @param mixed $source
 * @return array<string, int|string>|null
 */
function normalizeInsightSource(mixed $source): ?array
{
    if (!is_array($source) || !isset($source['path']) || !is_string($source['path']) || $source['path'] === '') {
        return null;
    }

    $entry = [
        'path' => $source['path'],
    ];

    if (isset($source['sha256']) && is_string($source['sha256']) && $source['sha256'] !== '') {
        $entry['sha256'] = $source['sha256'];
    }

    if (isset($source['start_line'])) {
        $entry['start_line'] = (int) $source['start_line'];
    }

    if (isset($source['end_line'])) {
        $entry['end_line'] = (int) $source['end_line'];
    }

    return $entry;
}

function writeCatalogFiles(string $outputPath, array $catalog): void
{
    $json = json_encode($catalog, JSON_FLAGS);
    if ($json === false) {
        throw new RuntimeException('Failed to encode catalog JSON.');
    }

    $jsonPath = $outputPath . '/catalog.json';
    file_put_contents($jsonPath, $json . PHP_EOL);

    $phpPath = $outputPath . '/catalog.php';
    $php = <<<PHP
<?php

declare(strict_types=1);

// this_file: paragra-php/config/providers/catalog.php

return %s;

PHP;

    file_put_contents($phpPath, sprintf($php, var_export($catalog, true)));
}

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}
