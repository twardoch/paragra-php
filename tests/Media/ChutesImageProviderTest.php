<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Media/ChutesImageProviderTest.php

namespace ParaGra\Tests\Media;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use ParaGra\Media\ChutesImageProvider;
use ParaGra\Media\MediaException;
use ParaGra\Media\MediaRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use const JSON_THROW_ON_ERROR;

#[CoversClass(ChutesImageProvider::class)]
final class ChutesImageProviderTest extends TestCase
{
    public function test_generate_whenJsonResponse_thenReturnsMediaResult(): void
    {
        $payload = [
            'images' => [
                [
                    'url' => 'https://cdn.example/chutes/img-1.png',
                    'mime_type' => 'image/png',
                    'width' => 1024,
                    'height' => 576,
                ],
            ],
            'job_id' => 'job_123',
        ];
        $handler = new MockHandler([new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR)
        )]);
        $client = new Client(['handler' => HandlerStack::create($handler)]);

        $provider = new ChutesImageProvider(
            http: $client,
            baseUrl: 'https://artist-chute.chutes.ai',
            apiKey: 'secret',
            defaults: ['model' => 'flux.1-dev'],
        );

        $result = $provider->generate(new MediaRequest('Book cover with glyphs'));

        self::assertSame('chutes', $result->getProvider());
        self::assertSame('https://cdn.example/chutes/img-1.png', $result->getFirstUrl());
        self::assertSame('flux.1-dev', $result->getModel());
    }

    public function test_generate_whenBinaryResponse_thenEncodesBase64(): void
    {
        $handler = new MockHandler([new Response(200, ['Content-Type' => 'image/png'], 'PNGDATA')]);
        $client = new Client(['handler' => HandlerStack::create($handler)]);

        $provider = new ChutesImageProvider($client, 'https://demo-chute.chutes.ai', 'secret');

        $result = $provider->generate(new MediaRequest('Icon grid', width: 640, height: 640));
        $artifact = $result->getArtifacts()[0];

        self::assertArrayHasKey('base64', $artifact);
        self::assertSame('image/png', $artifact['mime_type']);
    }

    public function test_generate_whenRetriesExhausted_thenThrows(): void
    {
        $handler = new MockHandler([
            new Response(500),
            new Response(500),
        ]);
        $client = new Client(['handler' => HandlerStack::create($handler)]);

        $provider = new ChutesImageProvider(
            $client,
            'https://unstable.chutes.ai',
            'secret',
            ['max_retries' => 1, 'retry_delay_ms' => 0]
        );

        $this->expectException(MediaException::class);
        $provider->generate(new MediaRequest('Failing request'));
    }
}
