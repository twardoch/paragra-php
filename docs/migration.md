---
this_file: paragra-php/docs/migration.md
---

# Migrating from ragie-php Helpers to ParaGra

Both projects live inside `/Users/adam/Developer/vcs/github.twardoch/pub/rag-projects`, but ParaGra now owns the orchestration logic. Follow the steps below to move existing Ragie-based stacks over.

## 1. Update Composer requirements

```bash
cd ask.vexy.art/private
composer req vexy/paragra-php:@dev
```

The private Composer path already points to `../../paragra-php`, so `composer update` will symlink (or copy) the local sources into `vendor-local/`. Keep the release build inside `vendor/` for production deployments.

## 2. Replace legacy helpers

| Legacy ragie-php helper | ParaGra replacement |
| --- | --- |
| `Ragie\Assistant\RagAnswerer::fromEnv()` | `ParaGra\ParaGra::fromConfig(require 'config/paragra.php')` |
| `Ragie\Llm\OpenAiChatClient` usage | `ParaGra\Llm\NeuronAiAdapter` (resolved automatically per provider) |
| `Ragie\Moderation\OpenAiModerator` | `ParaGra\Moderation\OpenAiModerator` (same public API) |
| AskYoda fallback glue | `ParaGra\Providers\AskYodaProvider` + pool ordering |

Remove direct calls to Ragie's `Client::retrieve()` where business logic no longer needs to differentiate providers. ParaGra hands back a `UnifiedResponse` with normalized chunks and metadata.

## 3. Move configuration into `config/paragra.php`

Instead of wiring API keys across multiple entry points, centralize them:

```php
// ask.vexy.art/private/config/paragra.php
return [
    'priority_pools' => require __DIR__ . '/../../paragra-php/examples/config/ragie_cerebras.php',
];
```

Each endpoint can now do:

```php
use ParaGra\ParaGra;
use ParaGra\Moderation\OpenAiModerator;

$config = require __DIR__ . '/../config/paragra.php';
$paragra = (ParaGra::fromConfig($config))
    ->withModeration(OpenAiModerator::fromEnv());

$response = $paragra->answer($_GET['text'] ?? '');
```

## 4. Validate behavior

1. Run `php ask.vexy.art/tests/paragra_config_test.php` to ensure config + Composer wiring work.
2. Hit `/public/rag/?text=hello&debug=1` and `/public/text/?text=hello&debug=1` to confirm metadata now lists `provider`, `model`, and `tier`.
3. Force failures by temporarily revoking the free-tier key; ParaGra should automatically step into the next pool.

## 5. Decommission bespoke glue

Once ParaGra owns rotation, fallback, moderation, and AskYoda integration, you can delete the redundant Ragie helper code paths inside your endpoints. This keeps `ragie-php` focused on being the retrieval SDK while ParaGra evolves as the multi-provider orchestrator.
