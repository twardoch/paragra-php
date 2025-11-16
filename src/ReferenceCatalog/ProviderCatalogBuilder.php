<?php

declare(strict_types=1);

// this_file: paragra-php/src/ReferenceCatalog/ProviderCatalogBuilder.php

namespace ParaGra\ReferenceCatalog;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

final class ProviderCatalogBuilder
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildFromSource(string $sourcePath): array
    {
        $absoluteSource = $this->resolvePath($sourcePath);
        $data = $this->decodeJsonFile($absoluteSource);

        if (!isset($data['providers']) || !is_array($data['providers'])) {
            throw new RuntimeException('Source file is missing a providers array.');
        }

        $providers = [];
        foreach ($data['providers'] as $provider) {
            if (!is_array($provider)) {
                throw new InvalidArgumentException('Provider entries must be associative arrays.');
            }
            if (!isset($provider['slug']) || !is_string($provider['slug']) || $provider['slug'] === '') {
                throw new InvalidArgumentException('Each provider entry requires a non-empty slug.');
            }

            $provider['sources'] = $this->buildSourceList($provider['sources'] ?? []);
            $providers[] = $provider;
        }

        usort(
            $providers,
            static fn (array $left, array $right): int => strcmp((string) $left['slug'], (string) $right['slug'])
        );

        $meta = [
            'this_file' => 'reference/catalog/provider_insights.json',
            'schema_version' => $data['__meta__']['schema_version'] ?? 1,
            'generated_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                ->format(DateTimeInterface::ATOM),
            'source_file' => $this->relativePath($absoluteSource),
            'provider_count' => count($providers),
        ];

        return [
            '__meta__' => $meta,
            'providers' => $providers,
        ];
    }

    /**
     * @param array<string, mixed> $catalog
     *
     * @return list<string>
     */
    public function verifyCatalog(array $catalog): array
    {
        $errors = [];
        if (!isset($catalog['providers']) || !is_array($catalog['providers'])) {
            return ['Catalog is missing a providers list.'];
        }

        foreach ($catalog['providers'] as $provider) {
            $slug = is_array($provider) && isset($provider['slug']) ? (string) $provider['slug'] : '(unknown)';
            $sources = is_array($provider) ? $provider['sources'] ?? [] : [];
            if (!is_array($sources)) {
                $errors[] = sprintf('Provider %s has invalid sources metadata.', $slug);
                continue;
            }

            foreach ($sources as $source) {
                if (!is_array($source) || !isset($source['path'], $source['sha256'])) {
                    $errors[] = sprintf('Provider %s has a malformed source entry.', $slug);
                    continue;
                }

                $path = (string) $source['path'];
                $expected = (string) $source['sha256'];
                try {
                    $actual = $this->hashRelativePath($path);
                } catch (RuntimeException $exception) {
                    $errors[] = sprintf('Provider %s references missing file %s.', $slug, $path);
                    continue;
                }

                if (!hash_equals($expected, $actual)) {
                    $errors[] = sprintf('Provider %s source %s hash mismatch.', $slug, $path);
                }
            }
        }

        return $errors;
    }

    /**
     * @param list<array<string, mixed>> $sources
     *
     * @return list<array<string, int|string>>
     */
    private function buildSourceList(array $sources): array
    {
        $result = [];
        foreach ($sources as $source) {
            if (!is_array($source) || !isset($source['path']) || !is_string($source['path']) || $source['path'] === '') {
                throw new InvalidArgumentException('Each source entry requires a non-empty path.');
            }

            $entry = [
                'path' => $source['path'],
                'sha256' => $this->hashRelativePath($source['path']),
            ];

            if (isset($source['start_line'])) {
                $entry['start_line'] = (int) $source['start_line'];
            }
            if (isset($source['end_line'])) {
                $entry['end_line'] = (int) $source['end_line'];
            }

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonFile(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Unable to find source file at %s', $path));
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read source file at %s', $path));
        }

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('Decoded JSON is not an object.');
        }

        return $decoded;
    }

    private function hashRelativePath(string $relativePath): string
    {
        $absolutePath = $this->resolvePath($relativePath);
        if (!is_file($absolutePath)) {
            throw new RuntimeException(sprintf('Missing referenced file: %s', $relativePath));
        }

        $hash = hash_file('sha256', $absolutePath);
        if ($hash === false) {
            throw new RuntimeException(sprintf('Unable to hash file: %s', $relativePath));
        }

        return $hash;
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            throw new InvalidArgumentException('Path cannot be empty.');
        }

        if ($path[0] === DIRECTORY_SEPARATOR) {
            return $path;
        }

        return rtrim($this->projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }

    private function relativePath(string $absolutePath): string
    {
        $root = rtrim($this->projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (str_starts_with($absolutePath, $root)) {
            return substr($absolutePath, strlen($root));
        }

        return $absolutePath;
    }
}
