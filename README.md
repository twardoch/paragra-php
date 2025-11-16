---
this_file: paragra-php/README.md
---

# ParaGra PHP Toolkit

ParaGra is a provider-agnostic PHP toolkit for orchestrating Retrieval-Augmented Generation (RAG) and LLM calls across Ragie, Gemini File Search, AskYoda, and future providers.

## Project Status

- **Phase:** Early scaffolding (see ../PLAN.md §12-13)
- **Goal:** Universal RAG+LLM coordinator that keeps Ragie-specific logic inside `ragie-php`

## Quick Start

Working in `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php`:

1. Copy the template config and fill in `.env` secrets.
   ```bash
   cp config/paragra.example.php config/paragra.php
   ```
2. (Optional) swap in one of the scenario configs under `examples/config/`.
3. Install dependencies + run tests.
   ```bash
   composer install
   composer test
   ```
4. Call ParaGra from your app:
   ```php
   use ParaGra\ParaGra;
   use ParaGra\Moderation\OpenAiModerator;

   $config = require __DIR__ . '/config/paragra.php';
   $paragra = ParaGra::fromConfig($config)
       ->withModeration(OpenAiModerator::fromEnv());

   $answer = $paragra->answer('Explain ParaGra', [
       'retrieval' => ['top_k' => 8],
       'generation' => ['temperature' => 0.2],
   ]);
   ```
5. Wire the same instance into `ask.vexy.art/public/{rag,text}` to keep endpoints slim.

## Directory Layout

```
paragra-php/
├── src/            # Library source (PSR-4, namespace ParaGra\)
├── tests/          # PHPUnit test suite
├── docs/           # Design notes and future architecture docs
├── examples/       # Usage snippets + smoke tests
├── config/         # Example pool/provider configuration files
├── composer.json   # Project metadata + dependencies
```

## Getting Started

```bash
cd paragra-php
composer install
composer test
```

Need a smoke test? Run `php examples/moderated_answer.php "What is Ragie?"` after exporting the relevant API keys.

## ParaGra Client (Rotation + Fallback)

```php
<?php

use ParaGra\Moderation\OpenAiModerator;
use ParaGra\ParaGra;

$config = require __DIR__ . '/config/paragra.php';
$paragra = ParaGra::fromConfig($config);
$paragra->withModeration(OpenAiModerator::fromEnv());

$retrieval = $paragra->retrieve('What is Ragie?', ['top_k' => 6]);

$answer = $paragra->answer('Explain ParaGra', [
    'retrieval' => ['top_k' => 8],
    'generation' => ['temperature' => 0.2],
]);

echo $answer['answer'];
```

- Automatic key rotation and fallback are powered by `PriorityPool`, `KeyRotator`, and `FallbackStrategy`. Free-tier pools attempt every key before falling back, hybrid pools default to two attempts, and hosted pools take a single shot, with hashed key fingerprints logged when ParaGra rotates providers.
- `retrieve()` returns a `UnifiedResponse` so your app can inspect normalized chunks, metadata, cost, and usage.
- `answer()` wraps retrieval + `NeuronAiAdapter` to build a prompt from the chunk text and call the configured LLM provider.

Copy `config/paragra.example.php` to `config/paragra.php` and fill in the relevant API keys (`CEREBRAS_API_KEY_*`, `RAGIE_API_KEY`, `OPENAI_API_KEY`, `GOOGLE_API_KEY`, etc.). Each pool groups one or more provider specs, letting free-tier keys handle most load while paid tiers stand by as fallbacks.

### Provider catalog shortcuts

Run `php tools/sync_provider_catalog.php` to refresh `config/providers/catalog.php`, then set `'provider_catalog' => __DIR__ . '/config/providers/catalog.php'` inside `config/paragra.php`. ParaGra will load any entry that includes `catalog`/`catalog_slug` and hydrate the provider spec from the catalog metadata:

```php
return [
    'provider_catalog' => __DIR__ . '/config/providers/catalog.php',
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
                'compliance' => ['internal'],
                'metadata_overrides' => ['notes' => 'Preferred free tier slot'],
            ],
        ],
    ],
];
```

Top-level keys such as `latency_tier`, `cost_ceiling`, `compliance`, and `metadata_overrides` merge into the catalog metadata so downstream dashboards can reason about tiers, budgets, and compliance posture without duplicating boilerplate.

### Pool presets with PoolBuilder

Tired of hand-writing rotation pools? Use the new `ParaGra\Planner\PoolBuilder` helper and CLI:

```bash
cd paragra-php
php tools/pool_builder.php --preset=free-tier --format=json
```

