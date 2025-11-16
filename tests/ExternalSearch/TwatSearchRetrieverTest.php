<?php

declare(strict_types=1);

// this_file: paragra-php/tests/ExternalSearch/TwatSearchRetrieverTest.php

namespace ParaGra\Tests\ExternalSearch;

use ParaGra\ExternalSearch\ExternalSearchException;
use ParaGra\ExternalSearch\TwatSearchRetriever;
use ParaGra\Response\UnifiedResponse;
use PHPUnit\Framework\TestCase;

use function json_encode;

/**
 * @covers \ParaGra\ExternalSearch\TwatSearchRetriever
 */
final class TwatSearchRetrieverTest extends TestCase
{
    public function testSearchNormalizesResultsAndMetadata(): void
    {
        $runnerCalls = [];
        $runner = function (array $command, array $env, float $timeout) use (&$runnerCalls): array {
            $runnerCalls[] = $command;

            return [
                'exit_code' => 0,
                'stdout' => (string) json_encode([
                    [
                        'source_engine' => 'brave',
                        'title' => 'ParaGra overview',
                        'url' => 'https://example.com/paragra',
                        'snippet' => 'ParaGra orchestrates Ragie, Gemini File Search, and more.',
                        'score' => 0.91,
                        'position' => 1,
                    ],
                    [
                        'source_engine' => 'duckduckgo',
                        'title' => 'Secondary',
                        'url' => 'https://example.com/secondary',
                        'snippet' => 'Second result text.',
                        'score' => null,
                        'position' => 2,
                        'extra_info' => ['type' => 'web'],
                        'raw' => ['hostname' => 'example.com'],
                    ],
                ]),
                'stderr' => '',
            ];
        };

        $retriever = new TwatSearchRetriever(
            defaultEngines: ['brave', 'duckduckgo'],
            defaultNumResults: 5,
            defaultMaxResults: 4,
            maxAttempts: 1,
            retryDelayMs: 0,
            timeoutSeconds: 1.0,
            cacheTtlSeconds: 60,
            cacheLimit: 4,
            processRunner: $runner,
        );

        $response = $retriever->search('ParaGra docs', [
            'engines' => 'brave,duckduckgo',
            'num_results' => 3,
            'max_results' => 1,
        ]);

        self::assertCount(1, $response);
        $chunk = $response->getChunks()[0];
        self::assertSame('ParaGra overview', $chunk['document_name']);
        self::assertSame('https://example.com/paragra', $chunk['document_id']);
        self::assertSame('ParaGra overview' . "\n\n" . 'ParaGra orchestrates Ragie, Gemini File Search, and more.', $chunk['text']);
        self::assertSame(0.91, $chunk['score']);
        self::assertSame('brave', $chunk['metadata']['engine']);

        $metadata = $response->getProviderMetadata();
        self::assertSame(['brave', 'duckduckgo'], $metadata['engines']);
        self::assertSame(3, $metadata['num_results']);
        self::assertSame(1, $metadata['max_results']);
        self::assertFalse($metadata['cache_hit']);
        self::assertSame(1, $metadata['retry_count']);
        self::assertSame('twat-search', $response->getProvider());
        self::assertSame('twat-search-cli', $response->getModel());

        self::assertNotEmpty($runnerCalls);
        $command = $runnerCalls[0];
        self::assertSame('twat-search', $command[0]);
        self::assertSame('web', $command[1]);
        self::assertSame('q', $command[2]);
        self::assertSame('ParaGra docs', $command[3]);
        self::assertContains('-e', $command);
        self::assertContains('--num_results', $command);
    }

    public function testSearchCachesResponsesWhenEnabled(): void
    {
        $callCount = 0;
        $runner = function (array $command, array $env, float $timeout) use (&$callCount): array {
            $callCount++;

            return [
                'exit_code' => 0,
                'stdout' => (string) json_encode([
                    [
                        'source_engine' => 'brave',
                        'title' => 'Cached entry',
                        'snippet' => 'Cached snippet',
                        'url' => 'https://example.com/cache',
                    ],
                ]),
                'stderr' => '',
            ];
        };

        $retriever = new TwatSearchRetriever(
            maxAttempts: 1,
            retryDelayMs: 0,
            cacheTtlSeconds: 10,
            cacheLimit: 2,
            processRunner: $runner,
        );

        $first = $retriever->search('Cache me');
        $second = $retriever->search('Cache me');

        self::assertInstanceOf(UnifiedResponse::class, $first);
        self::assertInstanceOf(UnifiedResponse::class, $second);
        self::assertSame(1, $callCount, 'Process runner should only execute once due to caching.');
        self::assertFalse($first->getProviderMetadata()['cache_hit']);
        self::assertTrue($second->getProviderMetadata()['cache_hit']);
        self::assertSame($first->getChunks(), $second->getChunks());
    }

    public function testSearchRetriesOnFailure(): void
    {
        $callCount = 0;
        $runner = function (array $command, array $env, float $timeout) use (&$callCount): array {
            $callCount++;

            if ($callCount === 1) {
                return [
                    'exit_code' => 1,
                    'stdout' => '',
                    'stderr' => 'network failure',
                ];
            }

            return [
                'exit_code' => 0,
                'stdout' => (string) json_encode([
                    [
                        'source_engine' => 'brave',
                        'title' => 'Recovered',
                        'snippet' => 'Second attempt',
                        'url' => 'https://example.com/retry',
                    ],
                ]),
                'stderr' => '',
            ];
        };

        $retriever = new TwatSearchRetriever(
            maxAttempts: 2,
            retryDelayMs: 0,
            processRunner: $runner,
        );

        $response = $retriever->search('Retry scenario');
        $metadata = $response->getProviderMetadata();

        self::assertSame(2, $callCount);
        self::assertSame(2, $metadata['retry_count']);
        self::assertSame('Recovered', $response->getChunks()[0]['document_name']);
    }

    public function testSearchThrowsAfterExceededRetries(): void
    {
        $runner = static fn (array $command, array $env, float $timeout): array => [
            'exit_code' => 127,
            'stdout' => '',
            'stderr' => 'command not found',
        ];

        $retriever = new TwatSearchRetriever(
            maxAttempts: 2,
            retryDelayMs: 0,
            processRunner: $runner,
        );

        $this->expectException(ExternalSearchException::class);
        $this->expectExceptionMessage('twat-search exited with code 127');
        $retriever->search('Missing binary');
    }

    public function testSearchRejectsEmptyQueries(): void
    {
        $retriever = new TwatSearchRetriever(processRunner: static fn (array $command, array $env, float $timeout): array => [
            'exit_code' => 0,
            'stdout' => '[]',
            'stderr' => '',
        ]);

        $this->expectException(ExternalSearchException::class);
        $retriever->search('   ');
    }

    public function testSearchThrowsWhenJsonOutputIsInvalid(): void
    {
        $retriever = new TwatSearchRetriever(
            maxAttempts: 1,
            processRunner: static fn (array $command, array $env, float $timeout): array => [
                'exit_code' => 0,
                'stdout' => 'not-json',
                'stderr' => '',
            ],
        );

        $this->expectException(ExternalSearchException::class);
        $this->expectExceptionMessage('Failed to locate JSON array');
        $retriever->search('Malformed JSON');
    }
}
