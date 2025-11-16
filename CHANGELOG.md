---
this_file: paragra-php/CHANGELOG.md
---

# ParaGra Changelog

## 2026-01-12 - Coverage lift for Assistant/Config/ProviderCatalog

- Working from `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php` (Assistant + Config + ProviderCatalog tests) to complete PLAN/TODO Workstream 2 goal “Achieve ≥90% ParaGra coverage”. Added `tests/Assistant/RagAnswerTest.php` to exercise metadata/fallback helpers plus context + chat-usage passthroughs so RagAnswer’s accessors are fully covered.
- Extended `tests/Config/PriorityPoolTest.php` with catalog metadata override merging assertions and a blank-slug guard, ensuring `normalizeCatalogConfig` and `extractSlug` branches run; created `tests/ProviderCatalog/ProviderSummaryTest.php` to validate trimming/normalization of models, embedding dimensions, metadata, and required-field errors.
- Tests (macOS 14.6.1 / PHP 8.4.13): `composer test` → **pass (323 tests / 1,205 assertions, known fallback log chatter only)**; `XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text --coverage-filter src` → **pass**, ParaGra coverage now **90.27% lines (3811/4222) / 66.34% methods**.

## 2026-01-11 - AskYoda hosted adapter telemetry

- Working from `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php` (Assistant + docs) to finish PLAN/TODO Workstream 2 item “Implement Eden AskYoda hosted RAG adapter…”. Added `Assistant/AskYodaHostedAdapter` + `AskYodaHostedResult`, wired `RagAnswerer` to consume the adapter, and exposed telemetry callbacks so hosted fallbacks record duration/chunk metadata instead of duplicating logic per caller.
- Updated `docs/api.md`, `PLAN.md`, and `TODO.md` to reflect the new adapter, and added PHPUnit coverage via `tests/Assistant/AskYodaHostedAdapterTest.php` alongside refreshed `RagAnswererTest` expectations.
- Tests (PHP 8.4.13, folder `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php`): `composer test` → **pass (239 tests / 1,057 assertions, existing 7 PHPUnit deprecation notices + fallback log chatter).**

## 2026-01-10 - Gemini File Search adapter wiring

- Working in `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php` (Providers + Planner + docs) to close PLAN/TODO Workstream 2 items “Implement Gemini File Search adapter” and “Document new environment variables” so Gemini pools stop instantiating Ragie providers and ops know how to supply datastore IDs.
- `src/Providers/GeminiFileSearchProvider.php` now normalizes `vector_store` strings/arrays (`datastore`, `corpus`, `name`, `resource`) before building `toolConfig`, fails fast when missing, and gained PHPUnit coverage validating payloads and error handling. `src/Planner/PoolBuilder.php` enforces `GEMINI_DATASTORE_ID` (with `GEMINI_CORPUS_ID`/`GEMINI_VECTOR_STORE` fallbacks), overrides the catalog solution type to `gemini-file-search`, and carries the normalized vector store into provider specs; `tests/Planner/PoolBuilderTest.php` now asserts datastore/corpus overrides plus the new exception path.
- Synced documentation + examples (`README.md`, `docs/configuration.md`, `docs/examples.md`, `config/paragra.example.php`, `examples/config/gemini_file_search.php`, `examples/vector-stores/hybrid_pipeline.php`) to highlight the new env requirements so downstream projects (ask.vexy.art) inherit the guidance automatically.
- Tests (macOS 14.6.1 / PHP 8.4.13, folder `paragra-php/`): `composer test` → **pass (237 tests / 1,044 assertions, expected fallback log chatter only).**

## 2026-01-09 - Family-aware rotation manager

- Working inside `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php` (router + docs) to finish PLAN §2 / TODO “Build API key rotation + failover manager configurable per provider family.” `Router/FallbackStrategy` now reads provider metadata (`plan`/`tier`) to classify pools as free, hybrid, or hosted, enforces per-family attempt budgets (free pools exhaust every key, hybrid pools try two keys, hosted pools take a single shot unless overridden), and logs hashed API-key fingerprints with provider/model context for each failure.
- Added optional constructor knobs for custom family policies + loggers, plus helper methods for spec indexing so the strategy can rotate through every key inside a pool before promoting to the next priority tier.
- Extended `tests/Router/FallbackStrategyTest.php` with scenarios covering multi-key free pools, policy caps that stop after one hybrid key, and hosted pools that fail fast; updated `README.md`, `docs/architecture.md`, and `docs/api.md` to describe the metadata-driven rotation. Checked off the corresponding TODO/PLAN entries.
- Tests (PHP 8.4.13, folder `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php`): `composer test -- tests/Router/FallbackStrategyTest.php` → **pass (8 tests / 19 assertions, expected fallback logs show hashed key IDs only)**.

