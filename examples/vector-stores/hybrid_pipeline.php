#!/usr/bin/env php
<?php

declare(strict_types=1);

// this_file: paragra-php/examples/vector-stores/hybrid_pipeline.php

use ParaGra\Embedding\OpenAiEmbeddingConfig;
use ParaGra\Embedding\OpenAiEmbeddingProvider;
use ParaGra\ParaGra;
use ParaGra\Pipeline\HybridRetrievalPipeline;
use ParaGra\VectorStore\ChromaVectorStore;
use ParaGra\VectorStore\GeminiFileSearchVectorStore;
use ParaGra\VectorStore\PineconeVectorStore;
use ParaGra\VectorStore\QdrantVectorStore;
use ParaGra\VectorStore\VectorNamespace;
use ParaGra\VectorStore\VectorStoreInterface;
use ParaGra\VectorStore\WeaviateVectorStore;
use RuntimeException;

require __DIR__ . '/../../vendor/autoload.php';

$question = $argv[1] ?? 'What does ParaGra do?';
$configPath = $argv[2] ?? __DIR__ . '/../config/ragie_cerebras.php';

if (!is_file($configPath)) {
    throw new RuntimeException(sprintf('Config file not found: %s', $configPath));
}

$config = require $configPath;
$paragra = ParaGra::fromConfig($config);
$embedding = new OpenAiEmbeddingProvider(OpenAiEmbeddingConfig::fromEnv());

$storeType = getenv('HYBRID_STORE') ?: 'pinecone';
$vectorStore = null;
$namespace = null;

switch ($storeType) {
    case 'pinecone':
        $pineconeBaseUrl = getenv('PINECONE_BASE_URL');
        $pineconeApiKey = getenv('PINECONE_API_KEY');
        $pineconeIndex = getenv('PINECONE_INDEX');
        if (!is_string($pineconeBaseUrl) || $pineconeBaseUrl === '') {
            throw new RuntimeException('Set PINECONE_BASE_URL for the Pinecone example.');
        }
        if (!is_string($pineconeApiKey) || $pineconeApiKey === '') {
            throw new RuntimeException('Set PINECONE_API_KEY for the Pinecone example.');
        }
        if (!is_string($pineconeIndex) || $pineconeIndex === '') {
            throw new RuntimeException('Set PINECONE_INDEX for the Pinecone example.');
        }

        $pineconeNamespace = getenv('PINECONE_NAMESPACE') ?: 'ragie-hybrid';
        $namespace = new VectorNamespace($pineconeNamespace);
        $vectorStore = new PineconeVectorStore(
            baseUrl: $pineconeBaseUrl,
            apiKey: $pineconeApiKey,
            indexName: $pineconeIndex,
            defaultNamespace: $namespace,
        );
        break;

    case 'qdrant':
        $qdrantUrl = getenv('QDRANT_URL');
        $qdrantCollection = getenv('QDRANT_COLLECTION');
        if (!is_string($qdrantUrl) || $qdrantUrl === '') {
            throw new RuntimeException('Set QDRANT_URL for the Qdrant example.');
        }
        if (!is_string($qdrantCollection) || $qdrantCollection === '') {
            throw new RuntimeException('Set QDRANT_COLLECTION for the Qdrant example.');
        }

        $namespace = new VectorNamespace($qdrantCollection);
        $vectorStore = new QdrantVectorStore(
            baseUrl: $qdrantUrl,
            collection: $qdrantCollection,
            apiKey: getenv('QDRANT_API_KEY') ?: null,
            defaultNamespace: $namespace,
        );
        break;

    case 'weaviate':
        $weaviateUrl = getenv('WEAVIATE_URL');
        $weaviateClass = getenv('WEAVIATE_CLASS');
        if (!is_string($weaviateUrl) || $weaviateUrl === '') {
            throw new RuntimeException('Set WEAVIATE_URL for the Weaviate example.');
        }
        if (!is_string($weaviateClass) || $weaviateClass === '') {
            throw new RuntimeException('Set WEAVIATE_CLASS for the Weaviate example.');
        }

        $tenant = getenv('WEAVIATE_TENANT');
        $metadata = $tenant !== false && $tenant !== '' ? ['tenant' => $tenant] : [];

        $namespace = new VectorNamespace(
            name: getenv('WEAVIATE_NAMESPACE') ?: 'ragie-hybrid',
            collection: $weaviateClass,
            metadata: $metadata,
        );

        $vectorStore = new WeaviateVectorStore(
            baseUrl: $weaviateUrl,
            className: $weaviateClass,
            apiKey: getenv('WEAVIATE_API_KEY') ?: null,
            defaultNamespace: $namespace,
            consistencyLevel: getenv('WEAVIATE_CONSISTENCY') ?: 'ONE',
        );
        break;

    case 'chroma':
        $chromaUrl = getenv('CHROMA_URL');
        $chromaTenant = getenv('CHROMA_TENANT');
        $chromaDatabase = getenv('CHROMA_DATABASE');
        $chromaCollection = getenv('CHROMA_COLLECTION');
        foreach ([
            'CHROMA_URL' => $chromaUrl,
            'CHROMA_TENANT' => $chromaTenant,
            'CHROMA_DATABASE' => $chromaDatabase,
            'CHROMA_COLLECTION' => $chromaCollection,
        ] as $label => $value) {
            if (!is_string($value) || $value === '') {
                throw new RuntimeException(sprintf('Set %s for the Chroma example.', $label));
            }
        }

        $namespace = new VectorNamespace(
            name: $chromaCollection,
            collection: $chromaCollection,
            metadata: ['source' => 'ragie'],
        );

        $vectorStore = new ChromaVectorStore(
            baseUrl: $chromaUrl,
            tenant: $chromaTenant,
            database: $chromaDatabase,
            collection: $chromaCollection,
            defaultNamespace: $namespace,
            authToken: getenv('CHROMA_TOKEN') ?: null,
        );
        break;

    case 'gemini':
    case 'gemini-file-search':
        $googleApiKey = getenv('GOOGLE_API_KEY');
        $geminiResource = getenv('GEMINI_DATASTORE_ID') ?: getenv('GEMINI_CORPUS_ID');
        if (!is_string($googleApiKey) || $googleApiKey === '') {
            throw new RuntimeException('Set GOOGLE_API_KEY for the Gemini File Search example.');
        }
        if (!is_string($geminiResource) || $geminiResource === '') {
            throw new RuntimeException('Set GEMINI_DATASTORE_ID (or GEMINI_CORPUS_ID) for the Gemini example.');
        }

        $namespace = new VectorNamespace(
            name: getenv('GEMINI_NAMESPACE') ?: 'ragie-hybrid',
            collection: $geminiResource,
            eventuallyConsistent: true,
            metadata: ['source' => 'ragie'],
        );

        $vectorStore = new GeminiFileSearchVectorStore(
            apiKey: $googleApiKey,
            resourceName: $geminiResource,
            defaultNamespace: $namespace,
        );
        $storeType = 'gemini-file-search';
        break;

    default:
        throw new RuntimeException(sprintf('Unsupported HYBRID_STORE value: %s', $storeType));
}