`PoolBuilder` reads `config/providers/catalog.php`, inspects your environment variables (`RAGIE_API_KEY`, `GOOGLE_API_KEY`, `GEMINI_DATASTORE_ID` or `GEMINI_CORPUS_ID`, `GROQ_API_KEY`, `OPENAI_API_KEY`, `EDENAI_*`, etc.), and emits ready-to-use `priority_pools` tuned for:

| Preset | Composition |
| --- | --- |
| `free-tier` | Gemini Flash Lite + Groq backed by Ragie, optional Cerebras rotation when `CEREBRAS_API_KEYS` is present. |
| `hybrid` | Free rotation plus OpenAI/Pinecone fallback for paid workloads. |
| `hosted` | AskYoda fallback annotated with Vectara + AWS Bedrock Knowledge Base recommendations. |

You can also consume it directly inside `config/paragra.php`:

```php
use ParaGra\Planner\PoolBuilder;
use ParaGra\ProviderCatalog\ProviderDiscovery;

$catalog = ProviderDiscovery::fromFile(__DIR__ . '/providers/catalog.php');
$builder = PoolBuilder::fromGlobals($catalog);

$options = [
    'ragie_partition' => getenv('RAGIE_PARTITION') ?: 'default',
    'ragie_defaults' => ['top_k' => 8],
];

$config = [
    'provider_catalog' => __DIR__ . '/providers/catalog.php',
    'priority_pools' => $builder->build(PoolBuilder::PRESET_FREE, $options),
];
```

All overrides are optional; `PoolBuilder` automatically merges env-driven values and throws descriptive errors when a required key (Gemini File Search now insists on `GEMINI_DATASTORE_ID`, `GEMINI_CORPUS_ID`, or `GEMINI_VECTOR_STORE`) or catalog slug is missing so you can address misconfiguration early.

### Composer integration (vendor vs vendor-local)

Inside `ask.vexy.art/private` we keep two copies:

| Path | Purpose |
| --- | --- |
| `vendor/` | Composer-managed release build used for deployments. |
| `vendor-local/` | Path repository pointing at `../../paragra-php` for local hacking. |

`composer.json` already declares the path repository, so `composer install` pulls fresh sources into both directories. Update `vendor-local/` when iterating locally; run `composer update vexy/paragra-php` to refresh the production `vendor/` copy once you're ready to deploy.

## Scripts

- `composer test` — PHPUnit test suite
- `composer stan` — PHPStan (level 7)
- `composer psalm` — Psalm (error level 3)
- `composer lint` — php-cs-fixer dry-run
- `composer qa` — Runs lint+stan+psalm+test sequentially
- `php tools/sync_provider_catalog.php [--source=../vexy-co-model-catalog]` — ingest the Python catalog outputs (`external/dump_models.py` + `models/*.json`) and regenerate `config/providers/catalog.{json,php}` with capability presets and recommended models.

## Components

