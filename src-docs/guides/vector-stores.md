# Vector Store Adapters

ParaGra provides adapters for major vector databases.

## Supported stores

- **Pinecone**: Serverless and pod-based indexes
- **Weaviate**: GraphQL-based semantic search
- **Qdrant**: REST API vector database
- **Chroma**: Embedded and managed ChromaDB
- **Gemini File Search**: Google-managed semantic search

## Common interface

All adapters implement `VectorStoreInterface` with unified operations:

- `upsert()` — Insert or update vectors
- `delete()` — Remove vectors by ID
- `query()` — Semantic search
- `getDefaultNamespace()` — Get default collection/namespace

## Quick examples

See [Vector Store Architecture](../architecture/vector-stores.md) for detailed examples of each adapter.

## Hybrid retrieval

Combine vector stores with Ragie using `HybridRetrievalPipeline`:

```php
$pipeline = new HybridRetrievalPipeline(
    [$paragra, 'retrieve'],
    $embeddingProvider,
    $vectorStore,
    $namespace
);

$result = $pipeline->hybridRetrieve('query', [
    'retrieval' => ['top_k' => 6],
    'vector_store' => ['top_k' => 6],
]);
```

See [Hybrid Retrieval](hybrid-retrieval.md) for details.
