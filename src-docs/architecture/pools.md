# Provider Pools

ParaGra uses **priority pools** to manage provider rotation and automatic fallback.

## Pool structure

A pool is an ordered list of provider specifications:

```php
$config = [
    'priority_pools' => [
        // Pool 1: Free tier
        [
            ['provider' => 'cerebras', 'api_key' => getenv('CEREBRAS_API_KEY_1')],
            ['provider' => 'cerebras', 'api_key' => getenv('CEREBRAS_API_KEY_2')],
            ['provider' => 'groq', 'api_key' => getenv('GROQ_API_KEY')],
        ],
        // Pool 2: Paid fallback
        [
            ['provider' => 'openai', 'api_key' => getenv('OPENAI_API_KEY')],
        ],
    ],
];
```

## Rotation logic

### Within a pool
1. Start with the first provider
2. If it fails, try the next provider in the same pool
3. Continue until a provider succeeds or all fail
4. Log which provider was used (hashed key fingerprint for privacy)

### Between pools
1. Only move to the next pool when all providers in the current pool fail
2. Pools are tried in order (Pool 1 → Pool 2 → Pool 3, etc.)
3. If all pools fail, throw an exception with diagnostics

## Rotation strategies

### Free-tier pools
Exhaust every key before falling back:

```php
[
    'priority_pools' => [
        [
            ['provider' => 'cerebras', 'api_key' => getenv('CEREBRAS_API_KEY_1')],
            ['provider' => 'cerebras', 'api_key' => getenv('CEREBRAS_API_KEY_2')],
            ['provider' => 'cerebras', 'api_key' => getenv('CEREBRAS_API_KEY_3')],
        ],
    ],
]
```

ParaGra tries all three Cerebras keys before moving to the next pool.

### Hybrid pools
Free tier first, paid fallback:

```php
[
    'priority_pools' => [
        // Pool 1: Free
        [
            ['provider' => 'cerebras', 'api_key' => getenv('CEREBRAS_API_KEY_1')],
            ['provider' => 'groq', 'api_key' => getenv('GROQ_API_KEY')],
        ],
        // Pool 2: Paid
        [
            ['provider' => 'openai', 'api_key' => getenv('OPENAI_API_KEY')],
        ],
    ],
]
```

Tries Cerebras and Groq first. Only uses OpenAI if both fail.

### Hosted pools
Single-shot attempts with managed services:

```php
[
    'priority_pools' => [
        [
            ['provider' => 'askyoda', 'api_key' => getenv('ASKYODA_API_KEY')],
        ],
    ],
]
```

## Key rotation

ParaGra supports multiple keys for the same provider:

```php
[
    ['provider' => 'cerebras', 'api_key' => getenv('CEREBRAS_API_KEY_1')],
    ['provider' => 'cerebras', 'api_key' => getenv('CEREBRAS_API_KEY_2')],
]
```

When the first key hits a rate limit, ParaGra automatically tries the second key.

## Retry behavior

ParaGra retries failed requests with exponential backoff:

- **Retryable errors**: Rate limits (429), temporary network issues
- **Non-retryable errors**: Authentication failures (401), invalid requests (400)
- **Max retries**: Configurable per provider (default: 2)

## Logging and diagnostics

ParaGra logs which provider was used (with hashed key fingerprints):

```
Ragie retrieval via Pool 1, Provider cerebras (key: a1b2c3...)
```

Key fingerprints are SHA-256 hashes truncated to 6 characters for privacy.

## Provider specifications

Each provider spec includes:

- `provider` — Provider slug (cerebras, openai, ragie, etc.)
- `api_key` — Authentication credential
- `solution` — Nested provider config (e.g., Ragie API key for Cerebras)
- `model` — Model identifier (optional, uses provider default)
- `latency_tier` — Performance hint (low/medium/high)
- `cost_ceiling` — Budget constraint (0.00 for free tier)
- `compliance` — Regulatory metadata (internal/gdpr/hipaa)

## Catalog integration

Use the provider catalog to avoid hard-coding metadata:

```php
[
    'catalog' => [
        'slug' => 'cerebras',
        'model_type' => 'generation',
        'overrides' => [
            'api_key' => getenv('CEREBRAS_API_KEY_1'),
        ],
    ],
    'latency_tier' => 'low',
    'cost_ceiling' => 0.00,
]
```

The catalog provides default models, dimensions, and capabilities.

## PoolBuilder automation

Generate pools automatically from environment variables:

```php
use ParaGra\Planner\PoolBuilder;
use ParaGra\ProviderCatalog\ProviderDiscovery;

$catalog = ProviderDiscovery::fromFile(__DIR__ . '/providers/catalog.php');
$builder = PoolBuilder::fromGlobals($catalog);

$pools = $builder->build(PoolBuilder::PRESET_FREE);
```

PoolBuilder inspects environment variables and constructs appropriate pools.

## Next steps

- Read [Vector Stores](vector-stores.md) for storage adapter architecture
- Use [Pool Builder](../how-to/pool-builder.md) to automate configuration
- Explore [Provider Catalog](../guides/provider-catalog.md) for metadata-driven pools