- **Assistant** — `ParaGra\Assistant\RagAnswerer` and `RagAnswer` now mirror the proven Ragie workflows (retrieval → prompt building → OpenAI/AskYoda fallback) while remaining free to orchestrate other providers.
- **LLM** — `ParaGra\Llm` hosts the chat DTOs, `OpenAiChatClient/Config`, `PromptBuilder`, plus the EdenAI AskYoda client/response for pool-based fallbacks.
- **Moderation** — `ParaGra\Moderation\OpenAiModerator` (with result/exception DTOs) enables optional safety checks before running retrieval/LLM steps.
- **Utilities** — `ParaGra\Util\ConfigValidator` and `ParaGra\Support\ExceptionEnhancer` centralize environment validation + error messaging so ask.vexy.art endpoints and future ParaGra clients share consistent helpers.
- **Embedding** — `ParaGra\Embedding\EmbeddingRequest`, `EmbeddingProviderInterface`, and the ready-made `OpenAiEmbeddingConfig/Provider`, `CohereEmbeddingConfig/Provider`, `GeminiEmbeddingConfig/Provider`, and `VoyageEmbeddingConfig/Provider` deliver a shared contract for vector generation with batch controls, metadata passthrough, and optional normalization.
- **Vector store** — `ParaGra\VectorStore\VectorNamespace` and `VectorStoreInterface` define how Pinecone, Weaviate, Qdrant, Chroma, or Gemini File Search adapters will describe namespaces, upserts, deletes, and queries with consistency hints.
- **External search** — `ParaGra\ExternalSearch\TwatSearchRetriever` shells out to the [`twat-search`](https://github.com/twardoch/twat-search) CLI so ParaGra can enrich empty contexts with multi-engine snippets (Brave, DuckDuckGo, Tavily, SerpAPI) and emit normalized chunks with attribution metadata.
- **Media** — `ParaGra\Media\MediaRequest`, `MediaResult`, and the new `ChutesImageProvider` + `FalImageProvider` turn Ragie answers into art prompts and call the matching REST APIs (Chutes `/generate` JSON/binary responses, Fal.ai async job polling) so image/video enrichments can run behind feature flags without touching the core Ragie flows.
- **Provider Catalog** — `ProviderCatalog\CapabilityMap`, `ProviderCatalog\ProviderSummary`, and `ProviderCatalog\ProviderDiscovery` wrap the generated catalog so you can filter providers by capability, inspect embedding/vector-store support, and turn catalog slugs (`openai`, `groq`, `gemini`, etc.) into ready-to-use `ProviderSpec` instances.

### OpenAI embedding provider

ParaGra ships the first concrete embedding adapter via `ParaGra\Embedding\OpenAiEmbeddingProvider`. It wraps OpenAI's `text-embedding-3` family (and legacy `text-embedding-ada-002`) with batch-size enforcement, vector normalization, dimension hints, and metadata pass-through so you can push normalized vectors straight to Pinecone/Qdrant.

```php
use ParaGra\Embedding\EmbeddingRequest;
use ParaGra\Embedding\OpenAiEmbeddingConfig;
use ParaGra\Embedding\OpenAiEmbeddingProvider;

$config = OpenAiEmbeddingConfig::fromEnv();
$provider = new OpenAiEmbeddingProvider($config);

$request = new EmbeddingRequest(
    inputs: [
        ['id' => 'doc-1', 'text' => 'Searchable text', 'metadata' => ['source' => 'kb']],
        'Plain string works too',
    ],
    normalize: true,
);

$result = $provider->embed($request);
// $result['vectors'] => list of ['id', 'values', 'metadata'] ready for vector stores.
```

Environment variables:

| Variable | Purpose |
| --- | --- |
| `OPENAI_API_KEY` | Required OpenAI key shared with chat/moderation features. |
| `OPENAI_EMBED_MODEL` | Defaults to `text-embedding-3-small`. |
| `OPENAI_EMBED_BASE_URL` | Optional proxy/base URL override. |
| `OPENAI_EMBED_DIMENSIONS` | Optional override when you want a custom `dimensions` payload. |
| `OPENAI_EMBED_MAX_BATCH` | Defaults to `2048` inputs per request (OpenAI's documented ceiling). |

If you omit `OPENAI_EMBED_DIMENSIONS`, ParaGra auto-picks the canonical dimension (1536 for `text-embedding-3-small`, 3072 for `text-embedding-3-large`). Requests can also specify `dimensions` directly through `EmbeddingRequest`. All vectors are optionally L2-normalized (`normalize: true`) so downstream cosine-search stores behave consistently.

### Cohere embedding provider

`ParaGra\Embedding\CohereEmbeddingProvider` hits Cohere's `embed-*-v3.0` family via raw HTTP (the official PHP SDKs are unmaintained and sit below the 200-star bar), so ParaGra leans on the existing `guzzlehttp/guzzle` dependency with tight env-driven defaults.

```php
use ParaGra\Embedding\CohereEmbeddingConfig;
use ParaGra\Embedding\CohereEmbeddingProvider;
use ParaGra\Embedding\EmbeddingRequest;

$config = CohereEmbeddingConfig::fromEnv();
$provider = new CohereEmbeddingProvider($config);

$request = new EmbeddingRequest(
    inputs: [
        ['id' => 'doc-1', 'text' => 'Search document', 'metadata' => ['tier' => 'gold']],
        'Another chunk that defaults to null metadata',
    ],
    normalize: false, // Cohere already outputs normalized floats
);

$result = $provider->embed($request);
// $result['vectors'] now mirrors ParaGra's embedding contract.
```

Environment variables:

| Variable | Purpose |
| --- | --- |
| `COHERE_API_KEY` | Required Cohere key. |
| `COHERE_EMBED_MODEL` | Defaults to `embed-english-v3.0` (1024 dims) but supports `*-light` (384) and multilingual variants. |
| `COHERE_EMBED_INPUT_TYPE` | Defaults to `search_document`; override with `search_query`, `classification`, etc. |
| `COHERE_EMBED_TRUNCATE` | Optional `START`/`END` toggle for long inputs. Leave empty to let Cohere reject oversize payloads. |
| `COHERE_EMBED_TYPES` | Comma list of embedding formats (`float`, `int8`, `binary`). ParaGra converts everything back to floats for downstream compatibility. |
| `COHERE_EMBED_MAX_BATCH` | Defaults to 96 texts per API call. |
| `COHERE_EMBED_BASE_URL` / `COHERE_EMBED_ENDPOINT` | Override when routing through a proxy/backplane. |

The provider enforces Cohere's 96-text ceiling, auto-infers the correct output dimension metadata (1024 or 384), and rejects manual `dimensions` overrides because Cohere's embeddings are fixed per model. Usage metadata exposes the `billed_units` block from Cohere's response so you can track consumption per batch.

### Gemini embedding provider

`ParaGra\Embedding\GeminiEmbeddingProvider` builds on the community-maintained (344⭐) [`google-gemini-php/client`](https://github.com/google-gemini-php/client) SDK so ParaGra can batch against Google's `text-embedding-004` and `embedding-001` models without hand-rolling HTTP plumbing. The provider enforces Gemini's documented 250-item ceiling, wires optional `taskType` hints, and only sends `outputDimensionality` when the target model supports it.

```php
use ParaGra\Embedding\EmbeddingRequest;
use ParaGra\Embedding\GeminiEmbeddingConfig;
use ParaGra\Embedding\GeminiEmbeddingProvider;

$config = GeminiEmbeddingConfig::fromEnv();
$provider = new GeminiEmbeddingProvider($config);

$request = new EmbeddingRequest(
    inputs: [
        ['id' => 'doc-1', 'text' => 'Gemini corpus entry', 'metadata' => ['region' => 'us']],
        'Query chunk that inherits null metadata',
    ],
    dimensions: 512, // only supported for text-embedding-004
    normalize: true,
);

$result = $provider->embed($request);
// $result['vectors'] => normalized Gemini vectors ready for Pinecone/Qdrant/etc.
```

Environment variables:

| Variable | Purpose |
| --- | --- |
| `GEMINI_EMBED_API_KEY` | Preferred Gemini API key for embeddings. Falls back to `GOOGLE_API_KEY` when unset. |
| `GEMINI_EMBED_MODEL` | Defaults to `text-embedding-004`. Set to `embedding-001` when you need the legacy 3072-dim output. |
| `GEMINI_EMBED_DIMENSIONS` | Optional override for `text-embedding-004` (128-3072). Ignored for models that don't support overrides. |
| `GEMINI_EMBED_MAX_BATCH` | Defaults to `250` inputs per call, matching Gemini's `batchEmbedContents` limit. |
| `GEMINI_EMBED_TASK_TYPE` | Optional TaskType hint (`retrieval_query`, `retrieval_document`, etc.) for improved embeddings. |
| `GEMINI_EMBED_TITLE` | Optional title passed to Gemini for analytics/debugging. |
| `GEMINI_EMBED_BASE_URL` | Override the API host (rare, mostly for proxies). |

If you leave `GEMINI_EMBED_API_KEY` empty ParaGra automatically reuses `GOOGLE_API_KEY`, which is already required for Gemini File Search retrieval pools. Regardless of the override, ParaGra records the canonical dimension metadata so downstream vector stores know what to expect.

### Voyage embedding provider

`ParaGra\Embedding\VoyageEmbeddingProvider` keeps the same contract but targets Voyage AI's `v1/embeddings` endpoint directly through `guzzlehttp/guzzle` (Voyage doesn't ship a PHP SDK with the >200⭐ bar yet). The adapter enforces Voyage's 128-text batch ceiling, wires the documented `input_type`/`truncate` toggles, lets callers override output dimensions via Matryoshka learning, and normalizes the returned vectors so they drop into Pinecone/Qdrant without extra math.

```php
use ParaGra\Embedding\EmbeddingRequest;
use ParaGra\Embedding\VoyageEmbeddingConfig;
use ParaGra\Embedding\VoyageEmbeddingProvider;

$config = VoyageEmbeddingConfig::fromEnv();
$provider = new VoyageEmbeddingProvider($config);

$request = new EmbeddingRequest(
    inputs: [
        ['id' => 'kb-1', 'text' => 'Voyage document embedding', 'metadata' => ['provider' => 'voyage']],
        'Query chunk that defaults to null metadata',
    ],
    normalize: true,
);

$result = $provider->embed($request);
// Contains normalized embeddings + usage metadata from Voyage's response.
```

Environment variables:

| Variable | Purpose |
| --- | --- |
| `VOYAGE_API_KEY` | Required Voyage AI key. |
| `VOYAGE_EMBED_MODEL` | Defaults to `voyage-3` (1024 dims). Supports `voyage-3-large` (2048), `voyage-3-lite` (512), `voyage-2`, and `voyage-2-lite`. |
| `VOYAGE_EMBED_INPUT_TYPE` | `document` (default) or `query`. Set to `none`/blank to omit the hint entirely. |
| `VOYAGE_EMBED_TRUNCATE` | Boolean toggle (`true`/`false`) for the API's truncation behavior. Defaults to `true`. |
| `VOYAGE_EMBED_DIMENSIONS` | Optional Matryoshka override (positive integer). Falls back to the model's canonical dimension when omitted. |
| `VOYAGE_EMBED_MAX_BATCH` | Defaults to `128` inputs per batch, matching Voyage's documented ceiling. |
| `VOYAGE_EMBED_TIMEOUT` | Request timeout in seconds (defaults to `30`). |
| `VOYAGE_EMBED_ENCODING` | Currently only accepts `float`, keeping Voyage payloads numeric so ParaGra can normalize them. |
| `VOYAGE_EMBED_BASE_URL` / `VOYAGE_EMBED_ENDPOINT` | Override when routing through an internal proxy. |

ParaGra always requests `encoding_format=float` today so downstream vector/vector-store tooling can safely treat the payloads as numeric lists. The provider rejects larger batches before hitting the network, converts Voyage's `data` or `embeddings` shapes back into ParaGra's canonical vector structure, and surfaces the upstream `usage` block verbatim for cost tracking.

### Pinecone vector store adapter

`ParaGra\VectorStore\PineconeVectorStore` speaks Pinecone's 2024 data plane API directly through Guzzle because no maintained PHP SDK clears the 200⭐ threshold yet. Instantiate it with the data plane host, API key, and index name, then pass `VectorNamespace` instances to scope namespaces/filters.

```php
use ParaGra\VectorStore\PineconeVectorStore;
use ParaGra\VectorStore\VectorNamespace;

$store = new PineconeVectorStore(
    baseUrl: 'https://my-index.svc.us-west1-aws.pinecone.io',
    apiKey: getenv('PINECONE_API_KEY'),
    indexName: 'docs-index',
    defaultNamespace: new VectorNamespace('public-kb'),
);

$store->upsert(
    new VectorNamespace('public-kb'),
    [
        ['id' => 'doc-1', 'values' => [0.1, 0.2], 'metadata' => ['text' => 'Chunk body', 'source' => 'kb']],
    ],
);

$response = $store->query(new VectorNamespace('public-kb'), $queryVector, ['top_k' => 8]);
foreach ($response->getChunks() as $chunk) {
    // UnifiedResponse chunks include text, Pinecone scores, and normalized metadata.
}
```

Key parameters:

- `baseUrl` — Your index host (`https://{index}-{project}.svc.{env}.pinecone.io`).
- `apiKey` — Pinecone data plane key (`PINECONE_API_KEY` in most deployments).
- `indexName` — Friendly name shown in `UnifiedResponse::getModel()`.
- `defaultNamespace` — Optional `VectorNamespace` for `getDefaultNamespace()`. Additional metadata entries become Pinecone JSON filters (scalars → `$eq`, lists → `$in`).

ParaGra always requests metadata, normalizes chunk text (`metadata.text` or `metadata.content`), and propagates namespace diagnostics through `UnifiedResponse` metadata for observability.

### Weaviate vector store adapter

`ParaGra\VectorStore\WeaviateVectorStore` wraps Weaviate’s REST + GraphQL APIs without pulling the low-star PHP community SDKs into the tree. Provide the cluster base URL (without `/v1`), the collection/class name, and optional API key/default namespace metadata so ParaGra can batch upserts, deletes, and vector queries with tenant-aware filters.

```php
use ParaGra\VectorStore\VectorNamespace;
use ParaGra\VectorStore\WeaviateVectorStore;

$namespace = new VectorNamespace(
    name: 'kb',
    collection: 'Articles',
    metadata: ['tenant' => 'tenant-a', 'source' => 'docs'],
);

$store = new WeaviateVectorStore(
    baseUrl: 'https://demo.weaviate.network',
    className: 'Articles',
    apiKey: getenv('WEAVIATE_API_KEY'),
    defaultNamespace: $namespace,
    defaultProperties: ['text', 'title', 'url'],
);

$store->upsert($namespace, [
    ['id' => 'doc-1', 'values' => [0.1, 0.2], 'metadata' => ['text' => 'Chunk body', 'url' => 'https://example']],
]);

$response = $store->query(
    $namespace,
    $queryVector,
    [
        'top_k' => 5,
        'include_vectors' => true, // requests `_additional.vector`
        'properties' => ['text', 'title', 'url'], // GraphQL projection override
    ],
);
```

Highlights:

- **Multi-tenancy + consistency:** Namespace metadata (or per-call options) can set `tenant` and `consistency_level`; the adapter threads them through the `X-Weaviate-Tenant` header, REST `consistency_level` query param, and GraphQL arguments automatically.
- **Metadata filters:** `VectorNamespace` metadata becomes a GraphQL `WhereFilter` (`Equal` for scalars, `ContainsAny` for list metadata). You can still pass a custom `filter` array to `query()` when you need compound predicates.
- **Property selection:** Because GraphQL requires explicit property lists, the constructor accepts `defaultProperties` and `query()` exposes a `properties` override. ParaGra always asks for `_additional { id score distance certainty [vector] }` so `UnifiedResponse` keeps scores/document IDs while your code chooses which object fields become metadata.

### Qdrant vector store adapter

`ParaGra\VectorStore\QdrantVectorStore` targets Qdrant's REST API using the same contract. Provide the base URL (e.g., `http://localhost:6333` or your managed endpoint), collection name, and optional API key.

```php
use ParaGra\VectorStore\QdrantVectorStore;
use ParaGra\VectorStore\VectorNamespace;

$store = new QdrantVectorStore(
    baseUrl: 'http://localhost:6333',
    collection: 'docs',
    apiKey: getenv('QDRANT_API_KEY'),
);

$store->upsert(
    new VectorNamespace('docs'),
    [
        ['id' => 'doc-1', 'values' => [0.4, 0.5], 'metadata' => ['text' => 'Payload body', 'tier' => 'gold']],
    ],
    ['wait_for_sync' => true],
);

$response = $store->query(new VectorNamespace('docs'), $queryVector, ['top_k' => 5]);
```

Important knobs:

- `wait_for_sync` (upsert/delete options) toggles Qdrant's `wait` flag.
- `VectorNamespace` metadata becomes Qdrant filters (`must` clauses with scalar/`any` matches).
- `include_vectors` query option controls `with_vector`.

The adapter preserves Qdrant payload fields as chunk metadata (minus the extracted `text`/`content`) so downstream prompt builders can keep URLs, titles, or custom attributes intact.

### Chroma vector store adapter

`ParaGra\VectorStore\ChromaVectorStore` covers ChromaDB v2's REST interface (`/api/v2/tenants/{tenant}/databases/{database}/collections/{collection}`) directly through Guzzle because the only public PHP clients (`theogibbons/chroma-php` @ 2⭐ and `CodeWithKyrian/chromadb-php` @ 73⭐) sit well below the >200⭐ support bar. Supply the base URL (without `/api/v2`), Chroma tenant/database names, the collection/namespace you want to target, and (optionally) a bearer token.

```php
use ParaGra\VectorStore\ChromaVectorStore;
use ParaGra\VectorStore\VectorNamespace;

$namespace = new VectorNamespace('kb', 'kb', metadata: ['source' => 'manual']);

$store = new ChromaVectorStore(
    baseUrl: 'http://localhost:8000',
    tenant: 'default_tenant',
    database: 'default_database',
    collection: 'kb',
    defaultNamespace: $namespace,
    authToken: getenv('CHROMA_API_TOKEN') ?: null,
);

$store->upsert($namespace, [
    ['id' => 'doc-1', 'values' => [0.1, 0.2], 'metadata' => ['text' => 'Chunk body', 'url' => 'https://example']],
]);

$response = $store->query(
    $namespace,
    $queryVector,
    [
        'top_k' => 5,
        // Optional: pass additional filters understood by Chroma's `where` syntax.
        'filter' => ['$and' => [['tier' => 'public']]],
    ],
);
```

Chroma highlights:

- **Tenant + database aware:** Constructor arguments feed every REST path so ParaGra can talk to multi-tenant deployments, while `VectorNamespace::getCollection()` lets you override the collection per namespace.
- **Metadata-driven filters:** Namespace metadata automatically becomes a Chroma `where` clause (scalars become `['field' => 'value']`, lists become `['$and' => [['field' => ['$in' => [...]]]]]`). You can always pass a custom `filter` array to `query()` for advanced predicates.
- **Document extraction:** Upserts automatically populate Chroma's `documents` field when metadata already includes `text`/`content`, and queries turn `documents` (or fallback metadata) back into `UnifiedResponse` chunks with `tenant`/`database`/`collection` diagnostics included.
- **Document extraction:** Upserts automatically populate Chroma's `documents` field when metadata already includes `text`/`content`, and queries turn `documents` (or fallback metadata) back into `UnifiedResponse` chunks with `tenant`/`database`/`collection` diagnostics included.

### Gemini File Search vector adapter

`ParaGra\VectorStore\GeminiFileSearchVectorStore` targets the Gemini File Search REST API, which exposes either `projects/.../corpora/...` resources or simplified `fileSearchStores/...` names. Google manages the chunking/vector math internally, so ParaGra focuses on uploading chunk text + metadata and issuing semantic queries that return normalized `UnifiedResponse` chunks.

```php
use ParaGra\VectorStore\GeminiFileSearchVectorStore;
use ParaGra\VectorStore\VectorNamespace;

$resource = getenv('GEMINI_DATASTORE_ID') ?: getenv('GEMINI_CORPUS_ID') ?: getenv('GEMINI_VECTOR_STORE');
if (!is_string($resource) || $resource === '') {
    throw new RuntimeException('Set GEMINI_DATASTORE_ID (preferred) or GEMINI_CORPUS_ID for Gemini File Search.');
}

$store = new GeminiFileSearchVectorStore(
    apiKey: getenv('GOOGLE_API_KEY'),
    resourceName: $resource,
);

$namespace = new VectorNamespace('internal-kb', $resource, metadata: ['source' => 'kb']);

$store->upsert($namespace, [
    [
        'id' => 'doc-1',
        'values' => [], // Gemini ignores embedding values
        'metadata' => [
            'text' => 'Chunk body content',
            'display_name' => 'Knowledge Base Doc',
            'tags' => ['kb'],
        ],
    ],
]);

$response = $store->query(
    $namespace,
    [],
    [
        'query' => 'Summarize the knowledge base doc',
        'top_k' => 4,
    ],
);
```

Key behaviors:

- **Text required:** Each record passed to `upsert()` must include `metadata['text']` (or `body`/`content`). Gemini uses this text to build a document; the `values` array is ignored because vectors are computed server-side.
- **Stable IDs:** Provide deterministic `id` values so `delete()` can remove or replace individual documents. ParaGra slugifies IDs automatically to satisfy Gemini's character rules.
- **Query contract:** `query()` ignores the `$vector` parameter—pass a natural-language prompt via `options['query']`. Namespace metadata (or `options['filter']`) becomes Gemini's metadata filters.
- **Resource flexibility:** `resourceName` can be either `fileSearchStores/{store}` (Gemini API), `projects/{project}/locations/{location}/collections/default_collection/dataStores/{datastore}` (Vertex AI Search), or the legacy `projects/{project}/locations/{location}/corpora/{corpus}` string. ParaGra mirrors whatever identifier you configure in Google AI Studio.

### Hybrid Ragie + vector store pipeline

`ParaGra\Pipeline\HybridRetrievalPipeline` blends Ragie retrieval (keyword/KB) with semantic matches from any vector store adapter. It accepts a callable retriever (usually `[$paragra, 'retrieve']`), an embedding provider, the vector store adapter, and the namespace to target. The pipeline exposes two entry points:

- `ingestFromRagie(string $query, array $options = [])` — fetch Ragie chunks, embed them, and upsert into the vector store.
- `hybridRetrieve(string $query, array $options = [])` — query Ragie + the vector store, rerank/deduplicate results, and return raw + merged `UnifiedResponse` objects.

```php
use ParaGra\Embedding\OpenAiEmbeddingConfig;
use ParaGra\Embedding\OpenAiEmbeddingProvider;
use ParaGra\ParaGra;
use ParaGra\Pipeline\HybridRetrievalPipeline;
use ParaGra\VectorStore\PineconeVectorStore;
use ParaGra\VectorStore\VectorNamespace;

$paragra = ParaGra::fromConfig(require __DIR__ . '/config/paragra.php');
$embedding = new OpenAiEmbeddingProvider(OpenAiEmbeddingConfig::fromEnv());
$namespace = new VectorNamespace('ragie-hybrid');
$vectorStore = new PineconeVectorStore(
    baseUrl: getenv('PINECONE_BASE_URL'),
    apiKey: getenv('PINECONE_API_KEY'),
    indexName: getenv('PINECONE_INDEX'),
    defaultNamespace: $namespace,
);

$pipeline = new HybridRetrievalPipeline([$paragra, 'retrieve'], $embedding, $vectorStore, $namespace);
$pipeline->ingestFromRagie('What does ParaGra do?', ['retrieval' => ['top_k' => 6]]);

$result = $pipeline->hybridRetrieve('What does ParaGra do?', [
    'retrieval' => ['top_k' => 6],
    'vector_store' => ['top_k' => 6],
    'hybrid_limit' => 8,
]);

$combinedChunks = $result['combined']->getChunks(); // ready for prompt builders
```

See `examples/vector-stores/hybrid_pipeline.php` for an end-to-end CLI that supports Pinecone, Qdrant, Weaviate, Chroma, and Gemini File Search via environment variables (`HYBRID_STORE`, `HYBRID_SEED_FIRST`, etc.).

### External search fallback (twat-search)

`ParaGra\ExternalSearch\TwatSearchRetriever` lets ParaGra execute the [`twat-search`](https://github.com/twardoch/twat-search) CLI (multi-engine Brave/DuckDuckGo/Tavily/SerpAPI) whenever Ragie or another catalog provider fails to return context. The retriever:

- Builds `twat-search web q --json ...` commands with engine selection (`-e brave,duckduckgo`), per-engine result counts, and a configurable binary (`TWAT_SEARCH_BIN`).
- Uses `symfony/process` to enforce timeouts, capture stdout/stderr cleanly, and retry transient failures (exit code ≠ 0) before surfacing an `ExternalSearchException`.
- Normalizes JSON output into `UnifiedResponse` chunks with `text`, `score`, `document_id` (URL), and metadata (engine, title, snippet, timestamp, raw/extra_info payloads).
- Caches responses in-memory (`TWAT_SEARCH_CACHE_TTL`, default 90s) so repeated questions hit the CLI once per TTL window while exposing `cache_hit`, `duration_ms`, and `engines` metadata for logging.

Usage example:

```php
use ParaGra\ExternalSearch\TwatSearchRetriever;

$twat = new TwatSearchRetriever(
    defaultEngines: explode(',', getenv('TWAT_SEARCH_ENGINES') ?: 'brave,duckduckgo'),
    environment: array_filter([
        'BRAVE_API_KEY' => getenv('BRAVE_API_KEY') ?: null,
        'TAVILY_API_KEY' => getenv('TAVILY_API_KEY') ?: null,
        'YOU_API_KEY' => getenv('YOU_API_KEY') ?: null,
        'SERPAPI_API_KEY' => getenv('SERPAPI_API_KEY') ?: null,
    ]),
);

$context = $paragra->retrieve($question);
if ($context->isEmpty()) {
    $search = $twat->search($question, ['num_results' => 4, 'max_results' => 6]);
    $chunks = $search->getChunks();
    // Feed chunks into PromptBuilder or append to fallback prompts.
}
```

See `examples/external-search/twat_search_fallback.php` for a CLI script that prints Ragie chunks alongside twat-search snippets plus metadata. Environment knobs:

| Env var | Default | Purpose |
| --- | --- | --- |
| `TWAT_SEARCH_BIN` | `twat-search` | Path to the CLI binary (`pip install "twat-search[all]"`). |
| `TWAT_SEARCH_ENGINES` | `brave,duckduckgo` | Comma-separated engine IDs passed to `-e`. |
| `TWAT_SEARCH_NUM_RESULTS` | `4` | Per-engine result count sent to `--num_results`. |
| `TWAT_SEARCH_MAX_RESULTS` | `6` | Combined snippet limit after normalization. |
| `TWAT_SEARCH_CACHE_TTL` | `90` | Cache duration (seconds) for CLI responses. |
| `TAVILY_API_KEY`, `BRAVE_API_KEY`, `YOU_API_KEY`, `SERPAPI_API_KEY` | — | Optional API keys enabling premium engines. |

### Provider catalog & discovery workflow

1. Run `php tools/sync_provider_catalog.php` from `paragra-php/` (or point `--source` at another checkout of `vexy-co-model-catalog`). The script parses `external/dump_models.py`, ingests the latest `models/*.json`, merges ParaGra-specific capability presets, and writes `config/providers/catalog.json` plus a PHP array version.
2. Load catalog data inside your app:
   ```php
   use ParaGra\Config\ProviderSpec;
   use ParaGra\ProviderCatalog\ProviderDiscovery;

   $catalog = ProviderDiscovery::fromFile(__DIR__ . '/config/providers/catalog.php');

   // Filter for providers that can embed
   $embeddingProviders = $catalog->filterByCapability('embeddings');

   // Build a ProviderSpec for OpenAI using env vars + default solution metadata
   putenv('OPENAI_API_KEY=sk-...');
   $openAiSpec = $catalog->buildProviderSpec('openai'); // ProviderSpec
   ```
3. Feed the returned `ProviderSpec` objects into your `PriorityPool` definitions (or ParaGra factories) instead of hard-coding provider metadata. The discovery helper surfaces embedding dimensions, preferred vector stores, and recommended models so you can rotate Cerebras/Groq/OpenAI/Gemini pools with a single slug.

## Dependencies

See `DEPENDENCIES.md` for justification of every runtime + dev dependency.

## Documentation

- `docs/configuration.md` — Step-by-step config + env var reference.
- `docs/architecture.md` — Priority pools, rotation, and fallback diagrams.
- `docs/examples.md` — Scenario configs + moderation script usage.
- `docs/migration.md` — How to replace old ragie-php helpers with ParaGra.
- `docs/api.md` — Quick reference for core classes and methods.
