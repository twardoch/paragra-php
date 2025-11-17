# Pool Builder

Automate provider pool configuration from environment variables.

## Overview

`PoolBuilder` scans environment variables and generates priority pools based on presets.

## CLI usage

```bash
php tools/pool_builder.php --preset=free-tier --format=php
```

**Options:**
- `--preset=NAME` — Pool preset (free-tier, hybrid, hosted)
- `--format=FORMAT` — Output format (json, php, yaml)
- `--output=FILE` — Write to file

## Presets

### free-tier
Zero-cost providers (Gemini + Groq + Cerebras):

```bash
export GOOGLE_API_KEY=...
export GEMINI_DATASTORE_ID=...
export GROQ_API_KEY=...
export RAGIE_API_KEY=...
export CEREBRAS_API_KEY_1=...

php tools/pool_builder.php --preset=free-tier
```

### hybrid
Free + paid fallback:

```bash
# All free-tier variables plus:
export OPENAI_API_KEY=...
export PINECONE_API_KEY=...

php tools/pool_builder.php --preset=hybrid
```

### hosted
Managed services:

```bash
export ASKYODA_API_KEY=...

php tools/pool_builder.php --preset=hosted
```

## Programmatic usage

```php
use ParaGra\Planner\PoolBuilder;
use ParaGra\ProviderCatalog\ProviderDiscovery;

$catalog = ProviderDiscovery::fromFile(__DIR__ . '/config/providers/catalog.php');
$builder = PoolBuilder::fromGlobals($catalog);

$pools = $builder->build(PoolBuilder::PRESET_FREE, [
    'ragie_partition' => 'default',
    'ragie_defaults' => ['top_k' => 8],
]);
```

## In config files

```php
// config/paragra.php
use ParaGra\Planner\PoolBuilder;
use ParaGra\ProviderCatalog\ProviderDiscovery;

$catalog = ProviderDiscovery::fromFile(__DIR__ . '/providers/catalog.php');
$builder = PoolBuilder::fromGlobals($catalog);

return [
    'provider_catalog' => __DIR__ . '/providers/catalog.php',
    'priority_pools' => $builder->build(PoolBuilder::PRESET_FREE),
];
```

See [Configuration](../getting-started/configuration.md) for details.
