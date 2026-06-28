---
this_file: paragra-php/src-docs/architecture/provider-matrix.md
---

# Provider Matrix

This table lists every provider adapter that ships with `paragra-php`, the capability it covers, and the adapter class that implements it.

## Retrieval providers

| Provider slug | Adapter class | Capabilities | Notes |
|---|---|---|---|
| `ragie` | `Providers\RagieProvider` | retrieval | Full-text + semantic via Ragie API; depends on `ragie-php` |
| `gemini` | `Providers\GeminiProvider` | retrieval, embedding | Uses Gemini File Search for retrieval; `gemini-embedding-exp` for embeddings |
| `askyoda` | `Providers\AskYodaProvider` | retrieval | AskYoda-hosted RAG service |
| `openai` | `Providers\OpenAiProvider` | generation, embedding | GPT generation + `text-embedding-3-*` family |
| `cerebras` | `Providers\CerebrasProvider` | generation | Fast-inference LLM host; no retrieval adapter |
| `neuron` | `Llm\NeuronAiAdapter` | generation | Neuron AI backend via `neuron-core/neuron-ai` |

## Vector store adapters

| Store slug | Adapter class | Protocol |
|---|---|---|
| `pinecone` | `VectorStore\PineconeVectorStore` | Pinecone 2024 data-plane REST API |
| `weaviate` | `VectorStore\WeaviateVectorStore` | Weaviate v4 GraphQL + REST |
| `qdrant` | `VectorStore\QdrantVectorStore` | Qdrant REST API |
| `chroma` | `VectorStore\ChromaVectorStore` | Chroma HTTP API v1 |
| `gemini_file_search` | `VectorStore\GeminiFileSearchVectorStore` | Gemini File Search gRPC/REST |

All vector store adapters implement `VectorStore\VectorStoreInterface`, which defines `upsert()`, `delete()`, and `query()`.

## Embedding providers

| Provider slug | Adapter class | Default model |
|---|---|---|
| `openai` | `Embedding\OpenAiEmbeddingProvider` | `text-embedding-3-small` |
| `gemini` | `Embedding\GeminiEmbeddingProvider` | `gemini-embedding-exp-03-07` |
| `cohere` | `Embedding\CohereEmbeddingProvider` | `embed-english-v3.0` |
| `voyage` | `Embedding\VoyageEmbeddingProvider` | `voyage-3-lite` |

## Moderation providers

| Slug | Class | Notes |
|---|---|---|
| `openai` | `Moderation\OpenAiModerator` | Uses `omni-moderation-latest` |
| `null` | `Moderation\NullModerator` | No-op (use in tests or trusted contexts) |

## External search enrichment

| Slug | Class | Notes |
|---|---|---|
| `openai_web` | `ExternalSearch\OpenAiWebSearchProvider` | Web search via OpenAI's `web_search_preview` tool |

## Pool configuration example

```php
return [
    'priority_pools' => [
        // Tier 0 – free keys, try all before falling back
        [
            [
                'provider' => 'ragie',
                'model'    => 'default',
                'api_key'  => $_ENV['RAGIE_API_KEY'],
                'solution' => [
                    'type'     => 'ragie',
                    'metadata' => ['plan' => 'free'],
                ],
            ],
        ],
        // Tier 1 – paid key, single attempt (hosted SLA)
        [
            [
                'provider' => 'openai',
                'model'    => 'gpt-4o-mini',
                'api_key'  => $_ENV['OPENAI_API_KEY'],
                'solution' => [
                    'type'     => 'openai',
                    'metadata' => ['plan' => 'hosted'],
                ],
            ],
        ],
    ],
];
```

See [Provider Pools](pools.md) for a full description of the pool spec keys.
