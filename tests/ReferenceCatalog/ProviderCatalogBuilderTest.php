<?php

declare(strict_types=1);

// this_file: paragra-php/tests/ReferenceCatalog/ProviderCatalogBuilderTest.php

namespace ParaGra\Tests\ReferenceCatalog;

use InvalidArgumentException;
use ParaGra\ReferenceCatalog\ProviderCatalogBuilder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProviderCatalogBuilderTest extends TestCase
{
    public function testBuildFromSourceAddsHashesAndMetadata(): void
    {
        $fixture = $this->createFixture();
        $builder = new ProviderCatalogBuilder($fixture['root']);
        $catalog = $builder->buildFromSource($fixture['source']);

        self::assertSame('reference/catalog/provider_insights.json', $catalog['__meta__']['this_file']);
        self::assertSame(1, $catalog['__meta__']['provider_count']);

        $provider = $catalog['providers'][0];
        self::assertSame('demo-provider', $provider['slug']);

        $expectedHash = hash_file('sha256', $fixture['doc']);
        self::assertSame($expectedHash, $provider['sources'][0]['sha256']);
    }

    public function testVerifyCatalogDetectsChecksumMismatch(): void
    {
        $fixture = $this->createFixture();
        $builder = new ProviderCatalogBuilder($fixture['root']);
        $catalog = $builder->buildFromSource($fixture['source']);

        file_put_contents($fixture['doc'], "mutated content\n");

        $errors = $builder->verifyCatalog($catalog);
        self::assertNotEmpty($errors);
        self::assertStringContainsString('hash mismatch', $errors[0]);
    }

    public function testBuildFromSourceRejectsMissingProvidersList(): void
    {
        $fixture = $this->createFixture();
        file_put_contents(
            $fixture['source'],
            json_encode(['__meta__' => ['schema_version' => 1]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $builder = new ProviderCatalogBuilder($fixture['root']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('providers array');
        $builder->buildFromSource($fixture['source']);
    }

    public function testBuildFromSourceRejectsMalformedSourceEntries(): void
    {
        $fixture = $this->createFixture();
        $invalidPayload = [
            '__meta__' => ['schema_version' => 1],
            'providers' => [
                [
                    'slug' => 'demo-provider',
                    'sources' => [
                        ['path' => ''],
                    ],
                ],
            ],
        ];
        file_put_contents(
            $fixture['source'],
            json_encode($invalidPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $builder = new ProviderCatalogBuilder($fixture['root']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('source entry');
        $builder->buildFromSource($fixture['source']);
    }

    public function testVerifyCatalogDetectsMissingSourceFiles(): void
    {
        $fixture = $this->createFixture();
        $builder = new ProviderCatalogBuilder($fixture['root']);
        $catalog = $builder->buildFromSource($fixture['source']);

        unlink($fixture['doc']);
        $errors = $builder->verifyCatalog($catalog);

        self::assertNotEmpty($errors);
        self::assertStringContainsString('references missing file', $errors[0]);
    }

    public function testVerifyCatalogFlagsInvalidSourceShape(): void
    {
        $fixture = $this->createFixture();
        $builder = new ProviderCatalogBuilder($fixture['root']);
        $catalog = $builder->buildFromSource($fixture['source']);

        $catalog['providers'][0]['sources'] = 'invalid';

        $errors = $builder->verifyCatalog($catalog);
        self::assertSame(['Provider demo-provider has invalid sources metadata.'], $errors);
    }

    public function testBuildFromSourceRejectsNonArrayProviders(): void
    {
        $fixture = $this->createFixture(data: [
            '__meta__' => ['schema_version' => 1],
            'providers' => ['invalid-entry'],
        ]);
        $builder = new ProviderCatalogBuilder($fixture['root']);

        $this->expectException(InvalidArgumentException::class);
        $builder->buildFromSource($fixture['source']);
    }

    public function testBuildFromSourceSortsProvidersBySlug(): void
    {
        $fixture = $this->createFixture(data: [
            '__meta__' => ['schema_version' => 1],
            'providers' => [
                [
                    'slug' => 'z-provider',
                    'sources' => [['path' => 'reference/research/sample.md']],
                ],
                [
                    'slug' => 'a-provider',
                    'sources' => [['path' => 'reference/research/sample.md']],
                ],
            ],
        ]);
        $builder = new ProviderCatalogBuilder($fixture['root']);
        $catalog = $builder->buildFromSource($fixture['source']);

        self::assertSame(['a-provider', 'z-provider'], [
            $catalog['providers'][0]['slug'],
            $catalog['providers'][1]['slug'],
        ]);
    }

    public function testBuildFromSourceKeepsAbsoluteSourcePaths(): void
    {
        $external = sys_get_temp_dir() . '/external-source-' . uniqid('', true) . '.md';
        file_put_contents($external, 'external');

        $fixture = $this->createFixture(data: [
            '__meta__' => ['schema_version' => 1],
            'providers' => [
                [
                    'slug' => 'abs-provider',
                    'sources' => [
                        ['path' => $external],
                    ],
                ],
            ],
        ]);

        $builder = new ProviderCatalogBuilder($fixture['root']);
        $catalog = $builder->buildFromSource($fixture['source']);

        self::assertSame($external, $catalog['providers'][0]['sources'][0]['path']);
    }

    /**
     * @return array{root: string, source: string, doc: string}
     */
    private function createFixture(?array $data = null): array
    {
        $root = sys_get_temp_dir() . '/provider-catalog-' . uniqid('', true);
        $catalogDir = $root . '/reference/catalog';
        $researchDir = $root . '/reference/research';
        if (!mkdir($catalogDir, 0777, true) && !is_dir($catalogDir)) {
            self::fail('Unable to create catalog directory.');
        }
        if (!mkdir($researchDir, 0777, true) && !is_dir($researchDir)) {
            self::fail('Unable to create research directory.');
        }

        $docPath = $researchDir . '/sample.md';
        file_put_contents($docPath, "demo content\n");

        $sourceData = $data ?? [
            '__meta__' => [
                'this_file' => 'reference/catalog/provider_insights.source.json',
                'schema_version' => 1,
            ],
            'providers' => [
                [
                    'slug' => 'demo-provider',
                    'name' => 'Demo Provider',
                    'category' => 'llm',
                    'modalities' => ['text'],
                    'recommended_roles' => ['demo'],
                    'reset_window' => 'daily',
                    'commercial_use' => 'demo use only',
                    'free_quota' => ['requests_per_day' => 1],
                    'notes' => 'demo entry',
                    'sources' => [
                        [
                            'path' => 'reference/research/sample.md',
                            'start_line' => 1,
                            'end_line' => 1,
                        ],
                    ],
                ],
            ],
        ];

        $sourcePath = $catalogDir . '/provider_insights.source.json';
        file_put_contents(
            $sourcePath,
            json_encode($sourceData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return [
            'root' => $root,
            'source' => $sourcePath,
            'doc' => $docPath,
        ];
    }
}
