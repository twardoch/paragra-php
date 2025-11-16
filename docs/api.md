---
this_file: paragra-php/docs/api.md
---

# ParaGra API Reference

This summary covers the primary entry points exposed by `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects/paragra-php`.

## ParaGra\ParaGra

| Method | Description |
| --- | --- |
| `__construct(PriorityPool $pools, ProviderFactory $factory, ?FallbackStrategy $fallback = null)` | Usually created via `fromConfig()`. Accepts injected factories for testing. |
| `static fromConfig(array $config): self` | Parses the `priority_pools` structure and builds default factories. |
| `withModeration(ModeratorInterface $moderator): self` | Enables moderation before every `retrieve()`/`answer()` call. Returns `$this` for chaining. |
| `retrieve(string $query, array $options = []): UnifiedResponse` | Sanitizes input, enforces moderation, runs fallback strategy, and returns normalized chunks. |
| `answer(string $question, array $options = []): array` | Runs retrieval, builds the prompt, calls `NeuronAiAdapter`, and returns `['answer','prompt','context','metadata']`. |

## Config layer

- `ParaGra\Config\PriorityPool::fromArray(array $pools): self` — Validates the nested pool definition.
- `ParaGra\Config\ProviderSpec::fromArray(array $spec): self` — Validates provider name, model, API key, and solution metadata.

## ProviderFactory & adapters

- `ProviderFactory::createProvider(ProviderSpec $spec): ProviderInterface`
  - Returns one of:
    - `RagieProvider` — Wraps `ragie/ragie-php` retrieval.
    - `GeminiFileSearchProvider` — Calls Google AI Platform File Search.
    - `AskYodaProvider` — Hits EdenAI AskYoda endpoints.
- `ProviderFactory::createLlmClient(ProviderSpec $spec): NeuronAiAdapter`
  - Builds a chat client for OpenAI, Gemini, Anthropic, Groq, etc., based on `provider` + `model`.

## Router utilities

- `Router\KeyRotator::currentProvider(PriorityPool $pool): ProviderSpec` — Deterministic rotation among providers in a single pool.
- `Router\FallbackStrategy::execute(Closure $handler): mixed` — Invokes `$handler(ProviderSpec)` for each provider until one succeeds, rotating through pool members according to their metadata-derived family policy (free = exhaust all keys, hybrid = two attempts, hosted = single shot) before escalating to the next pool.

## Response objects

- `Response\UnifiedResponse`
  - `getChunks(): array` — Normalized chunk payloads.
  - `getChunkTexts(): array` — Memoized list of chunk texts (used by PromptBuilder).
  - `getProviderMetadata(): array` — Provider + tier metadata (used by endpoints).
  - `getCost()/getUsage()` — Optional runtime reporting.

## Assistant utilities

- `Assistant\RagAnswerer::answer()` — Mirrors the historical Ragie helper but now lives in ParaGra so it can reuse rotation, moderation, and AskYoda fallback.
- `Assistant\AskYodaHostedAdapter::ask()` — Wraps `Llm\AskYodaClient`, records duration/chunk telemetry, and feeds the hosted fallback data back into `RagAnswerer`.
- `Assistant\AskYodaHostedResult` — Convenience DTO returned by the adapter (`getResponse()`, `getDurationMs()`, `getChunkCount()`), making it easy to log fallback telemetry.

## LLM helpers

- `Llm\PromptBuilder::build(string $question, array $chunkTexts): string` — Minimal prompt builder used by `answer()`.
- `Llm\NeuronAiAdapter::generate(string $prompt, array $options = []): string` — Thin wrapper around `neuron-core/neuron-ai` chat completions.
- `Llm\OpenAiChatConfig::fromEnv()` / `Llm\AskYodaClient::fromEnv()` remain available for low-level use but ParaGra defaults to provider specs.

## Moderation

- `Moderation\OpenAiModerator::fromEnv()` — Instantiates the moderation client using `OPENAI_API_KEY`.
- `Moderation\ModeratorInterface::moderate(string $text): ModerationResult` — Implement to add alternative moderation suppliers.
- `Moderation\NullModerator` — Pass-through helper for tests or trusted deployments.

## Exceptions

- `Exception\ConfigurationException` — Thrown when required provider metadata is missing.
- Standard `InvalidArgumentException` is raised when config arrays are malformed or question strings are empty.

Refer to `tests/` for concrete usage of each class; every function in the table is covered by PHPUnit to serve as living documentation.
