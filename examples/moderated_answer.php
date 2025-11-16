#!/usr/bin/env php
<?php

declare(strict_types=1);

// this_file: paragra-php/examples/moderated_answer.php

use ParaGra\Moderation\OpenAiModerator;
use ParaGra\ParaGra;

require __DIR__ . '/../vendor/autoload.php';

$question = $argv[1] ?? 'What does ParaGra do?';
$config = require __DIR__ . '/config/ragie_cerebras.php';

$paragra = ParaGra::fromConfig($config)
    ->withModeration(OpenAiModerator::fromEnv());

$response = $paragra->answer($question);

fwrite(STDOUT, json_encode([
    'question' => $question,
    'answer' => $response['answer'],
    'provider' => $response['metadata']['provider'] ?? null,
    'model' => $response['metadata']['model'] ?? null,
    'tier' => $response['metadata']['tier'] ?? null,
], JSON_PRETTY_PRINT) . PHP_EOL);