## 2026-01-08 - Eden AskYoda quotas + hosted metadata

- Working from `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects` with a focus on `reference/`, `vexy-co-model-catalog/`, and `paragra-php/` to finish PLAN §2 / TODO “Capture Eden AskYoda hosted details.” Added `reference/research-rag/ragres-14.md` with citations from Eden AI’s rate-limit + monitoring docs plus a third-party review, then regenerated `reference/catalog/provider_insights.{source,json}` so a new `eden-askyoda` slug captures the 60/300/1,000 RPM tiers, HTTP 429 behavior, and latency telemetry notes.
- Re-ran `uv run -s scripts/sync_provider_insights.py` in `../vexy-co-model-catalog` and `php tools/sync_provider_catalog.php` in `paragra-php/` so the catalog now embeds the AskYoda insights + metadata (`tier` + `latency_tier`). PoolBuilder’s hosted preset now injects the normalized insight blob, latency tier, and hosted recommendations into the AskYoda solution metadata, enabling dashboards + ask.vexy.art to surface the quotas directly from config.
- Hardened the PHPUnit fixtures: `tests/ProviderCatalog/SyncProviderCatalogTest.php` now expects AskYoda’s insight payload, and `tests/Planner/PoolBuilderTest.php` asserts that hosted pools carry the new latency tier plus the starter RPM quota in `metadata['insight']`. Regenerated `config/providers/catalog.{json,php}` accordingly.
- Tests (macOS 14.6.1 / PHP 8.4.13, folder `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php`): `composer test -- tests/ProviderCatalog/SyncProviderCatalogTest.php tests/Planner/PoolBuilderTest.php` → **OK (6 tests / 131 assertions, existing fallback log chatter only)**.

## 2026-01-07 - Gemini File Search metadata + PoolBuilder insights

- Working from `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects` (focus on `reference/` + `paragra-php/`) to capture Google's newly announced Gemini File Search tool so PLAN §2 ("ParaGra Multi-Provider Fabric") has research-backed quotas. Added `reference/research-rag/ragres-13.md` summarizing the Nov 2025 launch blog + official docs, then regenerated `reference/catalog/provider_insights.{source,json}` with a `google-gemini-file-search` entry (100 MB max file, 1 GB free store, storage/query-time embeddings free, $0.15/M token indexing).
- Mirrored the insights JSON into `../vexy-co-model-catalog/config/` via `uv run -s scripts/sync_provider_insights.py`, extended `tools/sync_provider_catalog.php`’s `insightMappings()` to include the new slug, and reran the sync CLI so `config/providers/catalog.{json,php}` now embed the File Search quotas alongside Gemini Flash + Embedding.
- Enhanced `PoolBuilder` to surface research metadata inside each rotation: Gemini + Groq entries now carry a normalized `insight` block (Gemini explicitly points at `google-gemini-file-search`) so ParaGra + ask.vexy.art can reason about free-tier storage/layouts from config alone. Updated the PHPUnit fixtures (`tests/ProviderCatalog/SyncProviderCatalogTest.php`, `tests/Planner/PoolBuilderTest.php`) to assert the extra insight rows and quotas, then re-synced the catalog + reran targeted tests.
- Tests (macOS 14.6.1 / PHP 8.4.13, folder `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php`): `composer test -- tests/ProviderCatalog/SyncProviderCatalogTest.php tests/Planner/PoolBuilderTest.php` → **OK (6 tests / 121 assertions, expected deprecation + fallback logs unchanged)**.

## 2025-11-16 - PoolBuilder presets + ask.vexy.art integration

- Working in `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php` + sibling `ask.vexy.art/` to land PLAN §1 / TODO RefCat item 3: vectara + AWS Bedrock Knowledge Base insights now live inside `reference/catalog/provider_insights.{source,json}`, `tools/sync_provider_catalog.php` gained capability presets/mappings for those providers, and `tests/ProviderCatalog/SyncProviderCatalogTest.php` asserts the new metadata survives a catalog sync.
- Introduced `src/Planner/PoolBuilder.php` plus the `tools/pool_builder.php` CLI so `free-tier`, `hybrid`, and `hosted` priority-pool presets can be generated straight from the catalog/environment. Added `tests/Planner/PoolBuilderTest.php` covering deterministic layouts, override knobs, and missing-data error paths; refreshed docs (`README.md`, `docs/configuration.md`) to document the workflow.
- `ask.vexy.art/private/config/paragra.php` now instantiates `PoolBuilder` instead of hand-built arrays, `.env.example` exposes `PARAGRA_POOL_PRESET`, and the config/rotation smoke tests were updated to load catalog-backed pools. Composer path repos were refreshed so the minisite consumes the new builder.
- Tests (macOS 14.6.1 / PHP 8.4.13):
  - `cd paragra-php && composer test` → **OK (230 tests / 1,010 assertions, 7 existing PHPUnit deprecations + expected ParaGra fallback logs)**.
  - `cd ask.vexy.art && php tests/paragra_config_test.php` → **pass**; `php tests/paragra_rotation_test.php` → **pass with simulated fallback logs**.

