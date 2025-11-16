<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Providers/AbstractProviderTest.php

namespace ParaGra\Tests\Providers;

use InvalidArgumentException;
use ParaGra\Config\ProviderSpec;
use ParaGra\Providers\AbstractProvider;
use ParaGra\Response\UnifiedResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractProvider::class)]
final class AbstractProviderTest extends TestCase
{
    public function test_supports_whenCapabilityPresent_thenReturnsTrue(): void
    {
        $provider = $this->createProvider(['retrieval', 'LLM_Generation']);

        self::assertTrue($provider->supports('retrieval'));
        self::assertTrue($provider->supports('llm_generation'), 'Lookup should be case-insensitive.');
        self::assertSame(['retrieval', 'llm_generation'], $provider->getCapabilities());
    }

    public function test_supports_whenCapabilityMissing_thenReturnsFalse(): void
    {
        $provider = $this->createProvider(['retrieval']);

        self::assertFalse($provider->supports('rerank'));
    }

    public function test_gettersExposeSpecData(): void
    {
        $provider = $this->createProvider(['retrieval']);

        self::assertSame('ragie', $provider->getProvider());
        self::assertSame('gpt-4o-mini', $provider->getModel());
        self::assertSame([
            'type' => 'ragie',
            'partition' => 'default',
        ], $provider->exposeSolution());
    }

    public function test_sanitizeQuery_whenValid_thenReturnsTrimmed(): void
    {
        $provider = $this->createProvider();

        self::assertSame('What is ParaGra?', $provider->exposeSanitize("  What is ParaGra?  "));
    }

    public function test_sanitizeQuery_whenEmpty_thenThrows(): void
    {
        $provider = $this->createProvider();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Query text cannot be empty');

        $provider->exposeSanitize('   ');
    }

    public function test_baseMetadata_includesProviderAndModel(): void
    {
        $provider = $this->createProvider();

        self::assertSame([
            'provider' => 'ragie',
            'model' => 'gpt-4o-mini',
        ], $provider->exposeBaseMetadata());
    }

    public function test_invalidOptionHelper_buildsConsistentMessage(): void
    {
        $provider = $this->createProvider();

        $exception = $provider->throwInvalidOption('top_k', 'must be positive');

        self::assertSame('Invalid option "top_k": must be positive', $exception->getMessage());
    }

    public function test_retrieve_usesUnifiedResponse(): void
    {
        $provider = $this->createProvider();

        $response = $provider->retrieve('  hello world   ');

        self::assertInstanceOf(UnifiedResponse::class, $response);
        self::assertSame('ragie', $response->getProvider());
        self::assertSame('gpt-4o-mini', $response->getModel());
        self::assertSame(['hello world'], $response->getChunkTexts());
        self::assertSame([
            'provider' => 'ragie',
            'model' => 'gpt-4o-mini',
        ], $response->getProviderMetadata());
    }

    /**
     * @param list<string> $capabilities
     */
    private function createProvider(array $capabilities = []): DummyProvider
    {
        return new DummyProvider($this->spec(), $capabilities);
    }

    private function spec(): ProviderSpec
    {
        return new ProviderSpec(
            provider: 'ragie',
            model: 'gpt-4o-mini',
            apiKey: 'sk-test',
            solution: [
                'type' => 'ragie',
                'partition' => 'default',
            ]
        );
    }
}

/**
 * @internal
 */
final class DummyProvider extends AbstractProvider
{
    #[\Override]
    public function retrieve(string $query, array $options = []): UnifiedResponse
    {
        $cleanQuery = $this->exposeSanitize($query);

        return new UnifiedResponse(
            provider: $this->getProvider(),
            model: $this->getModel(),
            chunks: [
                [
                    'text' => $cleanQuery,
                    'document_id' => $options['document_id'] ?? 'doc-1',
                ],
            ],
            providerMetadata: $this->exposeBaseMetadata(),
        );
    }

    public function exposeSanitize(string $query): string
    {
        return $this->sanitizeQuery($query);
    }

    /**
     * @return array<string, mixed>
     */
    public function exposeSolution(): array
    {
        return $this->getSolution();
    }

    /**
     * @return array<string, string>
     */
    public function exposeBaseMetadata(): array
    {
        return $this->baseMetadata();
    }

    public function throwInvalidOption(string $name, string $reason): InvalidArgumentException
    {
        return $this->invalidOption($name, $reason);
    }
}
