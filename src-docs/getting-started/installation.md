# Installation

## Requirements
- PHP 8.1+
- Composer 2.7+
- `ext-json`, `ext-mbstring`, and `ext-curl`
- API keys for providers you plan to use (Ragie, OpenAI, Gemini, etc.)

## Install via Composer

For integration into your project:
```bash
composer require vexy/paragra-php:^1.0@dev
```

During local development inside the monorepo:
```bash
cd paragra-php
composer install
```

## Configure environment

ParaGra reads API keys from environment variables. At minimum, you need Ragie credentials:

```bash
export RAGIE_API_KEY=sk_live_...
```

Optional provider keys (add as needed):
```bash
export OPENAI_API_KEY=sk-...
export GOOGLE_API_KEY=...
export GEMINI_DATASTORE_ID=...
export PINECONE_API_KEY=...
export CEREBRAS_API_KEY_1=...
export GROQ_API_KEY=...
```

## Create configuration file

Copy the example config and customize for your pools:
```bash
cd paragra-php
cp config/paragra.example.php config/paragra.php
```

Edit `config/paragra.php` to define your provider pools. See [Configuration](configuration.md) for details.

## Verify the install

```bash
php -r "require 'vendor/autoload.php'; echo \ParaGra\ParaGra::class, PHP_EOL;"
```

When Composer autoloads without errors, you're ready to orchestrate RAG queries.

## Next steps

- Read [Quickstart](quickstart.md) to run your first RAG query
- Review [Configuration](configuration.md) for pool setup and provider rotation
- Explore [Provider Catalog](../guides/provider-catalog.md) for catalog-driven configuration