## 2025-11-16 - Provider Insights Metadata Sync

- Working in `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php` + sibling `../vexy-co-model-catalog` to land PLAN §1 / TODO RefCat #203 item 2: the sync CLI now accepts `--insights`, loads `config/provider_insights.json`, normalizes recommended roles + free-tier quotas, and embeds them under each provider’s `metadata.insights`. Added capability presets for Cloudflare, Dify, Mistral, OpenRouter, Pinecone, Qdrant, and Voyage so their catalog entries exist even without fetched models.
- Shipped `tests/ProviderCatalog/SyncProviderCatalogTest.php`, an end-to-end fixture that boots a fake Vexy checkout, runs `tools/sync_provider_catalog.php`, and asserts that Gemini Flash, Groq Llama, OpenRouter, Mistral, Voyage, Cloudflare, Pinecone, Qdrant, and Dify all expose their research-backed quotas + recommended roles. Regenerated `config/providers/catalog.{json,php}` via the refreshed CLI.
- Tests (macOS 14.6.1 / PHP 8.4.13, folder `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php`): `composer test` → **OK (225 tests / 965 assertions, PHPUnit still reports the 7 known deprecations along with ParaGra fallback log spam)**.

## 2025-12-20 - Media Request + Image Providers

- Working in `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php` to deliver PLAN §27 / TODO #367-#372 so ParaGra finally has a first-class media contract: added `src/Media/MediaRequest.php`, `MediaResult.php`, and `ImageOperationInterface.php` so answer workflows can describe prompts, negative prompts, dimensions, metadata, and seeds before calling optional image providers.
- Introduced `Media\ChutesImageProvider` (Guzzle-backed POST to the chute `/generate` endpoint, JSON vs binary response handling, retries, aspect ratio helpers) and `Media\FalImageProvider` (Fal.ai async job submission/polling via `POST /v1/{model}` + `GET /v1/jobs/{requestId}`) plus `MediaException` to keep failure paths consistent. Added PHPUnit suites covering DTO validation plus happy-path/error-path behavior via `tests/Media/{MediaRequestTest,MediaResultTest,ChutesImageProviderTest,FalImageProviderTest}.php`.
- Shipped CLI examples under `examples/media/{chutes_answer_with_image.php,fal_answer_with_image.php}` that reuse ParaGra answers to craft art prompts, then call the matching provider behind env-driven feature flags. Updated README, `docs/examples.md`, and `docs/configuration.md` to document the workflow + env vars (`CHUTES_*`, `FAL_*`), and marked the roadmap/TODO entries as complete.
- Tests (macOS 14.6.1 / PHP 8.4.13, folder `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php`): `composer test` → **OK (222 tests / 892 assertions, PHPUnit logs the usual 7 deprecation notices)**.

## 2025-12-18 - Twat-search External Search Fallback

- Working in `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php` to execute PLAN §26 / TODO #357-#362 so ParaGra gains an external-search escape hatch: created `src/ExternalSearch/ExternalSearchRetrieverInterface.php` + `ExternalSearchException.php`, and shipped `ExternalSearch/TwatSearchRetriever.php`, a Symfony Process-powered adapter that calls `twat-search web q --json`, retries transient CLI failures, caches responses, and emits normalized `UnifiedResponse` chunks (title/snippet/url/engine metadata included).
- Added PHPUnit coverage in `tests/ExternalSearch/TwatSearchRetrieverTest.php` (command building, normalization, caching hits, retry paths, and malformed/empty payload handling via injectable process runners), plus documented the workflow through `examples/external-search/twat_search_fallback.php`, README (`External search fallback` section), `docs/examples.md`, `docs/configuration.md`, and updated PLAN/TODO/DEPENDENCIES entries. Composer now declares `symfony/process` to avoid bespoke `proc_open()` logic.
- Tests (macOS 14.6.1 / PHP 8.4.13, folder `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php`): `composer test` → **OK (208 tests / 865 assertions, PHPUnit logs the usual 7 deprecation warnings)**.

## 2025-12-17 - Hybrid Ragie + Vector Store Pipeline

