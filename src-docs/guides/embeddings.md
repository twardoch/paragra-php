# Embedding Providers

ParaGra provides unified interfaces for generating vector embeddings from multiple providers.

## Supported providers

- **OpenAI**: text-embedding-3-small/large (1536/3072 dims)
- **Cohere**: embed-english-v3.0 (1024 dims)
- **Gemini**: text-embedding-004 (768 dims, configurable)
- **Voyage**: voyage-3/voyage-3-large (1024/2048 dims)

## Common interface

All providers implement `EmbeddingProviderInterface`:

```php
interface EmbeddingProviderInterface
{
    public function embed(EmbeddingRequest $request): array;
}
```

## Basic usage

```php
use ParaGra\Embedding\EmbeddingRequest;
use ParaGra\Embedding\OpenAiEmbeddingConfig;
use ParaGra\Embedding\OpenAiEmbeddingProvider;

$config = OpenAiEmbeddingConfig::fromEnv();
$provider = new OpenAiEmbeddingProvider($config);

$request = new EmbeddingRequest(
    inputs: [
        ['id' => 'doc-1', 'text' => 'First document', 'metadata' => ['source' => 'kb']],
        'Plain string works too',
    ],
    normalize: true,
);

$result = $provider->embed($request);
// $result['vectors'] contains normalized embeddings
```

For detailed provider documentation, see the [README](../../README.md#embedding-providers).
