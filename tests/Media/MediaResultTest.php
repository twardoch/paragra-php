<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Media/MediaResultTest.php

namespace ParaGra\Tests\Media;

use InvalidArgumentException;
use ParaGra\Media\MediaResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MediaResult::class)]
final class MediaResultTest extends TestCase
{
    public function test_constructor_whenValidArtifacts_thenStoresMetadata(): void
    {
        $result = new MediaResult(
            provider: 'chutes',
            model: 'flux.1-pro',
            artifacts: [[
                'url' => 'https://cdn.example/chutes/image.png',
                'mime_type' => 'image/png',
                'width' => 1024,
                'height' => 576,
                'metadata' => ['engine' => 'flux', 'nsfw' => 0],
            ]],
            metadata: ['job_id' => 'job_123'],
        );

        self::assertSame('chutes', $result->getProvider());
        self::assertSame('https://cdn.example/chutes/image.png', $result->getFirstUrl());
        self::assertSame('job_123', $result->getMetadata()['job_id'] ?? null);
        self::assertSame('flux.1-pro', $result->getModel());
    }

    public function test_constructor_whenNoArtifact_thenThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MediaResult('fal', 'fal-ai/flux/dev', []);
    }

    public function test_constructor_whenArtifactMissingPayload_thenThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MediaResult('fal', 'fal-ai/flux/dev', [['mime_type' => 'image/png']]);
    }

    public function test_getFirstUrl_returnsNullForInlineArtifacts(): void
    {
        $result = new MediaResult('fal', 'flux', [['base64' => 'Zg==']]);
        self::assertNull($result->getFirstUrl());
    }

    public function test_constructor_rejects_empty_provider(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MediaResult('   ', 'flux', [['url' => 'https://example.com']]);
    }

    public function test_constructor_rejects_invalid_artifact_metadata(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MediaResult('fal', 'flux', [[
            'url' => 'https://cdn.example/file.png',
            'metadata' => ['bad' => ['nested' => 'value']],
        ]]);
    }

    public function test_constructor_rejects_non_scalar_metadata_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MediaResult(
            'fal',
            'flux',
            [['url' => 'https://cdn.example/file.png']],
            metadata: ['bad' => ['nested']]
        );
    }

    public function test_toArray_includes_artifacts_and_metadata(): void
    {
        $result = new MediaResult(
            'chutes',
            'flux',
            [[
                'url' => 'https://cdn.example/file.png',
                'bytes' => 1024,
            ]],
            metadata: ['job' => '123']
        );

        $payload = $result->toArray();
        self::assertSame('chutes', $payload['provider']);
        self::assertSame('flux', $payload['model']);
        self::assertSame('https://cdn.example/file.png', $payload['artifacts'][0]['url']);
        self::assertSame('123', $payload['metadata']['job']);
    }

    public function test_constructor_rejects_invalid_dimensions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MediaResult('fal', 'flux', [[
            'url' => 'https://cdn.example/file.png',
            'height' => 0,
        ]]);
    }

    public function test_constructor_rejects_negativeBytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MediaResult('fal', 'flux', [[
            'url' => 'https://cdn.example/file.png',
            'bytes' => -5,
        ]]);
    }

    public function test_getters_return_artifacts_and_metadata(): void
    {
        $result = new MediaResult(
            'fal',
            'flux',
            [[
                'url' => 'https://cdn.example/file.png',
                'metadata' => ['frame' => 1],
            ]],
            metadata: ['job' => 'abc']
        );

        self::assertSame([[ 'url' => 'https://cdn.example/file.png', 'metadata' => ['frame' => 1] ]], $result->getArtifacts());
        self::assertSame(['job' => 'abc'], $result->getMetadata());
    }
}