- Working in `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php` to wrap PLAN §25 / TODO #352: added `src/Pipeline/HybridRetrievalPipeline.php`, a test-driven orchestrator that (1) pulls Ragie context via any `ParaGra::retrieve` callable, (2) embeds those chunks, (3) seeds a configured vector store, and (4) runs hybrid retrieval with reranking/deduplication so Ragie + vector results collapse into a single `UnifiedResponse`.
- Landed PHPUnit coverage in `tests/Pipeline/HybridRetrievalPipelineTest.php` using fake embedding/vector-store stubs to prove seeding, duplicate penalties, env-driven options, and ordering guarantees before shipping the pipeline.
- Added `examples/vector-stores/hybrid_pipeline.php` so developers can export Ragie chunks into Pinecone/Qdrant/Weaviate/Chroma/Gemini via environment variables (`HYBRID_STORE`, `HYBRID_SEED_FIRST`, etc.), plus refreshed `docs/examples.md` and the README to document the pipeline, CLI usage, and env requirements. Marked the roadmap checkbox in `PLAN.md`/`TODO.md`.
- Tests (macOS 14.6.1 / PHP 8.4.13, folder `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php`): `composer test` → **OK (202 tests / 831 assertions, PHPUnit 6 deprecation warnings only)**.

## 2025-12-16 - Gemini File Search Vector Store

- Working in `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php` to finish PLAN §25 / TODO #351 by wiring a first-party Gemini File Search adapter instead of waiting for a >200⭐ PHP SDK (none exist; confirmed again via Exa/Perplexity). Added `src/VectorStore/GeminiFileSearchVectorStore.php`, a Guzzle client that turns namespace metadata into File Search document payloads (enforcing `metadata['text']`), accepts both `fileSearchStores/*` and `projects/.../corpora/...` resource names, and normalizes `documents:query` responses into `UnifiedResponse` chunks—complete with metadata filters + score propagation.
- Created `tests/VectorStore/GeminiFileSearchVectorStoreTest.php` with MockHandler coverage for upsert (payload batching + metadata flattening), delete (resource + query string inspection), and query (chunk normalization + metadata filters) so we can evolve the adapter without hitting live Google endpoints.
- Updated `README.md` with a dedicated Gemini File Search section (usage snippet, behavior notes), marked the task complete in `PLAN.md`/`TODO.md`, and logged the research/testing details in `WORK.md` so the roadmap reflects the new adapter.
- Tests (macOS 14.6.1 / PHP 8.4.13, folder `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php`): `composer test` → **OK (199 tests / 777 assertions)** with the usual ParaGra pool chatter only.

## 2025-11-16 - Provider Catalog Builder & CLI

- Working in `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php` plus `../reference/` to deliver PLAN Workstream 1 / TODO RefCat #1 so the research markdown finally emits machine-audited provider metadata. Added `ParaGra\ReferenceCatalog\ProviderCatalogBuilder`, checksum enforcement, and a `tools/provider_catalog.php` CLI (build + verify) that writes `reference/catalog/provider_insights.json`.
- Committed `reference/catalog/provider_insights.source.json` with the first ten providers (Gemini Flash + Embeddings, Groq, OpenRouter, Mistral, Voyage, Cloudflare, Pinecone, Qdrant, Dify) covering modality, quota, reset windows, and recommended roles. Each entry now references the source markdown lines with SHA-256 hashes for auditability.
- Added PHPUnit coverage in `tests/ReferenceCatalog/ProviderCatalogBuilderTest.php`, refreshed PLAN/TODO/WORK, and documented how to regenerate/validate via the changelog entry.
- Tests (macOS 14.6.1 / PHP 8.4.13, folder `paragra-php/`): `composer test` → pass with the known 7 PHPUnit deprecation notices; `php tools/provider_catalog.php verify` → confirms the generated JSON matches the markdown hashes.


## 2025-12-15 - Chroma Vector Store

- Working in `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php` on PLAN §25 / TODO #350 after running Exa/Perplexity searches that showed the only public PHP clients for ChromaDB (`theogibbons/chroma-php` @ 2⭐, `CodeWithKyrian/chromadb-php` @ 73⭐) sit far below our 200⭐ support bar, so we extended the existing Guzzle runtime instead of pulling in another low-signal dependency.
- Added `src/VectorStore/ChromaVectorStore.php`, a tenant/database-aware adapter that hits `/api/v2/tenants/{tenant}/databases/{database}/collections/{collection}` for upserts, deletes, and queries, turns namespace metadata into `where` filters, injects document text automatically, converts distances to usable scores, and exposes tenant/database/collection diagnostics in `UnifiedResponse`.
- Created PHPUnit coverage in `tests/VectorStore/ChromaVectorStoreTest.php` to lock upsert/delete/query payloads, header propagation, filter shaping, and score normalization using `MockHandler` histories, following the test-first workflow before adding the adapter.
- Updated `paragra-php/README.md` with a Chroma section (constructor knobs, sample usage, behavior notes) and refreshed `PLAN.md`, `TODO.md`, and `WORK.md` so the roadmap + scratchpad reflect the completed adapter.
- Tests (macOS 14.6.1 / PHP 8.4.13, folder `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php`): `composer test` → **OK (197 tests / 771 assertions)** with the expected ParaGra pool-failover chatter only.

