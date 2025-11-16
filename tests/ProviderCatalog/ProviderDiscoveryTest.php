<?php

declare(strict_types=1);

// this_file: paragra-php/tests/ProviderCatalog/ProviderDiscoveryTest.php

namespace ParaGra\Tests\ProviderCatalog;

use ParaGra\Config\ProviderSpec;
use ParaGra\Exception\ConfigurationException;
use ParaGra\ProviderCatalog\ProviderDiscovery;
use ParaGra\ProviderCatalog\ProviderSummary;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_map;
use function dirname;
use function in_array;
use function putenv;

final class ProviderDiscoveryTest extends TestCase
{
    private ProviderDiscovery $discovery;

    protected function setUp(): void
    {
        parent::setUp();

        $catalogPath = dirname(__DIR__, 2) . '/config/providers/catalog.php';
        $this->discovery = ProviderDiscovery::fromFile($catalogPath);
    }

    protected function tearDown(): void
    {
        putenv('OPENAI_API_KEY');
        parent::tearDown();
    }

    public function testListProvidersExposesSummaries(): void
    {
        $providers = $this->discovery->listProviders();
        self::assertNotEmpty($providers);
        self::assertInstanceOf(ProviderSummary::class, $providers[0]);

        $openai = $this->discovery->get('openai');
        self::assertNotNull($openai);
        self::assertTrue($openai->capabilities()->llmChat());
        self::assertTrue($openai->capabilities()->embeddings());
    }

    public function testFilterByCapabilityReturnsMatchingProviders(): void
    {
        $embeddingProviders = $this->discovery->filterByCapability('embeddings');
        self::assertNotEmpty($embeddingProviders);
        $slugs = array_map(
            static fn (ProviderSummary $summary): string => $summary->slug(),
            $embeddingProviders
        );

        self::assertTrue(in_array('openai', $slugs, true));
    }

    public function testSupportsEmbeddingDimension(): void
    {
        self::assertTrue($this->discovery->supportsEmbeddingDimension('openai', 1536));
        self::assertFalse($this->discovery->supportsEmbeddingDimension('openai', 9999));
    }

    public function testPreferredVectorStore(): void
    {
        self::assertSame('gemini-file-search', $this->discovery->preferredVectorStore('gemini'));
        self::assertNull($this->discovery->preferredVectorStore('unknown'));
    }

    public function testBuildProviderSpecUsesEnvironment(): void
    {
        putenv('OPENAI_API_KEY=test-openai');
        $spec = $this->discovery->buildProviderSpec('openai');

        self::assertInstanceOf(ProviderSpec::class, $spec);
        self::assertSame('openai', $spec->provider);
        self::assertSame('gpt-4o-mini', $spec->model);
        self::assertSame('test-openai', $spec->apiKey);
        self::assertSame('ragie', $spec->solution['type']);
    }

    public function testBuildProviderSpecCanOverrideModelAndSolution(): void
    {
        putenv('OPENAI_API_KEY=test-openai');
        $spec = $this->discovery->buildProviderSpec('openai', 'generation', [
            'model' => 'gpt-4.1-mini',
            'solution' => [
                'default_options' => ['top_k' => 4],
            ],
        ]);

        self::assertSame('gpt-4.1-mini', $spec->model);
        self::assertSame(4, $spec->solution['default_options']['top_k']);
    }

    public function testBuildProviderSpecFailsWhenApiKeyMissing(): void
    {
        putenv('OPENAI_API_KEY');

        $this->expectException(ConfigurationException::class);
        $this->discovery->buildProviderSpec('openai');
    }

    public function testBuildProviderSpecFailsForUnknownProvider(): void
    {
        putenv('OPENAI_API_KEY=test-openai');

        $this->expectException(ConfigurationException::class);
        $this->discovery->buildProviderSpec('does-not-exist');
    }

    public function testGetReturnsNullForUnknownProvider(): void
    {
        self::assertNull($this->discovery->get('does-not-exist'));
    }

    public function testFromFileThrowsWhenMissing(): void
    {
        $this->expectException(RuntimeException::class);
        ProviderDiscovery::fromFile('/tmp/does-not-exist.json');
    }

    public function testFromFileRejectsInvalidJson(): void
    {
        $path = sys_get_temp_dir() . '/invalid-catalog-' . uniqid('', true) . '.json';
        file_put_contents($path, '{invalid');

        try {
            $this->expectException(RuntimeException::class);
            ProviderDiscovery::fromFile($path);
        } finally {
            @unlink($path);
        }
    }

    public function testFromCatalogArrayRejectsInvalidPayload(): void
    {
        $this->expectException(RuntimeException::class);
        ProviderDiscovery::fromCatalogArray(['invalid' => []]);
    }

    public function testBuildProviderSpecFailsWhenModelPresetMissing(): void
    {
        putenv('OPENAI_API_KEY=test-openai');

        $this->expectException(ConfigurationException::class);
        $this->discovery->buildProviderSpec('openai', 'nonexistent');
    }

    public function testBuildProviderSpecFailsWhenEnvEmpty(): void
    {
        putenv('OPENAI_API_KEY=   ');

        $this->expectException(ConfigurationException::class);
        $this->discovery->buildProviderSpec('openai');
    }
}
