---
this_file: paragra-php/src-docs/reference/answer-method.md
---

# `answer()` Parameter Reference

`ParaGra::answer()` combines retrieval and LLM generation into a single call.

## Signature

```php
public function answer(string $question, array $options = []): array
```

## Parameters

### `$question` (string, required)

The user question to answer. Must be non-empty after trimming whitespace.

Throws `\InvalidArgumentException` if empty.

If a moderator is attached (via `withModeration()`), it runs against the trimmed question before any provider is contacted. A moderation rejection throws whatever exception the moderator is configured to throw.

---

### `$options` (array, optional)

An associative array with up to two optional sub-arrays:

#### `retrieval` (array)

Options forwarded verbatim to the retrieval provider's `retrieve()` method.

| Key | Type | Default | Description |
|---|---|---|---|
| `top_k` | int | provider default | Maximum number of passages to retrieve |
| `filter` | array | `[]` | Provider-specific metadata filter (format varies by adapter) |
| `include_vectors` | bool | `false` | Return raw embedding vectors alongside passages (VectorStore adapters only) |
| `namespace` | string | adapter default | Override the target vector namespace / collection |

Example:

```php
'retrieval' => [
    'top_k'  => 5,
    'filter' => ['lang' => 'en', 'source' => 'kb'],
],
```

#### `generation` (array)

Options forwarded verbatim to the LLM adapter's `generate()` method.

| Key | Type | Default | Description |
|---|---|---|---|
| `temperature` | float | model default | Sampling temperature (0.0 = deterministic, 1.0+ = creative) |
| `max_tokens` | int | model default | Maximum tokens in the generated answer |
| `top_p` | float | model default | Nucleus sampling probability mass |
| `stop` | string\|list\<string\> | none | One or more stop sequences |
| `system` | string | none | System prompt override (replaces the default template preamble) |

Example:

```php
'generation' => [
    'temperature' => 0.2,
    'max_tokens'  => 512,
],
```

## Return value

An associative array with four keys:

```php
[
    'answer'   => string,           // Raw text from the LLM
    'prompt'   => string,           // Full prompt sent to the LLM (for debugging)
    'context'  => UnifiedResponse,  // Retrieved passages (chunks, scores, metadata)
    'metadata' => array,            // Provider info + any extras from the retrieval response
]
```

### `answer` (string)

The raw text string returned by the LLM. No post-processing is applied; callers are responsible for rendering markdown, escaping HTML, etc.

### `prompt` (string)

The full text sent to the LLM, constructed by `PromptBuilder`. Useful for debugging prompt quality, token counts, and unexpected context injection.

### `context` (UnifiedResponse)

The `UnifiedResponse` from the retrieval step. Key methods:

| Method | Return type | Description |
|---|---|---|
| `getChunks()` | `list<array>` | Raw chunk records (text, score, document_id, metadata) |
| `getChunkTexts()` | `list<string>` | Plain-text bodies of each chunk (passed to `PromptBuilder`) |
| `getProvider()` | string | Provider slug that served this response |
| `getModel()` | string | Model / index identifier |
| `getProviderMetadata()` | array | Additional provider-specific fields |

### `metadata` (array)

Always contains:

```php
[
    'provider' => 'ragie',         // Slug of the provider that answered
    'model'    => 'default',       // Model / index used
    // + any extra keys from UnifiedResponse::getProviderMetadata()
]
```

## Full example

```php
use ParaGra\ParaGra;

$paragra = ParaGra::fromConfig(require 'config/paragra.php');

$result = $paragra->answer('What is the refund policy?', [
    'retrieval'  => ['top_k' => 5, 'filter' => ['namespace' => 'support']],
    'generation' => ['temperature' => 0.1, 'max_tokens' => 256],
]);

echo $result['answer'];
// → "Refunds are available within 30 days of purchase..."

// Inspect the retrieved passages
foreach ($result['context']->getChunks() as $chunk) {
    printf("[%.2f] %s\n", $chunk['score'], substr($chunk['text'], 0, 80));
}

// Check which provider handled the request
echo $result['metadata']['provider'];   // e.g. "ragie"
```

## Error handling

| Exception | When |
|---|---|
| `\InvalidArgumentException` | `$question` is empty, or a sub-option value is not an array |
| `\RuntimeException` | All priority pools exhausted; wraps the last provider exception |
| Provider-specific exceptions | Propagated if not caught by the fallback (e.g. auth errors on all tiers) |

Wrap in a try/catch when all-tier failures must be handled gracefully:

```php
try {
    $result = $paragra->answer($question);
} catch (\RuntimeException $e) {
    // All providers failed; degrade gracefully
    error_log('ParaGra answer failed: ' . $e->getMessage());
    return ['answer' => 'Sorry, no answer is available right now.'];
}
```
