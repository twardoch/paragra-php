---
this_file: paragra-php/docs/architecture.md
---

# ParaGra Architecture Primer

The ParaGra toolkit in `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php` keeps orchestration logic outside of app code so Ragie + Gemini + AskYoda stacks share the same runtime guarantees.

```
[User Query]
     |
     v
ParaGra::retrieve()/::answer()
     |
     v
PriorityPool -> KeyRotator -> ProviderFactory
     |
     v
ProviderInterface (Ragie / Gemini / AskYoda)
     |
     v
UnifiedResponse + Optional NeuronAiAdapter completion
```

## Priority pools

- `ParaGra\Config\PriorityPool` parses the nested config array and exposes an ordered list of `ProviderSpec` objects.
- Each **priority pool** represents a pricing/latency tier. Pool `0` typically contains free-tier keys; higher indices are progressively more expensive or more reliable.
- Provider metadata is preserved so responses can expose tier + model info downstream.

## Deterministic rotation

- `ParaGra\Router\KeyRotator` accepts the pool definition plus an optional clock.
- Rotation uses `(timestamp >> 1) + index` to keep even distribution, meaning two keys in the same pool should each receive ~50% of requests over time.
- Tests live in `tests/Router/KeyRotatorTest.php` demonstrating evenness across 2/3/5-key pools.

## Fallback execution

- `ParaGra\Router\FallbackStrategy` takes a `PriorityPool` and `KeyRotator`, then walks each provider in the current pool through a closure you supply.
- Pools inherit attempt budgets from metadata: free-tier pools rotate through every key before falling back, hybrid pools default to two attempts, and hosted pools take a single shot (all of which can be overridden via policy overrides).
- Each failure logs a hashed key fingerprint plus provider/model so you can audit rotation decisions; once a pool exhausts its budget, the next pool is attempted. If all pools fail, the final exception is re-thrown with prior errors attached in `metadata['fallback_errors']`.

## Retrieval pipeline

1. ParaGra sanitizes the question and optionally runs `ModeratorInterface::moderate()`.
2. FallbackStrategy calls the configured provider's `retrieve()` implementation:
   - `RagieProvider` → uses `Ragie\Client` to fetch `RetrievalResult`.
   - `GeminiFileSearchProvider` → issues HTTP requests against Google AI Platform.
   - `AskYodaProvider` → hits EdenAI AskYoda endpoints.
3. Each provider returns a `UnifiedResponse` object so chunk metadata stays consistent.

## Answer pipeline

When you call `ParaGra::answer()`:

1. Retrieval runs as above and yields a `UnifiedResponse`.
2. `PromptBuilder` builds a prompt from the clean question + chunk texts.
3. `ProviderFactory::createLlmClient()` hands back a `NeuronAiAdapter`, which wraps `neuron-core/neuron-ai` to talk to the LLM provider indicated by the current `ProviderSpec`.
4. The final payload contains `answer`, `prompt`, `context`, and combined metadata.

## Moderation

- The optional `withModeration(OpenAiModerator::fromEnv())` call enforces OpenAI moderation before retrieval.
- You can implement your own `ModeratorInterface` if you need a different policy.

## Data flow in ask.vexy.art

1. `/public/rag/index.php` loads `private/config/paragra.php`.
2. ParaGra handles provider selection and returns normalized metadata.
3. The endpoint simply serializes `UnifiedResponse` + `answer`, so no provider-specific logic remains in the minisite.

Use this doc alongside `docs/configuration.md` to reason about where to add new providers or rotation strategies without touching application code.
