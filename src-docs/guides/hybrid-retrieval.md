# Hybrid Retrieval

Combine keyword-based retrieval (Ragie) with semantic vector search for better results.

## Overview

`HybridRetrievalPipeline` blends two retrieval strategies:

1. **Keyword/KB search** — Ragie's optimized retrieval
2. **Semantic search** — Vector store similarity queries

Results are merged, deduplicated, and optionally reranked.

## Basic usage

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
    indexName: 'docs',
    defaultNamespace: $namespace,
);

$pipeline = new HybridRetrievalPipeline(
    [$paragra, 'retrieve'],
    $embedding,
    $vectorStore,
    $namespace
);
```

## Ingest from Ragie

Fetch Ragie chunks and store in vector database:

```php
$pipeline->ingestFromRagie('What is ParaGra?', [
    'retrieval' => ['top_k' => 10],
]);
```

## Hybrid retrieval

Query both sources and merge results:

```php
$result = $pipeline->hybridRetrieve('What is ParaGra?', [
    'retrieval' => ['top_k' => 6],      // Ragie results
    'vector_store' => ['top_k' => 6],   // Vector store results
    'hybrid_limit' => 8,                // Max combined results
]);

$ragieChunks = $result['ragie']->getChunks();
$vectorChunks = $result['vector']->getChunks();
$combinedChunks = $result['combined']->getChunks();
```

## Example script

```bash
export RAGIE_API_KEY=sk_live_...
export OPENAI_API_KEY=sk-...
export PINECONE_API_KEY=...
export PINECONE_BASE_URL=https://...
export HYBRID_STORE=pinecone
export HYBRID_SEED_FIRST=1

php examples/vector-stores/hybrid_pipeline.php "Explain RAG"
```

See `examples/vector-stores/hybrid_pipeline.php` for full implementation.
