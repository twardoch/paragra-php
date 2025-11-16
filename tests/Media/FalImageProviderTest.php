<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Media/FalImageProviderTest.php

namespace ParaGra\Tests\Media;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use ParaGra\Media\FalImageProvider;
use ParaGra\Media\MediaException;
use ParaGra\Media\MediaRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use const JSON_THROW_ON_ERROR;

#[CoversClass(FalImageProvider::class)]
final class FalImageProviderTest extends TestCase
{
    public function test_generate_whenJobCompletes_thenReturnsArtifact(): void
    {
        $handler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['request_id' => 'job-1'], JSON_THROW_ON_ERROR)),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['status' => 'IN_PROGRESS'], JSON_THROW_ON_ERROR)),
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'status' => 'COMPLETED',
                    'images' => [
                        [
                            'url' => 'https://fal.ai/cdn/img.png',
                            'content_type' => 'image/png',
                            'width' => 1024,
                            'height' => 1024,
                        ],
                    ],
                ], JSON_THROW_ON_ERROR)
            ),
        ]);
        $client = new Client(['handler' => HandlerStack::create($handler)]);

        $provider = new FalImageProvider(
            http: $client,
            apiKey: 'fal-key',
            modelId: 'fal-ai/flux/dev',
            defaults: ['poll_interval_ms' => 0],
        );

        $result = $provider->generate(new MediaRequest('Flux skyline'));
        self::assertSame('https://fal.ai/cdn/img.png', $result->getFirstUrl());
        self::assertSame('fal.ai', $result->getProvider());
    }

    public function test_generate_whenFalReportsFailure_thenThrows(): void
    {
        $handler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['request_id' => 'job-2'], JSON_THROW_ON_ERROR)),
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode(['status' => 'FAILED', 'error' => 'quota exceeded'], JSON_THROW_ON_ERROR)
            ),
        ]);
        $client = new Client(['handler' => HandlerStack::create($handler)]);

        $provider = new FalImageProvider(
            $client,
            'fal-key',
            'fal-ai/flux/dev',
            ['poll_interval_ms' => 0, 'max_poll_attempts' => 2]
        );

        $this->expectException(MediaException::class);
        $provider->generate(new MediaRequest('Should fail'));
    }

    public function test_generate_whenTimeoutReached_thenThrows(): void
    {
        $handler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['request_id' => 'job-3'], JSON_THROW_ON_ERROR)),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['status' => 'RUNNING'], JSON_THROW_ON_ERROR)),
        ]);
        $client = new Client(['handler' => HandlerStack::create($handler)]);

        $provider = new FalImageProvider(
            $client,
            'fal-key',
            'fal-ai/flux/dev',
            ['poll_interval_ms' => 0, 'max_poll_attempts' => 1]
        );

        $this->expectException(MediaException::class);
        $provider->generate(new MediaRequest('Still running'));
    }
}
