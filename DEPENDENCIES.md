---
this_file: paragra-php/DEPENDENCIES.md
---

# ParaGra Dependencies

| Package | Why we need it | Notes |
| --- | --- | --- |
| `ragie/ragie-php` | Primary Ragie client SDK; ParaGra wraps it to serve Ragie-backed pools. | Pulled via local path repository; tracks `dev-main` until v0.4.0 is tagged. |
| `neuron-core/neuron-ai` | Battle-tested (>1K ⭐) agentic/LLM abstraction for PHP that already handles provider selection, streaming, and adapters. | Avoids building LLM plumbing from scratch; extends our scope beyond OpenAI. |
| `google/cloud-ai-platform` | Official Google Cloud PHP client that surfaces Gemini File Search and Vertex AI models. | Maintained by Google; part of the google-cloud-php suite (~2k ⭐). |
| `google-gemini-php/client` | 344⭐ community SDK for Gemini's v1beta API, used by `GeminiEmbeddingProvider` to hit `text-embedding-004`/`embedding-001` without hand-rolled HTTP plumbing. | Works with any PSR-18 client (Guzzle already present) and exposes batch embedding helpers we can fake in tests. |
| `guzzlehttp/guzzle` | Lightweight HTTP client for AskYoda/Gemini REST adapters and future providers. | Matches ragie-php requirements; keeps HTTP plumbing consistent. |
| `openai-php/client` | Official OpenAI PHP SDK powering `OpenAiChatClient` and `OpenAiModerator`. | Shared with ragie-php for compatibility; now declared explicitly. |
| `symfony/process` | Battle-tested process runner for launching the `twat-search` CLI with timeouts, retries, and captured output. | Standard Symfony component (>10k ⭐) so we avoid writing custom `proc_open()` plumbing. |
| `friendsofphp/php-cs-fixer` | Formatting/linting to keep contributions consistent. | Dev dependency. |
| `phpstan/phpstan` | Static analysis (level 7 baseline). | Dev dependency. |
| `phpunit/phpunit` | Primary test runner (11.x). | Dev dependency. |
| `vimeo/psalm` | Deeper static analysis to catch flow/typing issues (err level 3). | Dev dependency. |
| `dg/bypass-finals` | Enables PHPUnit to mock final classes (OpenAiChatClient, RagAnswerer dependencies) without removing `final`. | Loaded via `tests/bootstrap.php` before running the suite. |