if (!$vectorStore instanceof VectorStoreInterface || !$namespace instanceof VectorNamespace) {
    throw new RuntimeException('Failed to initialize the requested vector store.');
}

$pipeline = new HybridRetrievalPipeline([$paragra, 'retrieve'], $embedding, $vectorStore, $namespace);

$retrievalTopK = (int) (getenv('HYBRID_RETRIEVAL_TOPK') ?: 6);
if ($retrievalTopK <= 0) {
    $retrievalTopK = 6;
}

$vectorTopK = (int) (getenv('HYBRID_VECTOR_TOPK') ?: 6);
if ($vectorTopK <= 0) {
    $vectorTopK = 6;
}

$hybridLimit = (int) (getenv('HYBRID_LIMIT') ?: 8);
if ($hybridLimit <= 0) {
    $hybridLimit = 8;
}

if (getenv('HYBRID_SEED_FIRST') === '1') {
    $seedResult = $pipeline->ingestFromRagie($question, [
        'retrieval' => ['top_k' => $retrievalTopK],
    ]);

    fwrite(
        STDERR,
        sprintf(
            "Seeded %d Ragie chunks into %s (%s)\n",
            $seedResult['ingested_chunks'],
            $storeType,
            $namespace->getName(),
        )
    );
}

$result = $pipeline->hybridRetrieve($question, [
    'retrieval' => ['top_k' => $retrievalTopK],
    'vector_store' => ['top_k' => $vectorTopK],
    'hybrid_limit' => $hybridLimit,
]);

$payload = [
    'question' => $question,
    'store' => $storeType,
    'ragie_provider' => $result['ragie']->getProvider(),
    'vector_store_provider' => $result['vector_store']->getProvider(),
    'combined_chunks' => $result['combined']->getChunks(),
];

fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
