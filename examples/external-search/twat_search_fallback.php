#!/usr/bin/env php
<?php

declare(strict_types=1);

// this_file: paragra-php/examples/external-search/twat_search_fallback.php

use ParaGra\ExternalSearch\TwatSearchRetriever;
use ParaGra\ParaGra;
use ParaGra\Response\UnifiedResponse;

use function array_filter;
use function explode;
use function getenv;
use function is_file;
use function json_encode;
use function max;
use function sprintf;
use function trim;

require __DIR__ . '/../../vendor/autoload.php';

$question = $argv[1] ?? 'Where can I learn more about ParaGra?';
$configFile = $argv[2] ?? __DIR__ . '/../config/ragie_cerebras.php';

if (!is_file($configFile)) {
    fwrite(STDERR, sprintf("Config file not found: %s\n", $configFile));
    exit(1);
}

$config = require $configFile;
$paragra = ParaGra::fromConfig($config);

$defaultEngines = parseEngines(getenv('TWAT_SEARCH_ENGINES') ?: 'brave,duckduckgo');
$defaultNumResults = max(1, (int) (getenv('TWAT_SEARCH_NUM_RESULTS') ?: 4));
$defaultMaxResults = max(1, (int) (getenv('TWAT_SEARCH_MAX_RESULTS') ?: 6));
$cacheTtl = max(10, (int) (getenv('TWAT_SEARCH_CACHE_TTL') ?: 90));

$twatSearch = new TwatSearchRetriever(
    binary: getenv('TWAT_SEARCH_BIN') ?: 'twat-search',
    defaultEngines: $defaultEngines,
    defaultNumResults: $defaultNumResults,
    defaultMaxResults: $defaultMaxResults,
    cacheTtlSeconds: $cacheTtl,
    environment: array_filter([
        'TAVILY_API_KEY' => getenv('TAVILY_API_KEY') ?: null,
        'BRAVE_API_KEY' => getenv('BRAVE_API_KEY') ?: null,
        'YOU_API_KEY' => getenv('YOU_API_KEY') ?: null,
        'SERPAPI_API_KEY' => getenv('SERPAPI_API_KEY') ?: null,
    ]),
);

/** @var UnifiedResponse $ragieContext */
$ragieContext = $paragra->retrieve($question, [
    'top_k' => (int) (getenv('TWAT_RAGIE_TOPK') ?: 6),
]);

$external = $ragieContext->isEmpty()
    ? $twatSearch->search($question, ['allow_cache' => true])
    : $twatSearch->search($question, ['max_results' => 3]);

$output = [
    'question' => $question,
    'ragie_chunks' => $ragieContext->getChunks(),
    'external_chunks' => $external->getChunks(),
    'external_metadata' => $external->getProviderMetadata(),
];

fwrite(STDOUT, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

/**
 * @return list<string>
 */
function parseEngines(string $value): array
{
    $parts = array_filter(array_map(
        static fn(string $engine): string => trim($engine),
        explode(',', $value)
    ));

    return $parts === [] ? ['brave', 'duckduckgo'] : $parts;
}
