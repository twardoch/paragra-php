---
this_file: paragra-php/docs/examples.md
---

# Configuration & Usage Examples

All examples live inside `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php/examples`. Copy whichever file matches your deployment and customize the environment variables.

| Scenario | File |
| --- | --- |
| Ragie retrieval + Cerebras rotation | `examples/config/ragie_cerebras.php` |
| Ragie retrieval + OpenAI fallback | `examples/config/ragie_openai.php` |
| Gemini File Search fallback | `examples/config/gemini_file_search.php` |
| EdenAI AskYoda fallback | `examples/config/askyoda.php` |
| Moderated answer flow | `examples/moderated_answer.php` |
| Ragie + twat-search fallback | `examples/external-search/twat_search_fallback.php` |
| Hybrid Ragie + vector store pipeline | `examples/vector-stores/hybrid_pipeline.php` |
| Answer + Chutes illustration | `examples/media/chutes_answer_with_image.php` |
| Answer + Fal.ai illustration | `examples/media/fal_answer_with_image.php` |

## Ragie + Cerebras rotation

```php
return [
    'priority_pools' => [
        [
            [
                'provider' => 'cerebras',
                'model' => 'llama-3.3-70b',
                'api_key' => getenv('CEREBRAS_API_KEY_1'),
                'solution' => [
                    'type' => 'ragie',
                    'ragie_api_key' => getenv('RAGIE_API_KEY'),
                    'default_options' => ['top_k' => 6, 'rerank' => true],
                    'metadata' => ['tier' => 'free'],
                ],
            ],
            [
                'provider' => 'cerebras',
                'model' => 'llama-3.3-70b',
                'api_key' => getenv('CEREBRAS_API_KEY_2'),
                'solution' => [
                    'type' => 'ragie',
                    'ragie_api_key' => getenv('RAGIE_API_KEY'),
                    'metadata' => ['tier' => 'free'],
                ],
            ],
        ],
    ],
];
```

Pool `0` receives all requests. `KeyRotator` alternates between `_1` and `_2` keys, keeping the free tier hot while logs still show which key took each request.

## Ragie + OpenAI fallback

```php
return [
    'priority_pools' => [
        [
            [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => getenv('OPENAI_API_KEY'),
                'solution' => [
                    'type' => 'ragie',
                    'ragie_api_key' => getenv('RAGIE_API_KEY'),
                    'default_options' => ['top_k' => 8, 'rerank' => true],
                    'metadata' => ['tier' => 'paid', 'notes' => 'High reliability'],
                ],
            ],
        ],
    ],
];
```

Use this as pool `1` after the Cerebras pool when you want a fast failover path that still reuses Ragie's corpus.

## Gemini File Search fallback

```php
return [
    'priority_pools' => [
        [
            [
                'provider' => 'gemini',
                'model' => 'gemini-2.0-flash-exp',
                'api_key' => getenv('GOOGLE_API_KEY'),
                'solution' => [
                    'type' => 'gemini-file-search',
                    'vector_store' => [
                        'datastore' => getenv('GEMINI_DATASTORE_ID') ?: getenv('GEMINI_CORPUS_ID'),
                    ],
                    'generation' => ['temperature' => 0.4],
                    'metadata' => ['tier' => 'paid'],
                ],
            ],
        ],
    ],
];
```

This example shows how to bypass Ragie entirely when you want to leverage Google-native corpora as the fallback tier.

## AskYoda fallback

```php
return [
    'priority_pools' => [
        [
            [
                'provider' => 'edenai',
                'model' => 'askyoda',
                'api_key' => getenv('EDENAI_API_KEY'),
                'solution' => [
                    'type' => 'askyoda',
                    'askyoda_api_key' => getenv('EDENAI_API_KEY'),
                    'project_id' => getenv('EDENAI_ASKYODA_PROJECT'),
                    'default_options' => ['k' => 10, 'min_score' => 0.35],
                    'llm' => ['provider' => 'google', 'model' => 'gemini-1.5-flash'],
                    'metadata' => ['tier' => 'paid'],
                ],
            ],
        ],
    ],
];
```

Pair this pool with Ragie pools so the system automatically switches to AskYoda when Ragie returns HTTP 429 or when you explicitly move traffic there.

## Moderated answer flow

Run the example script to see moderation hooked into ParaGra:

```bash
cd paragra-php
php examples/moderated_answer.php "Summarize the ParaGra roadmap"
```

The script:

- Loads the Ragie + Cerebras config.
- Builds `ParaGra` + `OpenAiModerator`.
- Prints the answer plus provider metadata as JSON.

Use it to confirm moderation flags work before wiring the same pattern into `ask.vexy.art/public/rag/index.php`.

## Hybrid Ragie + vector store pipeline

```
HYBRID_STORE=pinecone \\
PINECONE_BASE_URL=https://example.svc.us-west1-aws.pinecone.io \\
PINECONE_API_KEY=pcn-xxxx \\
PINECONE_INDEX=ragie-demo \\
php examples/vector-stores/hybrid_pipeline.php "Summarize ParaGra" examples/config/ragie_cerebras.php
```

The `examples/vector-stores/hybrid_pipeline.php` script orchestrates `HybridRetrievalPipeline`: Ragie retrieval supplies keyword/KB context, OpenAI embeddings feed a vector store, and both signals are reranked into a single payload. Set `HYBRID_STORE` to `pinecone`, `qdrant`, `weaviate`, `chroma`, or `gemini-file-search` and provide the corresponding environment variables:

