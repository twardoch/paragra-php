#!/usr/bin/env php
<?php

declare(strict_types=1);

// this_file: paragra-php/examples/media/fal_answer_with_image.php

use GuzzleHttp\Client;
use ParaGra\Media\FalImageProvider;
use ParaGra\Media\MediaRequest;
use ParaGra\ParaGra;

use function getenv;
use function is_file;
use function json_encode;
use function max;
use function sprintf;
use function trim;

require __DIR__ . '/../../vendor/autoload.php';

$question = $argv[1] ?? 'Show a para-graph describing Ragie knowledge orchestration';
$configFile = $argv[2] ?? __DIR__ . '/../config/ragie_cerebras.php';

if (!is_file($configFile)) {
    fwrite(STDERR, sprintf("Config file not found: %s\n", $configFile));
    exit(1);
}

$falKey = trim((string) getenv('FAL_KEY'));
if ($falKey === '') {
    fwrite(STDERR, "FAL_KEY must be configured for this script.\n");
    exit(1);
}

$modelId = trim((string) (getenv('FAL_MODEL') ?: 'fal-ai/flux/dev'));
$images = max(1, (int) (getenv('FAL_IMAGES') ?: 1));
$guidance = (float) (getenv('FAL_GUIDANCE') ?: 7.5);

$paragra = ParaGra::fromConfig(require $configFile);
$answer = $paragra->answer($question);

$prompt = sprintf(
    'Generate an editorial illustration for this answer: %s',
    $answer['answer']
);

$request = new MediaRequest(
    prompt: $prompt,
    negativePrompt: 'watermark, text, blurry',
    images: $images,
    metadata: ['script' => 'fal_answer_with_image']
);

$provider = new FalImageProvider(
    http: new Client(['timeout' => 60]),
    apiKey: $falKey,
    modelId: $modelId,
    defaults: [
        'guidance_scale' => $guidance,
        'poll_interval_ms' => 250,
    ]
);

$result = $provider->generate($request, [
    'payload' => [
        'num_images' => $images,
        'num_inference_steps' => (int) (getenv('FAL_STEPS') ?: 25),
    ],
]);

fwrite(STDOUT, json_encode([
    'question' => $question,
    'answer' => $answer['answer'],
    'images' => $result->getArtifacts(),
    'metadata' => $result->getMetadata(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
