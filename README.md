---
this_file: paragra-php/README.md
---

# ParaGra PHP

Provider-agnostic PHP toolkit for Retrieval-Augmented Generation (RAG) and LLM orchestration. ParaGra sits above individual provider libraries — it routes queries, handles fallbacks, rotates API keys, and combines retrieval with generation in a single call.

## What is RAG?

RAG (Retrieval-Augmented Generation) is a technique that improves AI-generated answers by first searching a knowledge base for relevant content, then passing that content to a language model as context. Instead of asking the LLM to answer from memory (which can hallucinate), you give it real, relevant documents to work from.

The typical flow:

```
User query → vector search → retrieve relevant passages → LLM generates answer grounded in those passages
```

ParaGra orchestrates this entire pipeline: retrieval across multiple providers, optional moderation, and generation with automatic fallback if one provider fails.

## What it does

- **Provider pools** — Configure multiple retrieval and generation providers. ParaGra tries them in priority order and falls back automatically on failure or rate limiting.
- **Key rotation** — Multiple API keys per provider rotate round-robin to distribute load.
- **Vector store adapters** — Unified interface for Pinecone, Weaviate, Qdrant, Chroma, and Gemini File Search.
- **Embedding providers** — Standardized embedding generation across OpenAI, Gemini, and others.
- **Moderation** — Optional content screening before retrieval or generation.
- **External search enrichment** — Augment retrieval with web search results.
- **Single `answer()` call** — Combines retrieval + generation in one method.

## Install

```bash
composer require vexy/paragra-php
```

Or for local development alongside `ragie-php`:

```bash
git clone https://github.com/twardoch/paragra-php
cd paragra-php
composer install
```

## Quick start

```php
use ParaGra\ParaGra;

$config = require __DIR__ . '/config/paragra.php';
$paragra = ParaGra::fromConfig($config);

// Retrieve relevant passages for a query
$passages = $paragra->retrieve('What is the refund policy?', ['top_k' => 5]);

// Or do retrieval + generation in one step
$answer = $paragra->answer('What is the refund policy?', [
    'retrieval' => ['top_k' => 5],
    'generation' => ['temperature' => 0.2],
]);

echo $answer->getText();
```

## Configuration

Copy the example config and fill in your provider API keys:

```bash
cp config/paragra.example.php config/paragra.php
```

The config defines `priority_pools` — ordered lists of provider specs. ParaGra works through the list until one succeeds:

```php
return [
    'priority_pools' => [
        'retrieval' => [
            ['provider' => 'ragie',  'api_key' => $_ENV['RAGIE_API_KEY'],  'priority' => 1],
            ['provider' => 'gemini', 'api_key' => $_ENV['GEMINI_API_KEY'], 'priority' => 2],
        ],
        'generation' => [
            ['provider' => 'openai', 'api_key' => $_ENV['OPENAI_API_KEY'], 'priority' => 1],
        ],
    ],
];
```

## Provider catalog

ParaGra ships a catalog of supported models and providers. Refresh it from the upstream source:

```bash
php tools/sync_provider_catalog.php --insights
```

Build pre-configured pools from the catalog:

```bash
php tools/pool_builder.php --preset=free-tier
php tools/pool_builder.php --preset=hybrid
php tools/pool_builder.php --preset=hosted
```

## Architecture

```
paragra-php/
├── src/
│   ├── ParaGra.php           # Main entry point
│   ├── Pipeline/             # Orchestration pipeline
│   ├── Providers/            # Provider catalog and specs
│   ├── VectorStore/          # Adapters: Pinecone, Weaviate, Qdrant, Chroma
│   ├── Embedding/            # Embedding providers: OpenAI, Gemini, ...
│   ├── Moderation/           # Content moderation (OpenAI, etc.)
│   ├── ExternalSearch/       # Web search enrichment
│   └── Router/               # Priority pool routing and fallback logic
├── config/
│   ├── paragra.example.php   # Template configuration
│   └── providers/catalog.php # Model/provider metadata
├── tests/                    # PHPUnit test suite
├── examples/                 # Usage snippets
└── tools/                    # CLI helpers for catalog sync and pool building
```

## Relationship to ragie-php

`paragra-php` depends on [`ragie-php`](../ragie-php) for Ragie-specific retrieval. The `composer.json` uses a local path repository during development so changes to `ragie-php` are reflected immediately.

`ragie-php` handles the Ragie API directly. `paragra-php` adds multi-provider orchestration on top.

## Documentation

Full API reference, provider matrix, fallback algorithm, migration guide, and style guide:

- [Provider Matrix](src-docs/architecture/provider-matrix.md) — all adapters at a glance
- [Fallback Algorithm](src-docs/architecture/fallback-algorithm.md) — how rotation and tiers work
- [`answer()` Reference](src-docs/reference/answer-method.md) — full parameter and return-type docs
- [Migration Guide](src-docs/guides/migration.md) — move from ragie-php, openai-php, or raw SDK calls
- [Style Guide](src-docs/STYLE_GUIDE.md) — coding, naming, and documentation conventions

## Testing

```bash
composer qa           # lint + PHPStan + Psalm + PHPUnit
composer test         # tests only
composer lint         # fix code style
composer stan         # PHPStan static analysis
composer psalm        # Psalm static analysis
```

Target coverage: ≥90%.

## Requirements

- PHP 8.3+
- Composer
- At least one configured provider (Ragie, OpenAI, Gemini, etc.)

## License

MIT