- **Pinecone:** `PINECONE_BASE_URL`, `PINECONE_API_KEY`, `PINECONE_INDEX`, optional `PINECONE_NAMESPACE`.
- **Qdrant:** `QDRANT_URL`, `QDRANT_COLLECTION`, optional `QDRANT_API_KEY`.
- **Weaviate:** `WEAVIATE_URL`, `WEAVIATE_CLASS`, optional `WEAVIATE_API_KEY`, `WEAVIATE_TENANT`, `WEAVIATE_CONSISTENCY`.
- **Chroma:** `CHROMA_URL`, `CHROMA_TENANT`, `CHROMA_DATABASE`, `CHROMA_COLLECTION`, optional `CHROMA_TOKEN`.
- **Gemini File Search:** `GOOGLE_API_KEY`, `GEMINI_DATASTORE_ID` (preferred) or `GEMINI_CORPUS_ID`, optional `GEMINI_NAMESPACE`.

Flags:

- `HYBRID_SEED_FIRST=1` — call `ingestFromRagie()` before querying, useful when seeding new namespaces.
- `HYBRID_RETRIEVAL_TOPK`, `HYBRID_VECTOR_TOPK`, `HYBRID_LIMIT` — override Ragie topK, vector-store topK, and combined chunk limits (defaults: 6/6/8).

The script prints a JSON payload containing the question, the selected store, and reranked chunks with provenance metadata so you can feed them into ParaGra’s prompt builder or other pipelines.

## Ragie + twat-search fallback

Install the [twat-search](https://github.com/twardoch/twat-search) CLI once:

```bash
pip install "twat-search[all]"
```

Then run the example to see Ragie retrieval augmented with multi-engine web search snippets:

```bash
TWAT_SEARCH_ENGINES="brave,duckduckgo" \
TAVILY_API_KEY=tvly-key \
BRAVE_API_KEY=brv-key \
php examples/external-search/twat_search_fallback.php "Latest ParaGra roadmap" examples/config/ragie_cerebras.php
```

The script:

- Retrieves chunks with ParaGra (Ragie provider) and prints them verbatim.
- Executes `twat-search web q --json ...` via `TwatSearchRetriever` to source attributed snippets (url/title/snippet/engine). Engines default to Brave + DuckDuckGo, but you can set `TWAT_SEARCH_ENGINES`, `TWAT_SEARCH_NUM_RESULTS`, and `TWAT_SEARCH_MAX_RESULTS` to tune payload size.
- Accepts API keys via `TAVILY_API_KEY`, `BRAVE_API_KEY`, `YOU_API_KEY`, or `SERPAPI_API_KEY` so paid engines activate when available.
- Emits JSON summarizing Ragie vs external chunks plus metadata (`cache_hit`, `duration_ms`, engine list) for debugging fallback decisions.

Use this as a blueprint for ParaGra deployments where an external search pass should run when Ragie (or any catalog provider) returns no context. The retriever caches results in-memory for `TWAT_SEARCH_CACHE_TTL` seconds so repeated questions hit the CLI only once per TTL window.

## Answer + image generation (Chutes / Fal.ai)

Need a quick hero shot after generating an answer? The media scripts summarize the Ragie answer into an art direction prompt, then call either Chutes or Fal.ai using the new `MediaRequest` + provider adapters.

### Chutes cinematic still

```bash
CHUTES_API_KEY=chutes_live_xxx \
CHUTES_BASE_URL=https://brandstudio-yourchute.chutes.ai \
CHUTES_MODEL=flux.1-pro \
php examples/media/chutes_answer_with_image.php \
  "Stage a futuristic ParaGra help desk" \
  examples/config/ragie_cerebras.php
```

| Env var | Purpose |
| --- | --- |
| `CHUTES_API_KEY` | Required Bearer token for your chute deployment. |
| `CHUTES_BASE_URL` | Base URL for the chute (defaults to `https://myuser-my-image-gen.chutes.ai`). |
| `CHUTES_MODEL` | Optional model slug (Flux, SDXL, LoRA, etc.). |
| `CHUTES_GUIDANCE` | Float guidance scale override (default 7.2). |
| `CHUTES_STEPS` | `num_inference_steps` override (default 28). |
| `CHUTES_ASPECT_RATIO` | `W:H` string (e.g., `16:9`, `9:16`). |
| `CHUTES_IMAGES` | Number of frames to request (capped at 4). |

The script prints the answer, image artifact metadata (URL/base64/mime), and the provider metadata block so ask.vexy.art can embed the same payload when image mode is enabled.

### Fal.ai async image job

```bash
FAL_KEY=fal_test_xxx \
FAL_MODEL="fal-ai/flux/dev" \
php examples/media/fal_answer_with_image.php \
  "Illustrate the Ragie + ParaGra ecosystem" \
  examples/config/ragie_cerebras.php
```

| Env var | Purpose |
| --- | --- |
| `FAL_KEY` | Required API key from https://fal.ai. |
| `FAL_MODEL` | Model endpoint (Flux Dev, Fooocus, GPT-Image-1, etc.). |
| `FAL_IMAGES` | Parallel image count (default 1). |
| `FAL_GUIDANCE` | Guidance scale override. |
| `FAL_STEPS` | Optional inference steps passed through to the Fal payload. |

`FalImageProvider` handles asynchronous polling and timeouts, so the script simply emits the CDN URLs/base64 payloads once Fal marks the job `COMPLETED`. Swap either script into your endpoints when you want optional image enrichments without bloating the main Ragie flow.
