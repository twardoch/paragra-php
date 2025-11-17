# CLI Tools

ParaGra provides command-line utilities for catalog management, pool building, and testing.

## Provider catalog sync

Refresh the provider catalog from the `vexy-co-model-catalog` repository:

```bash
php tools/sync_provider_catalog.php [OPTIONS]
```

**Options:**
- `--source=PATH` — Path to `vexy-co-model-catalog` directory (default: `../vexy-co-model-catalog`)
- `--insights` — Include capability insights in output

**Output files:**
- `config/providers/catalog.json` — JSON catalog
- `config/providers/catalog.php` — PHP array version

**Example:**
```bash
cd paragra-php
php tools/sync_provider_catalog.php --insights
```

The script:
1. Parses `external/dump_models.py` from the catalog repo
2. Ingests `models/*.json` files
3. Merges ParaGra-specific capability presets
4. Writes normalized catalog files

## Pool builder

Generate priority pools from environment variables:

```bash
php tools/pool_builder.php [OPTIONS]
```

**Options:**
- `--preset=NAME` — Pool preset (free-tier, hybrid, hosted)
- `--format=FORMAT` — Output format (json, php, yaml)
- `--output=FILE` — Write to file instead of stdout

**Presets:**

### free-tier
Free-tier providers only (Gemini Flash Lite + Groq + Cerebras):

```bash
php tools/pool_builder.php --preset=free-tier --format=php
```

Required environment variables:
- `GOOGLE_API_KEY` or `GEMINI_API_KEY`
- `GEMINI_DATASTORE_ID` or `GEMINI_CORPUS_ID`
- `GROQ_API_KEY`
- `RAGIE_API_KEY`

Optional:
- `CEREBRAS_API_KEY_*` — Additional rotation keys

### hybrid
Free rotation with paid fallback (OpenAI/Pinecone):

```bash
php tools/pool_builder.php --preset=hybrid --format=php
```

Required environment variables:
- All free-tier variables
- `OPENAI_API_KEY`
- `PINECONE_API_KEY`
- `PINECONE_BASE_URL`

### hosted
Managed services (AskYoda, Vectara, AWS Bedrock):

```bash
php tools/pool_builder.php --preset=hosted --format=php
```

Required environment variables:
- `ASKYODA_API_KEY`

**Programmatic usage:**

```php
use ParaGra\Planner\PoolBuilder;
use ParaGra\ProviderCatalog\ProviderDiscovery;

$catalog = ProviderDiscovery::fromFile(__DIR__ . '/config/providers/catalog.php');
$builder = PoolBuilder::fromGlobals($catalog);

$pools = $builder->build(PoolBuilder::PRESET_FREE, [
    'ragie_partition' => getenv('RAGIE_PARTITION') ?: 'default',
    'ragie_defaults' => ['top_k' => 8],
]);
```

## Example scripts

### Moderated answer
Run a RAG query with content moderation:

```bash
export RAGIE_API_KEY=sk_live_...
export OPENAI_API_KEY=sk-...
export CEREBRAS_API_KEY_1=...

php examples/moderated_answer.php "What is ParaGra?"
```

### Hybrid pipeline
Test hybrid Ragie + vector store retrieval:

```bash
export RAGIE_API_KEY=sk_live_...
export OPENAI_API_KEY=sk-...
export PINECONE_API_KEY=...
export PINECONE_BASE_URL=https://...
export HYBRID_STORE=pinecone
export HYBRID_SEED_FIRST=1

php examples/vector-stores/hybrid_pipeline.php "Explain RAG"
```

Supported `HYBRID_STORE` values:
- `pinecone`
- `weaviate`
- `qdrant`
- `chroma`
- `gemini`

### External search fallback
Test twat-search integration:

```bash
export RAGIE_API_KEY=sk_live_...
export TWAT_SEARCH_BIN=twat-search
export TWAT_SEARCH_ENGINES=brave,duckduckgo
export BRAVE_API_KEY=...
export TAVILY_API_KEY=...

php examples/external-search/twat_search_fallback.php "Latest AI news"
```

## Testing commands

Run all QA tools:
```bash
composer qa
```

Individual checks:
```bash
composer test      # PHPUnit tests
composer lint      # PHP-CS-Fixer
composer stan      # PHPStan static analysis
composer psalm     # Psalm static analysis
```

## Next steps

- Read [Documentation Workflow](docs.md) for Zensical documentation tools
- Explore [Pool Builder](../how-to/pool-builder.md) for automated configuration
- Review [Testing](../how-to/testing.md) for test suite details