## 2025-12-14 - Weaviate Vector Store

- Working in `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php` on PLAN §25 / TODO #349 after confirming (via Exa search) that the only PHP SDK (`timkley/weaviate-php`) still sits at 35⭐—so we extended the existing Guzzle runtime instead of importing an under-starred dependency. Added `VectorStore/WeaviateVectorStore.php`, a REST + GraphQL adapter that batches upserts through `/v1/batch/objects`, deletes via match filters, and issues `nearVector` GraphQL queries with tenant + `consistency_level` overrides plus metadata-driven `WhereFilter` builders and configurable property projections.
- Created `tests/VectorStore/WeaviateVectorStoreTest.php` to cover batch upserts (headers/query params), batch deletes (ID filters), and GraphQL queries (tenant propagation, metadata filters, property selection) using `MockHandler` histories so we can prove payloads before shipping real integrations.
- Updated `paragra-php/README.md` with a Weaviate section documenting constructor parameters, multi-tenancy/consistency knobs, metadata filter behavior, and the `properties` override; refreshed `PLAN.md`, `TODO.md`, and `WORK.md` so the roadmap reflects the completed adapter and downstream docs stay accurate.
- Tests (macOS 14.6.1 / PHP 8.4.13, folder `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php`): `composer test` → **OK (194 tests / 735 assertions)** with the usual ParaGra pool-failover chatter only.

## 2025-12-13 - Pinecone + Qdrant Vector Stores

- Working in `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php` on PLAN §25 / TODO #347 to finally materialize the first vector store adapters. Added `VectorStore/PineconeVectorStore.php` (Guzzle-backed client that hits Pinecone's 2024 data plane endpoints, maps namespace metadata into Pinecone filters, and converts matches into `UnifiedResponse` chunks) plus `VectorStore/QdrantVectorStore.php` (REST wrapper for `/collections/{collection}/points` CRUD, including filter helpers for scalar/list payloads and chunk normalization).
- Created PHPUnit coverage in `tests/VectorStore/PineconeVectorStoreTest.php` and `tests/VectorStore/QdrantVectorStoreTest.php` that exercise upsert/delete/query payloads via `MockHandler`, assert headers (`Api-Key`, `api-key`), and verify that query responses become normalized `UnifiedResponse` instances with document IDs, scores, and preserved metadata.
- Updated `README.md` with usage sections for both adapters (constructor parameters, env expectations, option knobs) and logged the work in PLAN/TODO/WORK so the roadmap now reflects that Pinecone + Qdrant are live while Weaviate/Chroma/Gemini FS stay on deck.
- Tests (macOS 14.6.1 / PHP 8.4.13, folder `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php`): `composer test` → **OK (191 tests / 689 assertions)** with the usual ParaGra key-rotation chatter only.

## 2025-12-12 - Voyage Embedding Provider

- Working in `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php` to continue PLAN §25 / TODO #346 so ParaGra covers Voyage AI alongside the other embedding stacks. Added `Embedding/VoyageEmbeddingConfig.php` (env-driven model/input-type/dimension/batch controls + validation) and `Embedding/VoyageEmbeddingProvider.php` (Guzzle-backed POST to `https://api.voyageai.com/v1/embeddings`, trimming inputs, optional normalization, and metadata passthrough).
- Created PHPUnit coverage in `tests/Embedding/VoyageEmbeddingConfigTest.php` (env parsing, invalid input-type/encoding/dimension cases) and `tests/Embedding/VoyageEmbeddingProviderTest.php` (payload construction, handling `data` vs `embeddings` response shapes, normalization, batch limit enforcement, and error propagation).
- Updated `README.md`, `PLAN.md`, `TODO.md`, and `WORK.md` so the new provider is discoverable, the roadmap reflects the completed item, and the scratchpad logs include the research note that Voyage lacks a 200⭐ PHP SDK (hence the Guzzle integration).
- Tests (macOS 14.6.1 / PHP 8.4.13, folder `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php`): `composer test` → **OK (185 tests / 635 assertions)** with expected ParaGra fallback chatter only.

## 2025-12-11 - Gemini Embedding Provider

