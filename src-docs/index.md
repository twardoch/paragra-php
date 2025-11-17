# ParaGra PHP Documentation

`paragra-php` is a provider-agnostic PHP toolkit for orchestrating Retrieval-Augmented Generation (RAG) and LLM calls across Ragie, Gemini File Search, AskYoda, and future providers.

- **Language**: PHP 8.1+
- **Focus**: RAG orchestration, provider pools, vector stores, and embeddings.
- **Status**: Pre-release. Breaking changes are allowed because nothing has shipped yet.

## Why this library exists
- Provide provider-agnostic RAG orchestration without vendor lock-in.
- Enable automatic fallback and key rotation across free and paid tiers.
- Offer unified interfaces for embeddings and vector stores (Pinecone, Weaviate, Qdrant, Chroma, Gemini File Search).
- Keep orchestration separate from provider-specific SDKs.

## What lives where
| Layer | Repository | Responsibilities |
| ----- | ---------- | ---------------- |
| SDK | `ragie-php` | HTTP client, auth, retries, caching, typed models |
| Orchestration | `paragra-php` | Provider pools, fallback plans, embeddings/vector adapters |
| Demo app | `ask.vexy.art` | Reference web app + smoke-test target |

Keep these layers decoupled—`paragra-php` depends on `ragie-php` but remains provider-neutral.

## Documentation structure
- [Getting Started](getting-started/installation.md) covers installation, configuration, and the first query.
- [Architecture](architecture/overview.md) explains provider pools, rotation logic, and extension points.
- [Guides](guides/provider-catalog.md) provide production-ready patterns for embeddings, vector stores, and hybrid retrieval.
- [How-To](how-to/pool-builder.md) documents testing, pool configuration, and CLI utilities.
- [Reference](reference/cli.md) lists utility scripts, docs tooling, and development workflows.

Use the left navigation or search (⌘K / Ctrl+K) to jump around.
