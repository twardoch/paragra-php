# Vector Store Architecture

ParaGra provides a unified interface for vector databases through the `VectorStoreInterface`.

## Supported vector stores

- **Pinecone**: Serverless and pod-based indexes
- **Weaviate**: Self-hosted and managed cloud
- **Qdrant**: Local, Docker, and managed cloud
- **Chroma**: Local and managed ChromaDB
- **Gemini File Search**: Google-managed semantic search

## Core interface

All vector store adapters implement:

```php
interface VectorStoreInterface
{
    public function upsert(
        VectorNamespace $namespace,
        array $vectors,
        array $options = []
    ): void;

    public function delete(
        VectorNamespace $namespace,
        array $ids,
        array $options = []
    ): void;

    public function query(
        VectorNamespace $namespace,
        array $vector,
        array $options = []
    ): UnifiedResponse;

    public function getDefaultNamespace(): VectorNamespace;
}
```

## Vector namespace

`VectorNamespace` encapsulates collection/index identity and metadata:

```php
use ParaGra\VectorStore\VectorNamespace;

$namespace = new VectorNamespace(
    name: 'kb',
    collection: 'knowledge-base',
    metadata: ['source' => 'docs', 'tier' => 'production']
);
```

Namespace metadata becomes filters in query operations.

## Vector format

All adapters accept this normalized vector format:

```php
[
    'id' => 'doc-1',
    'values' => [0.1, 0.2, 0.3, ...], // Embedding vector
    'metadata' => [
        'text' => 'Chunk body',
        'source' => 'kb',
        'url' => 'https://example.com/doc',
    ],
]
```

The `text` field (or `content`/`body`) is extracted for chunk responses.

## Adapter specifics

### Pinecone
```php
use ParaGra\VectorStore\PineconeVectorStore;
use ParaGra\VectorStore\VectorNamespace;

$store = new PineconeVectorStore(
    baseUrl: 'https://my-index.svc.us-west1-aws.pinecone.io',
    apiKey: getenv('PINECONE_API_KEY'),
    indexName: 'docs-index',
    defaultNamespace: new VectorNamespace('public-kb'),
);
```

**Key features:**
- Serverless and pod-based index support
- Namespace isolation
- Metadata filtering with `$eq` and `$in` operators

### Weaviate
```php
use ParaGra\VectorStore\WeaviateVectorStore;
use ParaGra\VectorStore\VectorNamespace;

$namespace = new VectorNamespace(
    name: 'kb',
    collection: 'Articles',
    metadata: ['tenant' => 'tenant-a'],
);

$store = new WeaviateVectorStore(
    baseUrl: 'https://demo.weaviate.network',
    className: 'Articles',
    apiKey: getenv('WEAVIATE_API_KEY'),
    defaultNamespace: $namespace,
    defaultProperties: ['text', 'title', 'url'],
);
```

**Key features:**
- Multi-tenancy support via namespace metadata
- GraphQL-based queries with property selection
- Consistency level control

### Qdrant
```php
use ParaGra\VectorStore\QdrantVectorStore;
use ParaGra\VectorStore\VectorNamespace;

$store = new QdrantVectorStore(
    baseUrl: 'http://localhost:6333',
    collection: 'docs',
    apiKey: getenv('QDRANT_API_KEY'),
);
```

**Key features:**
- REST API integration
- Wait-for-sync option for immediate consistency
- Payload filtering with `must` clauses

### Chroma
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
```

**Key features:**
- Multi-tenant and multi-database support
- Automatic document field extraction
- Metadata-driven `where` clause filters

### Gemini File Search
```php
use ParaGra\VectorStore\GeminiFileSearchVectorStore;
use ParaGra\VectorStore\VectorNamespace;

$resource = getenv('GEMINI_DATASTORE_ID') ?: getenv('GEMINI_CORPUS_ID');
$store = new GeminiFileSearchVectorStore(
    apiKey: getenv('GOOGLE_API_KEY'),
    resourceName: $resource,
);

$namespace = new VectorNamespace('kb', $resource, metadata: ['source' => 'kb']);
```

**Key features:**
- Google-managed chunking and embedding
- Natural-language query (no vector required)
- Supports `fileSearchStores/*`, `dataStores/*`, and `corpora/*` resources

## Metadata filtering

Namespace metadata automatically becomes query filters:

```php
$namespace = new VectorNamespace('kb', metadata: [
    'source' => 'docs',          // Scalar: exact match
    'tags' => ['public', 'kb'],  // List: "in" match
]);

$response = $store->query($namespace, $queryVector, ['top_k' => 5]);
```

**Filter conversion:**
- **Pinecone**: `{'source': {'$eq': 'docs'}, 'tags': {'$in': ['public', 'kb']}}`
- **Weaviate**: GraphQL `WhereFilter` with `Equal` and `ContainsAny`
- **Qdrant**: `must` clauses with scalar/array matches
- **Chroma**: `{'source': 'docs', 'tags': {'$in': ['public', 'kb']}}`

## Unified responses

All query operations return `UnifiedResponse`:

```php
$response = $store->query($namespace, $queryVector, ['top_k' => 5]);

foreach ($response->getChunks() as $chunk) {
    echo "Text: {$chunk['text']}\n";
    echo "Score: {$chunk['score']}\n";
    echo "Source: {$chunk['metadata']['source']}\n";
}

echo "Provider: {$response->getProvider()}\n";
echo "Model: {$response->getModel()}\n";
```

## Hybrid retrieval

Combine vector stores with Ragie for hybrid keyword + semantic search:

```php
use ParaGra\Pipeline\HybridRetrievalPipeline;

$pipeline = new HybridRetrievalPipeline(
    [$paragra, 'retrieve'],
    $embeddingProvider,
    $vectorStore,
    $namespace
);

$result = $pipeline->hybridRetrieve('What is ParaGra?', [
    'retrieval' => ['top_k' => 6],
    'vector_store' => ['top_k' => 6],
    'hybrid_limit' => 8,
]);
```

See [Hybrid Retrieval](../guides/hybrid-retrieval.md) for details.

## Next steps

- Read [Embedding Providers](../guides/embeddings.md) to generate vectors
- Explore [Hybrid Retrieval](../guides/hybrid-retrieval.md) for combined queries
- Review [Vector Store Adapters](../guides/vector-stores.md) for usage examples
