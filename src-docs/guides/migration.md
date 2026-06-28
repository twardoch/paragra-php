---
this_file: paragra-php/src-docs/guides/migration.md
---

# Migration Guide

This guide helps you move from calling provider SDKs directly to using ParaGra's unified orchestration layer.

## Why migrate?

Calling provider SDKs directly means:

- You handle rate limiting, fallback, and key rotation yourself.
- Switching providers requires rewriting call sites.
- Retrieval and generation are wired together ad-hoc.

ParaGra gives you a single `answer()` call with automatic multi-provider fallback and key rotation, while keeping all provider adapters interchangeable.

---

## From ragie-php directly

### Before

```php
use Ragie\Client;
use Ragie\Models\RetrieveParams;

$client = Client::builder()->security('YOUR_RAGIE_KEY')->build();

$response = $client->retrievals->retrieve(
    new RetrieveParams(query: 'What is the refund policy?', rerank: true)
);

foreach ($response->scoredChunks as $chunk) {
    echo $chunk->text . "\n";
}
```

### After

```php
use ParaGra\ParaGra;

$paragra = ParaGra::fromConfig([
    'priority_pools' => [
        [
            [
                'provider' => 'ragie',
                'model'    => 'default',
                'api_key'  => $_ENV['RAGIE_API_KEY'],
                'solution' => ['type' => 'ragie'],
            ],
        ],
    ],
]);

$result = $paragra->retrieve('What is the refund policy?', ['top_k' => 5]);

foreach ($result->getChunks() as $chunk) {
    echo $chunk['text'] . "\n";
}
```

**What changed:**

- `Client` → `ParaGra::fromConfig()`
- `$client->retrievals->retrieve()` → `$paragra->retrieve()`
- `scoredChunks` → `getChunks()` (normalised array with `text`, `score`, `document_id`, `metadata`)
- Add a second tier to the pool config and you get automatic fallback at zero extra code cost.

---

## From openai-php directly (chat completion)

### Before

```php
use OpenAI;

$client = OpenAI::client($_ENV['OPENAI_API_KEY']);

$response = $client->chat()->create([
    'model'    => 'gpt-4o-mini',
    'messages' => [
        ['role' => 'user', 'content' => 'What is the refund policy?'],
    ],
]);

echo $response->choices[0]->message->content;
```

### After

```php
use ParaGra\ParaGra;

$paragra = ParaGra::fromConfig([
    'priority_pools' => [
        [
            [
                'provider' => 'ragie',
                'model'    => 'default',
                'api_key'  => $_ENV['RAGIE_API_KEY'],
                'solution' => ['type' => 'ragie'],
            ],
        ],
        [
            [
                'provider' => 'openai',
                'model'    => 'gpt-4o-mini',
                'api_key'  => $_ENV['OPENAI_API_KEY'],
                'solution' => ['type' => 'openai'],
            ],
        ],
    ],
]);

$result = $paragra->answer('What is the refund policy?', [
    'generation' => ['temperature' => 0.2],
]);

echo $result['answer'];
```

**What changed:**

- Retrieval is now handled automatically before the LLM call.
- The LLM only receives context-grounded content — reducing hallucination.
- Swap the OpenAI spec for a Cerebras or Gemini spec to change LLM without rewriting application code.

---

## From google-gemini-php directly

### Before

```php
use Gemini\Client;

$client = new Client($_ENV['GEMINI_API_KEY']);
$result = $client->generativeModel('gemini-2.0-flash')->generateContent('Explain RAG in PHP');
echo $result->text();
```

### After

```php
use ParaGra\ParaGra;

$paragra = ParaGra::fromConfig([
    'priority_pools' => [
        [
            [
                'provider' => 'gemini',
                'model'    => 'gemini-2.0-flash',
                'api_key'  => $_ENV['GEMINI_API_KEY'],
                'solution' => ['type' => 'gemini'],
            ],
        ],
    ],
]);

$result = $paragra->answer('Explain RAG in PHP', [
    'generation' => ['temperature' => 0.4],
]);

echo $result['answer'];
```

---

## From a vector store SDK directly (Pinecone example)

### Before

```php
// Raw Guzzle calls to Pinecone
$http = new GuzzleHttp\Client([
    'base_uri' => 'https://my-index.pinecone.io',
    'headers'  => ['Api-Key' => $_ENV['PINECONE_API_KEY']],
]);

$response = $http->post('/query', ['json' => [
    'vector'          => $myVector,
    'topK'            => 5,
    'includeMetadata' => true,
    'namespace'       => 'docs',
]]);

$body = json_decode($response->getBody(), true);
foreach ($body['matches'] as $match) {
    echo $match['metadata']['text'] . "\n";
}
```

### After

```php
use ParaGra\VectorStore\PineconeVectorStore;
use ParaGra\VectorStore\VectorNamespace;

$store = new PineconeVectorStore(
    baseUrl:   'https://my-index.pinecone.io',
    apiKey:    $_ENV['PINECONE_API_KEY'],
    indexName: 'my-index',
);

$result = $store->query(
    namespace: new VectorNamespace('docs'),
    vector:    $myVector,
    options:   ['top_k' => 5],
);

foreach ($result->getChunks() as $chunk) {
    echo $chunk['text'] . "\n";
}
```

The `PineconeVectorStore` (and all other `VectorStoreInterface` implementations) normalise provider-specific response formats into the same `UnifiedResponse` shape.

---

## Migrating multiple environments (free → paid fallback)

A common pattern is free-tier keys for development and a paid fallback for production load.

```php
return [
    'priority_pools' => [
        // Free keys — exhaust all before moving to paid
        [
            [
                'provider' => 'cerebras',
                'model'    => 'llama-3.3-70b',
                'api_key'  => $_ENV['CEREBRAS_KEY_1'],
                'solution' => ['type' => 'openai_compat', 'metadata' => ['plan' => 'free']],
            ],
            [
                'provider' => 'cerebras',
                'model'    => 'llama-3.3-70b',
                'api_key'  => $_ENV['CEREBRAS_KEY_2'],
                'solution' => ['type' => 'openai_compat', 'metadata' => ['plan' => 'free']],
            ],
        ],
        // Paid fallback — single attempt (hosted SLA)
        [
            [
                'provider' => 'openai',
                'model'    => 'gpt-4o-mini',
                'api_key'  => $_ENV['OPENAI_API_KEY'],
                'solution' => ['type' => 'openai', 'metadata' => ['plan' => 'hosted']],
            ],
        ],
    ],
];
```

---

## Checklist

- [ ] Replace direct SDK client construction with `ParaGra::fromConfig()`.
- [ ] Move API keys into the `priority_pools` config array (from `.env` or secrets manager).
- [ ] Replace `retrieve()`-style calls with `$paragra->retrieve()` or `$paragra->answer()`.
- [ ] Map provider-specific response fields to `UnifiedResponse` accessors (`getChunks()`, `getChunkTexts()`, `getProviderMetadata()`).
- [ ] Add a second tier for fallback so no single-provider outage breaks your app.
- [ ] Add `withModeration()` if the app previously called a moderation API separately.
