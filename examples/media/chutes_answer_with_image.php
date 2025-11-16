#!/usr/bin/env php
<?php

declare(strict_types=1);

// this_file: paragra-php/examples/media/chutes_answer_with_image.php

use GuzzleHttp\Client;
use ParaGra\Media\ChutesImageProvider;
use ParaGra\Media\MediaRequest;
use ParaGra\ParaGra;

use function array_filter;
use function getenv;
use function is_file;
use function json_encode;
use function max;
use function sprintf;
use function trim;

require __DIR__ . '/../../vendor/autoload.php';

$question = $argv[1] ?? 'Design a welcoming lobby for the Ragie + ParaGra SDK';
$configFile = $argv[2] ?? __DIR__ . '/../config/ragie_cerebras.php';

if (!is_file($configFile)) {
    fwrite(STDERR, sprintf("Config file not found: %s\n", $configFile));
    exit(1);
}

$apiKey = trim((string) getenv('CHUTES_API_KEY'));
if ($apiKey === '') {
    fwrite(STDERR, "CHUTES_API_KEY must be set to use this example.\n");
    exit(1);
}

$baseUrl = trim((string) (getenv('CHUTES_BASE_URL') ?: 'https://myuser-my-image-gen.chutes.ai'));
$model = trim((string) (getenv('CHUTES_MODEL') ?: 'flux.1-pro'));
$guidance = (float) (getenv('CHUTES_GUIDANCE') ?: 7.2);
$steps = max(10, (int) (getenv('CHUTES_STEPS') ?: 28));

$paragra = ParaGra::fromConfig(require $configFile);
$answer = $paragra->answer($question);

$summaryPrompt = sprintf(
    'Illustrate the following Ragie answer with a cinematic still: %s',
    $answer['answer']
);

$request = new MediaRequest(
    prompt: $summaryPrompt,
    negativePrompt: 'blurry, watermark, signature, text',
    aspectRatio: getenv('CHUTES_ASPECT_RATIO') ?: '16:9',
    images: max(1, (int) (getenv('CHUTES_IMAGES') ?: 1)),
    metadata: ['script' => 'chutes_answer_with_image']
);

$provider = new ChutesImageProvider(
    http: new Client(['timeout' => 60]),
    baseUrl: $baseUrl,
    apiKey: $apiKey,
    defaults: array_filter([
        'model' => $model,
        'guidance_scale' => $guidance,
    ])
);

$result = $provider->generate($request, [
    'payload' => ['num_inference_steps' => $steps],
]);

fwrite(STDOUT, json_encode([
    'question' => $question,
    'answer' => $answer['answer'],
    'image' => $result->getArtifacts()[0],
    'metadata' => $result->getMetadata(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