- Working in `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php` to advance PLAN §25 / TODO #345 by adding `Embedding/GeminiEmbeddingConfig.php` + `Embedding/GeminiEmbeddingProvider.php`, leveraging the 344⭐ `google-gemini-php/client` SDK so ParaGra can hit `text-embedding-004`/`embedding-001`, enforce the 250-item batch limit, support TaskType hints, and normalize embeddings while respecting Gemini's dimension rules.
- Added PHPUnit coverage in `tests/Embedding/GeminiEmbeddingConfigTest.php` (env parsing, fallback to `GOOGLE_API_KEY`, invalid task/dimension checks) and `tests/Embedding/GeminiEmbeddingProviderTest.php` (payload construction, normalization, metadata helpers, and error propagation) plus docs updates (`README.md`, `docs/configuration.md`, `DEPENDENCIES.md`, PLAN/TODO checkmarks).
- Composer dependency update: `composer require google-gemini-php/client:^2.7` so we rely on a maintained SDK instead of rolling custom HTTP; CHANGELOG/WORK logs capture the rationale and PoC verification.
- Tests (macOS 14.6.1 / PHP 8.4.13, folder `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php`): `composer test` → **OK (173 tests / 569 assertions)** with expected ParaGra fallback chatter only.

## 2025-12-10 - Cohere Embedding Provider

- Working in `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php` to advance PLAN §25 / TODO #344 by adding `Embedding\CohereEmbeddingConfig` + `Embedding\CohereEmbeddingProvider`, giving ParaGra a production-ready Cohere Embed v3 adapter with env-driven model/input-type/truncate controls, 96-item batch enforcement, immutable dimension metadata, and Guzzle-backed HTTP calls (official PHP SDKs remain <200⭐ so we stuck with the existing `guzzlehttp/guzzle` runtime).
- Added PHPUnit coverage in `tests/Embedding/CohereEmbeddingConfigTest.php` (env parsing and validation) and `tests/Embedding/CohereEmbeddingProviderTest.php` (payload construction, normalization behavior, error wrapping, metadata accessors).
- Extended `README.md` with a Cohere section documenting the env vars plus the rationale for using Guzzle, and updated PLAN/TODO tracking entries to mark the task complete.
- Tests (macOS 14.6.1 / PHP 8.4.13): `cd paragra-php && composer test` → **OK (164 tests / 525 assertions)** with expected ParaGra fallback chatter only.

## 2025-12-09 - OpenAI Embedding Provider

- Working in `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php` to advance PLAN §25 / TODO #342 by delivering the first concrete embedding adapter: `OpenAiEmbeddingConfig` + `OpenAiEmbeddingProvider`, built on `openai-php/client` with env-driven defaults (model/base URL/batch size/dimensions) plus input metadata passthrough.
- Added PHPUnit coverage in `tests/Embedding/OpenAiEmbeddingConfigTest.php` and `tests/Embedding/OpenAiEmbeddingProviderTest.php` to lock env parsing, payload construction, batch limit errors, normalization, and error wrapping.
- Refreshed `README.md` with an "OpenAI embedding provider" section that documents the required env vars (`OPENAI_API_KEY`, `OPENAI_EMBED_MODEL`, etc.), shows a usage snippet, and explains how ParaGra auto-picks 1536/3072 dimensions unless callers override them.
- Tests: `cd paragra-php && composer test` → **OK (148 tests / 436 assertions)** (expected ParaGra fallback chatter only).

## 2025-12-08 - Embedding + Vector Store Contracts

- Working in `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php` to kick off PLAN §25 / TODO #342 by introducing the shared embedding + vector store contracts that ParaGra needs before wiring Pinecone/Qdrant adapters.
- Added `Embedding\EmbeddingRequest` (batch inputs + metadata filters) and `Embedding\EmbeddingProviderInterface` so OpenAI/Cohere/Gemini providers can advertise supported dimensions + batch sizes consistently.
- Added `VectorStore\VectorNamespace` (slugged names, consistency hints, metadata) plus `VectorStoreInterface` that standardizes upsert/delete/query signatures for Pinecone/Weaviate/Qdrant/Chroma adapters.
- New PHPUnit coverage under `tests/Embedding/EmbeddingRequestTest.php` and `tests/VectorStore/VectorNamespaceTest.php` locks the validation + normalization semantics; refreshed README Components with the new modules.
- Tests: `cd paragra-php && composer test` → **OK (146 tests / 428 assertions)** (expected ParaGra fallback log chatter only).

## 2025-12-07 - Catalog-backed Pool Schema

