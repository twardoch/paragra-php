#!/usr/bin/env php
<?php

declare(strict_types=1);

// this_file: paragra-php/tools/pool_builder.php

use ParaGra\Planner\PoolBuilder;
use ParaGra\ProviderCatalog\ProviderDiscovery;

require __DIR__ . '/../vendor/autoload.php';

const PB_JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

$options = getopt('', ['preset::', 'catalog::', 'format::', 'output::']);

$preset = isset($options['preset']) ? (string) $options['preset'] : PoolBuilder::PRESET_FREE;
$catalogPath = isset($options['catalog'])
    ? (string) $options['catalog']
    : __DIR__ . '/../config/providers/catalog.php';
$format = isset($options['format']) ? strtolower((string) $options['format']) : 'php';
$outputPath = isset($options['output']) ? (string) $options['output'] : null;

try {
    $catalog = ProviderDiscovery::fromFile($catalogPath);
    $builder = PoolBuilder::fromGlobals($catalog);
    $priorityPools = $builder->build($preset);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

$payload = [
    'provider_catalog' => $catalogPath,
    'priority_pools' => $priorityPools,
];

if ($format === 'json') {
    $contents = json_encode($payload, PB_JSON_FLAGS) . PHP_EOL;
} elseif ($format === 'php') {
    $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($payload, true) . ";\n";
} else {
    fwrite(STDERR, sprintf('Unsupported format "%s". Use "php" or "json".', $format) . PHP_EOL);
    exit(1);
}

if ($outputPath !== null) {
    if (file_put_contents($outputPath, $contents) === false) {
        fwrite(STDERR, sprintf('Unable to write pools to %s', $outputPath) . PHP_EOL);
        exit(1);
    }

    exit(0);
}

fwrite(STDOUT, $contents);
