# Moderation

ParaGra supports optional content moderation before executing retrieval or generation.

## Overview

`OpenAiModerator` uses OpenAI's Moderation API to flag unsafe content.

## Basic usage

```php
use ParaGra\Moderation\OpenAiModerator;
use ParaGra\ParaGra;

$config = require __DIR__ . '/config/paragra.php';
$paragra = ParaGra::fromConfig($config)
    ->withModeration(OpenAiModerator::fromEnv());

try {
    $answer = $paragra->answer('Tell me about AI safety');
    echo $answer['answer'];
} catch (\ParaGra\Moderation\ModerationException $e) {
    echo "Content flagged: {$e->getMessage()}\n";
    // Access details: $e->getResult()
}
```

## Environment configuration

```bash
export OPENAI_API_KEY=sk-...
```

`OpenAiModerator::fromEnv()` reads `OPENAI_API_KEY` automatically.

## Manual configuration

```php
use ParaGra\Moderation\OpenAiModerator;

$moderator = new OpenAiModerator('sk-...');
$paragra->withModeration($moderator);
```

## Moderation result

When content is flagged, `ModerationException` includes:

```php
$exception->getResult(); // ModerationResult object
$result->isFlagged();    // bool
$result->getCategories(); // ['hate', 'violence', ...]
$result->getScores();    // ['hate' => 0.95, ...]
```

## Example script

```bash
export RAGIE_API_KEY=sk_live_...
export OPENAI_API_KEY=sk-...
export CEREBRAS_API_KEY_1=...

php examples/moderated_answer.php "What is ParaGra?"
```

See `examples/moderated_answer.php` for full implementation.
