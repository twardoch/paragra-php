# Architecture Overview

ParaGra is a provider-agnostic orchestration layer that sits above specific AI service SDKs.

## Core components

```
┌─────────────────────────────────────────┐
│        Application Layer                │
│    (ask.vexy.art, custom apps)         │
└─────────────┬───────────────────────────┘
              │
┌─────────────▼───────────────────────────┐
│         ParaGra\ParaGra                  │
│  (retrieve, answer, withModeration)      │
└─┬───────────────────────────────────┬───┘
  │                                   │
  │ ┌─────────────────────────────┐   │
  │ │   Priority Pools             │   │
  │ │  - PriorityPool              │   │
  │ │  - KeyRotator                │   │
  │ │  - FallbackStrategy          │   │
  │ └─────────────────────────────┘   │
  │                                   │
  ├─────────────┬─────────────────────┤
  │             │                     │
┌─▼─────────┐ ┌─▼─────────┐ ┌───────▼──────┐
│ Retrieval │ │ Generation│ │  Embeddings  │
│ Providers │ │ Providers │ │  Providers   │
└───────────┘ └───────────┘ └──────────────┘
│ - Ragie   │ │ - OpenAI  │ │ - OpenAI     │
│ - Gemini  │ │ - Cerebras│ │ - Cohere     │
│   File    │ │ - Groq    │ │ - Gemini     │
│   Search  │ │ - Gemini  │ │ - Voyage     │
└───────────┘ └───────────┘ └──────────────┘
              │
        ┌─────▼─────────────────────┐
        │   Vector Stores           │
        │ - Pinecone                │
        │ - Weaviate                │
        │ - Qdrant                  │
        │ - Chroma                  │
        │ - Gemini File Search      │
        └───────────────────────────┘
```

## Layer responsibilities

### Application layer
- **ask.vexy.art**: Reference web app that consumes ParaGra
- **Custom apps**: Your own projects using ParaGra as a dependency

### Orchestration layer (ParaGra)
- **ParaGra\ParaGra**: Main entry point with `retrieve()` and `answer()` methods
- **Pool management**: Rotation, fallback, key management
- **Moderation**: Optional content safety checks (OpenAI Moderation)
- **Response normalization**: Unified response format across all providers

### Provider adapters
- **Retrieval**: Ragie (via `ragie-php`), Gemini File Search
- **Generation**: OpenAI, Cerebras, Groq, Gemini, AskYoda
- **Embeddings**: OpenAI, Cohere, Gemini, Voyage
- **Vector stores**: Pinecone, Weaviate, Qdrant, Chroma, Gemini File Search

## Key design principles

### 1. Provider neutrality
ParaGra never hard-codes provider-specific logic in the orchestration layer. All provider details live in adapters.

### 2. Automatic fallback
When a provider fails (rate limit, timeout, error), ParaGra automatically tries the next provider in the pool without manual intervention.

### 3. Unified responses
All retrieval providers return `UnifiedResponse` objects with normalized chunks, metadata, and usage information.

### 4. Zero vendor lock-in
Swap providers by changing configuration—no code changes required.

## Data flow

### Retrieval flow
```
User query
    ↓
ParaGra::retrieve($query, $options)
    ↓
PriorityPool (try Pool 1 providers)
    ↓
Provider adapter (Ragie, Gemini, etc.)
    ↓
UnifiedResponse (normalized chunks)
    ↓
Return to application
```

### Answer flow (RAG + generation)
```
User question
    ↓
ParaGra::answer($question, $options)
    ↓
1. Optional moderation check
    ↓
2. Retrieve context (via PriorityPool)
    ↓
3. Build prompt with context chunks
    ↓
4. Generate answer (via generation provider)
    ↓
Return answer + context + metadata
```

### Hybrid retrieval flow
```
User query
    ↓
HybridRetrievalPipeline
    ↓
├─ Ragie retrieval (keyword/KB)
│  ↓
│  Chunks
└─ Vector store query (semantic)
   ↓
   Chunks
    ↓
Merge + deduplicate + rerank
    ↓
UnifiedResponse (combined chunks)
```

## Extension points

ParaGra is designed for easy extension:

1. **New providers**: Implement adapter interfaces and add to pool config
2. **Custom moderation**: Implement `ModeratorInterface`
3. **Custom vector stores**: Implement `VectorStoreInterface`
4. **Custom embedding providers**: Implement `EmbeddingProviderInterface`
5. **Custom prompt builders**: Extend `PromptBuilder` for domain-specific formats

## Next steps

- Read [Provider Pools](pools.md) for rotation and fallback details
- Explore [Vector Stores](vector-stores.md) for storage adapter architecture
- Review [Provider Catalog](../guides/provider-catalog.md) for metadata-driven configuration