- Working in `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php` to close PLAN §24 / TODO #337 by letting `ParaGra::fromConfig()` + `PriorityPool::fromArray()` resolve catalog slugs (OpenAI, Cerebras, Gemini, etc.) directly from `config/providers/catalog.php`, including `catalog_slug`/`catalog_overrides` helpers.
- Added metadata-aware overrides so pools can declare `latency_tier`, `cost_ceiling`, `compliance`, and arbitrary `metadata_overrides`, while retaining env-key overrides for rotating multiple keys per provider.
- Updated `config/paragra.example.php`, `docs/configuration.md`, and `README.md` to showcase the `provider_catalog` pointer plus the new syntax, and expanded `tests/Config/PriorityPoolTest.php` to cover slug-based resolution + error propagation.
- Tests: `cd paragra-php && composer test` → **OK (136 tests / 403 assertions)** (expected fallback log chatter only).

## 2025-12-06 - Provider Catalog Sync & Discovery

- Working in `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php` + sibling `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/vexy-co-model-catalog` to kick off PLAN §24 / TODO #334-339 by adding `tools/sync_provider_catalog.php`, a repeatable ingest that parses the Python dataset, merges capability presets (OpenAI, Groq, Cerebras, Gemini, AskYoda, Ragie), and emits `config/providers/catalog.{json,php}` with 45 provider entries, recommended models, embedding dims, and vector-store hints.
- Introduced `ProviderCatalog\CapabilityMap`, `ProviderCatalog\ProviderSummary`, and `ProviderCatalog\ProviderDiscovery` so ParaGra can filter providers, check embeddings/vector-store coverage, and build `ProviderSpec` structures from catalog slugs; documented helper methods for capability queries and default spec generation.
- Added PHPUnit coverage under `tests/ProviderCatalog/*` ensuring capability validation, discovery lookups, embedding-dimension helpers, vector preference lookups, and `buildProviderSpec()` env handling stay stable; ran `cd paragra-php && composer test` → **134 tests / 393 assertions / pass** (expected ParaGra fallback log chatter only).

## 2025-12-03 - PriorityPool Config Coverage

- Working in `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php` to close TODO #21 by hardening `tests/Config/PriorityPoolTest.php` with constructor guards, invalid pool shape assertions, and a real-world nested metadata scenario that mirrors `config/paragra.example.php`.
- No production code changed—these tests ensure ParaGra surfaces misconfigured pools immediately and preserves deep solution defaults/metadata for downstream components.
- Tests: `cd paragra-php && composer test` → **123 tests / 366 assertions / pass** (expected ParaGra fallback log chatter only).

## 2025-12-01 - Documentation Playbook

- Working within `paragra-php/docs` + `examples` to finish TODO #22: added configuration, migration, architecture (with ASCII diagrams), examples, and API reference guides, plus runnable config snippets for Cerebras/OpenAI/Gemini/AskYoda pools and a moderated answer script.
- Expanded `paragra-php/README.md` with a Quick Start checklist and an explanation of how `ask.vexy.art/private/vendor` vs `vendor-local` consume the local path repository.
- Tests: `cd paragra-php && composer test` → **119 tests / 356 assertions / pass** (expected fallback log chatter only).

## 2025-11-30 - Rotation & Fallback Test Coverage

- Working under `paragra-php/tests` to burn down TODO #21 by adding deterministic rotation coverage to `Router/KeyRotatorTest.php` (100/300/500 iteration sweeps that prove even distribution for 2, 3, and 5 key pools) plus three-tier fallback exercises in `Router/FallbackStrategyTest.php` that validate success paths and the propagated previous exception when every pool fails.
- Extended `ParaGraTest.php` with a provider-switching scenario that wires Cerebras (two tiers) and OpenAI (paid tier) stubs, ensuring the fallback chain hits each provider exactly once before succeeding and surfacing the recorded call order as regression coverage.
- Test suite: `cd paragra-php && composer test` → **119 tests / 356 assertions / pass** (expected fallback log chatter only).

## 2025-11-26 - ParaGra Client Facade

- Working inside `paragra-php/src` to satisfy PLAN §14.5 / TODO #19 by introducing `ParaGra\ParaGra`, a rotation-aware client that wires `PriorityPool`, `KeyRotator`, `FallbackStrategy`, and `ProviderFactory` together for both `retrieve()` and `answer()` flows plus optional moderation hooks.
- Added the dedicated PHPUnit suite `tests/ParaGraTest.php` covering config validation, moderation enforcement, fallback behavior, and prompt + LLM orchestration (boosting the suite to 113 tests / 338 assertions).
- Shipped `config/paragra.example.php` so users can bootstrap multi-pool deployments quickly, and updated `README.md` with concrete client usage and configuration guidance.

