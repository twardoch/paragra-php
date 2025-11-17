# Quickstart

This guide shows you how to run your first RAG query with ParaGra.

## Prerequisites

1. ParaGra installed via Composer
2. `config/paragra.php` configured with at least one provider pool
3. Required API keys in environment variables

## Basic retrieval

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use ParaGra\ParaGra;

$config = require __DIR__ . '/config/paragra.php';
$paragra = ParaGra::fromConfig($config);

$response = $paragra->retrieve('What is ParaGra?', ['top_k' => 6]);

foreach ($response->getChunks() as $chunk) {
    echo "Text: {$chunk['text']}\n";
    echo "Score: {$chunk['score']}\n\n";
}
```

## RAG answer with generation

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use ParaGra\ParaGra;

$config = require __DIR__ . '/config/paragra.php';
$paragra = ParaGra::fromConfig($config);

$result = $paragra->answer('Explain ParaGra in one sentence', [
    'retrieval' => ['top_k' => 8],
    'generation' => ['temperature' => 0.2],
]);

echo "Answer: {$result['answer']}\n";
echo "Context chunks: " . count($result['context']->getChunks()) . "\n";
```

## With moderation

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use ParaGra\Moderation\OpenAiModerator;
use ParaGra\ParaGra;

$config = require __DIR__ . '/config/paragra.php';
$paragra = ParaGra::fromConfig($config)
    ->withModeration(OpenAiModerator::fromEnv());

try {
    $answer = $paragra->answer('Tell me about AI safety', [
        'retrieval' => ['top_k' => 6],
        'generation' => ['temperature' => 0.3],
    ]);

    echo $answer['answer'];
} catch (\ParaGra\Moderation\ModerationException $e) {
    echo "Content flagged: {$e->getMessage()}\n";
}
```

## Pool rotation and fallback

ParaGra automatically rotates through keys in your priority pools and falls back to the next pool when providers fail:

```php
$config = [
    'priority_pools' => [
        // Pool 1: Free tier (Cerebras with Ragie)
        [
            ['provider' => 'cerebras', 'api_key' => getenv('CEREBRAS_API_KEY_1')],
            ['provider' => 'cerebras', 'api_key' => getenv('CEREBRAS_API_KEY_2')],
        ],
        // Pool 2: Paid fallback (OpenAI)
        [
            ['provider' => 'openai', 'api_key' => getenv('OPENAI_API_KEY')],
        ],
    ],
];

$paragra = ParaGra::fromConfig($config);

// Automatically tries Cerebras keys first, falls back to OpenAI if needed
$answer = $paragra->answer('What is RAG?');
```

## Using PoolBuilder

For automated pool configuration from environment variables:

```bash
php tools/pool_builder.php --preset=free-tier --format=json
```

Or programmatically:

```php
use ParaGra\Planner\PoolBuilder;
use ParaGra\ProviderCatalog\ProviderDiscovery;

$catalog = ProviderDiscovery::fromFile(__DIR__ . '/config/providers/catalog.php');
$builder = PoolBuilder::fromGlobals($catalog);

$config = [
    'provider_catalog' => __DIR__ . '/config/providers/catalog.php',
    'priority_pools' => $builder->build(PoolBuilder::PRESET_FREE),
];

$paragra = ParaGra::fromConfig($config);
```

## Next steps

- Learn about [Configuration](configuration.md) for advanced pool setups
- Explore [Provider Catalog](../guides/provider-catalog.md) for catalog-driven configuration
- Read [Embedding Providers](../guides/embeddings.md) to generate and store vectors
- Try [Hybrid Retrieval](../guides/hybrid-retrieval.md) for combined Ragie + vector store queries
