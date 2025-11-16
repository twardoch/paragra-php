<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Response/UnifiedResponseTest.php

namespace ParaGra\Tests\Response;

use InvalidArgumentException;
use ParaGra\Response\UnifiedResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnifiedResponse::class)]
final class UnifiedResponseTest extends TestCase
{
    public function test_construction_normalizesChunks(): void
    {
        $response = new UnifiedResponse(
            provider: 'ragie',
            model: 'gpt-4o-mini',
            chunks: [
                [
                    'text' => '  chunk A  ',
                    'score' => 0.92,
                    'document_id' => ' doc-1 ',
                    'document_name' => ' Example Doc ',
                    'metadata' => ['source' => 'ragie'],
                ],
            ],
            providerMetadata: ['pool' => 'primary'],
            usage: ['tokens' => 42],
            cost: ['usd' => 0.001],
        );

        self::assertSame('ragie', $response->getProvider());
        self::assertSame('gpt-4o-mini', $response->getModel());
        self::assertSame([
            [
                'text' => 'chunk A',
                'score' => 0.92,
                'document_id' => 'doc-1',
                'document_name' => 'Example Doc',
                'metadata' => ['source' => 'ragie'],
            ],
        ], $response->getChunks());
        self::assertSame(['chunk A'], $response->getChunkTexts());
        self::assertSame(['pool' => 'primary'], $response->getProviderMetadata());
        self::assertSame(['tokens' => 42], $response->getUsage());
        self::assertSame(['usd' => 0.001], $response->getCost());
        self::assertFalse($response->isEmpty());
        self::assertSame(
            [
                'provider' => 'ragie',
                'model' => 'gpt-4o-mini',
                'chunks' => [
                    [
                        'text' => 'chunk A',
                        'score' => 0.92,
                        'document_id' => 'doc-1',
                        'document_name' => 'Example Doc',
                        'metadata' => ['source' => 'ragie'],
                    ],
                ],
                'metadata' => ['pool' => 'primary'],
                'usage' => ['tokens' => 42],
                'cost' => ['usd' => 0.001],
            ],
            $response->toArray()
        );
    }

    public function test_getChunkTexts_isMemoized(): void
    {
        $response = new UnifiedResponse(
            provider: 'ragie',
            model: 'gpt-4o-mini',
            chunks: [
                ['text' => 'chunk A'],
                ['text' => 'chunk B'],
            ],
        );

        $firstCall = $response->getChunkTexts();
        $secondCall = $response->getChunkTexts();

        self::assertSame($firstCall, $secondCall);
    }

    public function test_constructor_whenChunkMissingText_thenThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Chunk 0 is missing a valid "text" field.');

        new UnifiedResponse(
            provider: 'ragie',
            model: 'gpt-4o-mini',
            chunks: [
                ['score' => 0.5],
            ],
        );
    }

    public function test_isEmpty_returnsTrueWhenNoChunks(): void
    {
        $response = new UnifiedResponse(
            provider: 'ragie',
            model: 'gpt-4o-mini',
            chunks: [],
        );

        self::assertTrue($response->isEmpty());
        self::assertCount(0, $response);
    }

    public function test_fromChunks_staticFactory(): void
    {
        $response = UnifiedResponse::fromChunks(
            provider: 'gemini',
            model: 'gemini-2.0-flash-exp',
            chunks: [
                [
                    'text' => 'Gemini chunk',
                    'score' => 0.81,
                ],
            ],
            metadata: ['solution' => 'gemini-file-search'],
            usage: ['prompt_tokens' => 10],
            cost: ['usd' => 0.0002],
        );

        self::assertSame('gemini', $response->getProvider());
        self::assertSame(['Gemini chunk'], $response->getChunkTexts());
        self::assertSame(['solution' => 'gemini-file-search'], $response->getProviderMetadata());
        self::assertSame(['prompt_tokens' => 10], $response->getUsage());
        self::assertSame(['usd' => 0.0002], $response->getCost());
    }
}
