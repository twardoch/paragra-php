# Configuration

ParaGra configuration happens in `config/paragra.php`, which defines provider pools, rotation logic, and optional integrations.

## Basic structure

```php
<?php

return [
    'priority_pools' => [
        // Pool 1: Free-tier providers
        [
            [
                'provider' => 'cerebras',
                'api_key' => (string) getenv('CEREBRAS_API_KEY_1'),
                'solution' => [
                    'ragie_api_key' => (string) getenv('RAGIE_API_KEY'),
                ],
            ],
        ],
        // Pool 2: Paid fallback
        [
            [
                'provider' => 'openai',
                'api_key' => (string) getenv('OPENAI_API_KEY'),
            ],
        ],
    ],
];
```

## Environment variables

ParaGra reads secrets from environment variables to keep credentials out of source control:

### Core providers
- `RAGIE_API_KEY` — Ragie retrieval (required for most pools)
- `OPENAI_API_KEY` — OpenAI chat/embeddings
- `GOOGLE_API_KEY` / `GEMINI_API_KEY` — Google Gemini services

### Free-tier providers
- `CEREBRAS_API_KEY_*` — Cerebras inference (fast, free tier)
- `GROQ_API_KEY` — Groq inference
- `GEMINI_DATASTORE_ID` / `GEMINI_CORPUS_ID` — Gemini File Search

### Vector stores
- `PINECONE_API_KEY` / `PINECONE_BASE_URL` — Pinecone vector database
- `WEAVIATE_API_KEY` — Weaviate vector database
- `QDRANT_API_KEY` — Qdrant vector database
- `CHROMA_API_TOKEN` — ChromaDB authentication

### Embedding providers
- `COHERE_API_KEY` — Cohere embeddings
- `VOYAGE_API_KEY` — Voyage AI embeddings

## Provider catalog integration

Use the provider catalog for metadata-driven configuration:

```php
<?php

return [
    'provider_catalog' => __DIR__ . '/providers/catalog.php',
    'priority_pools' => [
        [
            [
                'catalog' => [
                    'slug' => 'cerebras',
                    'model_type' => 'generation',
                    'overrides' => [
                        'api_key' => (string) getenv('CEREBRAS_API_KEY_1'),
                        'solution' => [
                            'ragie_api_key' => (string) getenv('RAGIE_API_KEY'),
                        ],
                    ],
                ],
                'latency_tier' => 'low',
                'cost_ceiling' => 0.00,
            ],
        ],
    ],
];
```

The catalog (`config/providers/catalog.php`) is generated via:
```bash
php tools/sync_provider_catalog.php
```

## Pool presets with PoolBuilder

For automated configuration from environment variables:

```php
use ParaGra\Planner\PoolBuilder;
use ParaGra\ProviderCatalog\ProviderDiscovery;

$catalog = ProviderDiscovery::fromFile(__DIR__ . '/providers/catalog.php');
$builder = PoolBuilder::fromGlobals($catalog);

return [
    'provider_catalog' => __DIR__ . '/providers/catalog.php',
    'priority_pools' => $builder->build(PoolBuilder::PRESET_FREE, [
        'ragie_partition' => getenv('RAGIE_PARTITION') ?: 'default',
        'ragie_defaults' => ['top_k' => 8],
    ]),
];
```

Available presets:
- `PRESET_FREE` — Gemini Flash Lite + Groq + Cerebras (all free tier)
- `PRESET_HYBRID` — Free rotation with OpenAI/Pinecone fallback
- `PRESET_HOSTED` — AskYoda + Vectara recommendations

## Pool rotation logic

ParaGra tries providers in order within each pool:

1. **Within a pool**: Round-robin through all providers, exhausting retries before moving to next provider
2. **Between pools**: Only move to next pool when all providers in current pool fail
3. **Free-tier pools**: Attempt every key before falling back
4. **Hybrid pools**: Default to two attempts per key
5. **Hosted pools**: Single-shot attempts

## Scenario configs

The `examples/config/` directory contains ready-made configurations:

- `free_tier.php` — Free providers only (Cerebras, Groq, Gemini)
- `hybrid.php` — Free rotation with paid fallback
- `hosted.php` — Managed services (AskYoda, Vectara)
- `multi_key_rotation.php` — Multiple Cerebras keys with exhaustive rotation

Copy a scenario config to `config/paragra.php` or merge elements into your custom setup.

## Next steps

- Read [Provider Pools](../architecture/pools.md) for rotation internals
- Use [Pool Builder](../how-to/pool-builder.md) to generate configs automatically
- Explore [Provider Catalog](../guides/provider-catalog.md) for capability-based filtering