## 2025-11-15 - Moderation Interface & NullModerator

- Working inside `paragra-php/src/Moderation` to finish TODO #18, introduced `ModeratorInterface` plus a `NullModerator` no-op implementation so downstream clients can toggle moderation strategies without touching the OpenAI wiring.
- Updated `Assistant\RagAnswerer` and its PHPUnit suite to depend on the new interface + mocks, preserving behavior while enabling future moderators.
- Added `tests/Moderation/NullModeratorTest.php` to assert the no-op behavior and ran `cd paragra-php && composer test` → **108 tests / 318 assertions / pass** (expected fallback log noise only).

## 2025-11-15 - LLM + Moderation Stack Migration

- Ported Ragie's proven LLM pipeline into ParaGra: `ParaGra\Llm` now houses the OpenAI chat config/client, prompt builder, chat DTOs, and AskYoda fallback client/response with strict `this_file` markers.
- Added `ParaGra\Assistant\RagAnswerer` + `RagAnswer` so ParaGra can orchestrate retrieval, OpenAI completion, moderation, metrics, and AskYoda fallback without touching ragie-php yet.
- Moved safety/utility pieces (`ParaGra\Moderation\{OpenAiModerator,ModerationResult,ModerationException}`, `ParaGra\Util\ConfigValidator`, `ParaGra\Support\ExceptionEnhancer`) plus fresh PHPUnit coverage mirroring the Ragie suite (new tests under `tests/{Assistant,Llm,Moderation,Support,Util}` and shared `tests/Logging/SpyLogger` helper).
- Declared direct dependencies on `openai-php/client` + `guzzlehttp/guzzle` and wired a PHPUnit bootstrap that enables `dg/bypass-finals`, yielding `composer test` → **106 tests / 314 assertions / pass**.

## 2025-11-24 - Provider Implementations

- Added production-ready provider adapters: `RagieProvider`, `GeminiFileSearchProvider`, and `AskYodaProvider`, each exposing sanitized retrieval plus metadata/cost/usage normalization through `UnifiedResponse`.
- Introduced `ProviderFactory` to hydrate providers + NeuronAI adapters from `ProviderSpec`, and wired a new `Llm\NeuronAiAdapter` wrapper around `neuron-core/neuron-ai` for OpenAI, Gemini, Anthropic, Mistral, Groq, Cerebras, Deepseek, and X.ai.
- Expanded `UnifiedResponse` with a `fromChunks()` factory to simplify DTO creation, plus comprehensive PHPUnit coverage for every new component (providers, factory, adapter).
- Provider configuration now respects solution defaults (partition, vector store, AskYoda tuning) and fetches chunk payloads from EdenAI/Gemini HTTP APIs via injected Guzzle clients.

## 2025-11-23 - Priority Pools & Fallback Routing

- Added `src/Config/PriorityPool.php` with strict config validation so priority pools can be parsed from nested arrays safely.
- Added `src/Router/KeyRotator.php` (timestamp-driven rotation with injectable clock) and `src/Router/FallbackStrategy.php` (pool walker that logs failures and raises when all pools exhaust).
- Expanded PHPUnit coverage via `tests/Config/PriorityPoolTest.php` and `tests/Router/{KeyRotatorTest,FallbackStrategyTest}.php`, bringing the suite to 14 tests / 33 assertions (`composer test`).

## 2025-11-22 - Project Scaffolding

- Created the `paragra-php` package skeleton with Composer metadata, PSR-4 autoloading, QA scripts, and dev tooling (PHPStan, Psalm, PHPUnit, php-cs-fixer).
- Installed runtime dependencies (`ragie/ragie-php`, `neuron-core/neuron-ai`, `google/cloud-ai-platform`) with a path repository linking to the local Ragie SDK copy.
- Added baseline documentation (`README.md`, `DEPENDENCIES.md`) and configuration files (`phpunit.xml.dist`, `phpstan.neon.dist`, `psalm.xml`, `.gitignore`).
- Established placeholder directories for `src/`, `tests/`, `examples/`, `config/`, and `docs/`.

## 2025-11-15 - Provider Contracts & Unified Response

- Implemented the provider contract layer via `src/Providers/ProviderInterface.php` and `src/Providers/AbstractProvider.php`, including capability lookups, sanitized query helpers, and consistent metadata builders.
- Added `src/Response/UnifiedResponse.php` to normalize chunks, metadata, cost, and usage across providers so upcoming Ragie/Gemini adapters can share the same DTO.
- Expanded PHPUnit coverage with `tests/Providers/AbstractProviderTest.php` and `tests/Response/UnifiedResponseTest.php`, bringing the suite to 26 tests / 64 assertions (`composer test`).
