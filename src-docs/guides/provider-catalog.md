# Provider Catalog

The provider catalog provides metadata-driven configuration for AI models and services.

## Overview

Instead of hard-coding provider capabilities, ParaGra uses `config/providers/catalog.php` to:

- Discover available providers and models
- Filter by capability (generation, embeddings, retrieval)
- Auto-configure dimensions, endpoints, and defaults
- Build `ProviderSpec` instances from environment variables

## Generating the catalog

Sync from the `vexy-co-model-catalog` repository:

```bash
cd paragra-php
php tools/sync_provider_catalog.php --insights
```

**Output files:**
- `config/providers/catalog.json` — JSON catalog
- `config/providers/catalog.php` — PHP array version

## Catalog structure

Each provider entry includes:

```php
[
    'slug' => 'openai',
    'name' => 'OpenAI',
    'capabilities' => ['generation', 'embeddings', 'moderation'],
    'models' => [
        'generation' => ['gpt-4o', 'gpt-4o-mini'],
        'embeddings' => ['text-embedding-3-small', 'text-embedding-3-large'],
    ],
    'embedding_dimensions' => [
        'text-embedding-3-small' => 1536,
        'text-embedding-3-large' => 3072,
    ],
    'vector_store_recommendations' => ['pinecone', 'weaviate', 'qdrant'],
]
```

## Using the catalog

### Load and discover
```php
use ParaGra\ProviderCatalog\ProviderDiscovery;

$catalog = ProviderDiscovery::fromFile(__DIR__ . '/config/providers/catalog.php');

// Filter for embedding providers
$embeddingProviders = $catalog->filterByCapability('embeddings');

// Get a specific provider
$openai = $catalog->getProvider('openai');
```

### Build ProviderSpec from catalog
```php
putenv('OPENAI_API_KEY=sk-...');
$spec = $catalog->buildProviderSpec('openai');

// $spec now contains:
// - api_key from environment
// - model from catalog defaults
// - embedding dimensions
// - vector store recommendations
```

### Catalog-driven pool configuration
```php
return [
    'provider_catalog' => __DIR__ . '/providers/catalog.php',
    'priority_pools' => [
        [
            [
                'catalog' => [
                    'slug' => 'cerebras',
                    'model_type' => 'generation',
                    'overrides' => [
                        'api_key' => getenv('CEREBRAS_API_KEY_1'),
                        'solution' => [
                            'ragie_api_key' => getenv('RAGIE_API_KEY'),
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

## Capability filtering

```php
// Get providers that support embeddings
$embeddingProviders = $catalog->filterByCapability('embeddings');

// Get providers for text generation
$generationProviders = $catalog->filterByCapability('generation');

// Get vector store providers
$vectorStores = $catalog->filterByCapability('vector_store');

// Get retrieval providers
$retrievalProviders = $catalog->filterByCapability('retrieval');
```

## Recommended models

The catalog includes curated model recommendations:

```php
$provider = $catalog->getProvider('openai');
$recommendedGeneration = $provider['models']['generation'][0]; // gpt-4o
$recommendedEmbedding = $provider['models']['embeddings'][0];  // text-embedding-3-small
```

## Vector store recommendations

Each embedding provider lists compatible vector stores:

```php
$provider = $catalog->getProvider('openai');
$vectorStores = $provider['vector_store_recommendations'];
// ['pinecone', 'weaviate', 'qdrant', 'chroma']
```

## Embedding dimensions

The catalog tracks embedding dimensions per model:

```php
$provider = $catalog->getProvider('cohere');
$dimensions = $provider['embedding_dimensions'];
// [
//     'embed-english-v3.0' => 1024,
//     'embed-english-light-v3.0' => 384,
//     'embed-multilingual-v3.0' => 1024,
// ]
```

## Next steps

- Use [Pool Builder](../how-to/pool-builder.md) for automated catalog-driven configuration
- Read [Embedding Providers](embeddings.md) to generate vectors
- Explore [Vector Store Adapters](vector-stores.md) for storage options
