#!/usr/bin/env php
<?php

declare(strict_types=1);

// this_file: paragra-php/tools/provider_catalog.php

use ParaGra\ReferenceCatalog\ProviderCatalogBuilder;

require __DIR__ . '/../vendor/autoload.php';

const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

$argv = $_SERVER['argv'] ?? [];
$command = $argv[1] ?? 'build';
$options = getopt('', ['project-root::', 'source::', 'output::', 'catalog::']);

$defaultRoot = dirname(__DIR__, 2);
$projectRoot = isset($options['project-root']) ? (string) $options['project-root'] : $defaultRoot;
$builder = new ProviderCatalogBuilder($projectRoot);

switch ($command) {
    case 'build':
        $source = isset($options['source'])
            ? (string) $options['source']
            : $projectRoot . '/reference/catalog/provider_insights.source.json';
        $output = isset($options['output'])
            ? (string) $options['output']
            : $projectRoot . '/reference/catalog/provider_insights.json';

        $catalog = $builder->buildFromSource($source);
        $json = json_encode($catalog, JSON_FLAGS) . "\n";
        if ($json === false) {
            fwrite(STDERR, "Failed to encode provider catalog." . PHP_EOL);
            exit(1);
        }

        if (file_put_contents($output, $json) === false) {
            fwrite(STDERR, sprintf('Unable to write catalog to %s', $output) . PHP_EOL);
            exit(1);
        }

        fwrite(
            STDOUT,
            sprintf('Wrote %d provider entries to %s' . PHP_EOL, $catalog['__meta__']['provider_count'] ?? 0, $output)
        );

        break;

    case 'verify':
        $catalogPath = isset($options['catalog'])
            ? (string) $options['catalog']
            : $projectRoot . '/reference/catalog/provider_insights.json';

        if (!is_file($catalogPath)) {
            fwrite(STDERR, sprintf('Catalog file %s not found.' . PHP_EOL, $catalogPath));
            exit(1);
        }

        $contents = file_get_contents($catalogPath);
        if ($contents === false) {
            fwrite(STDERR, sprintf('Unable to read catalog file %s.' . PHP_EOL, $catalogPath));
            exit(1);
        }

        $catalog = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $errors = $builder->verifyCatalog($catalog);
        if ($errors !== []) {
            foreach ($errors as $error) {
                fwrite(STDERR, $error . PHP_EOL);
            }

            exit(1);
        }

        fwrite(STDOUT, 'Catalog hashes match referenced sources.' . PHP_EOL);
        break;

    default:
        fwrite(STDERR, "Usage: provider_catalog.php [build|verify] [--project-root=path]" . PHP_EOL);
        exit(1);
}
