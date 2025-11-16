<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Media/MediaRequestTest.php

namespace ParaGra\Tests\Media;

use InvalidArgumentException;
use ParaGra\Media\MediaRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MediaRequest::class)]
final class MediaRequestTest extends TestCase
{
    public function test_resolveDimensions_whenAspectRatioProvided_thenComputesHeight(): void
    {
        $request = new MediaRequest(
            prompt: 'Bright cover art',
            aspectRatio: '16:9',
            width: 1920,
            metadata: ['style' => 'cinematic']
        );

        self::assertSame(
            ['width' => 1920, 'height' => 1080],
            $request->resolveDimensions()
        );
        self::assertSame('16:9', $request->getAspectRatio());
        self::assertSame(['style' => 'cinematic'], $request->getMetadata());
    }

    public function test_resolveDimensions_whenOnlyAspectRatio_thenUsesDefaults(): void
    {
        $request = new MediaRequest('Square crop', aspectRatio: '1:1');
        $result = $request->resolveDimensions(768, 512);

        self::assertSame(768, $result['width']);
        self::assertSame(768, $result['height']);
    }

    public function test_constructor_whenPromptEmpty_thenThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MediaRequest('');
    }

    public function test_constructor_whenMetadataInvalid_thenThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        /** @phpstan-ignore-next-line */
        new MediaRequest('Prompt', metadata: [['bad']]);
    }

    public function test_toArray_whenSeedProvided_thenIncludesAllFields(): void
    {
        $request = new MediaRequest(
            prompt: 'Snowy forest',
            negativePrompt: 'blurry',
            width: 1024,
            height: 768,
            images: 2,
            seed: 1234,
            metadata: ['origin' => 'test']
        );

        $payload = $request->toArray();

        self::assertSame('Snowy forest', $payload['prompt']);
        self::assertSame(2, $payload['images']);
        self::assertSame(1234, $payload['seed']);
        self::assertSame('blurry', $payload['negative_prompt']);
    }

    public function test_resolveDimensions_whenHeightProvided_thenComputesWidth(): void
    {
        $request = new MediaRequest(
            prompt: 'Portrait',
            aspectRatio: '4:5',
            height: 1250
        );

        $result = $request->resolveDimensions();
        self::assertSame(1000, $result['width']);
        self::assertSame(1256, $result['height']);
    }

    public function test_resolveDimensions_enforcesMinimumDimension(): void
    {
        $request = new MediaRequest('Tiny', width: 10);
        $result = $request->resolveDimensions();

        self::assertSame(64, $result['width']);
    }

    public function test_constructor_rejects_invalid_aspect_ratio_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MediaRequest('Scene', aspectRatio: 'wide');
    }

    public function test_constructor_rejects_zero_aspect_ratio_component(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MediaRequest('Scene', aspectRatio: '0:4');
    }

    public function test_constructor_rejects_non_scalar_metadata_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MediaRequest('Prompt', metadata: ['tags' => ['alpha', ['beta']]]);
    }

    public function test_constructor_rejects_numeric_metadata_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MediaRequest('Prompt', metadata: [123 => 'value']);
    }

    public function test_negative_prompt_allows_empty_string(): void
    {
        $request = new MediaRequest('Prompt', negativePrompt: '   ');
        self::assertSame('', $request->getNegativePrompt());
    }

    public function test_constructor_rejects_zero_width(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MediaRequest('Prompt', width: 0);
    }

    public function test_metadata_allows_null_entries_in_list(): void
    {
        $request = new MediaRequest('Prompt', metadata: ['tags' => ['alpha', null]]);
        self::assertSame(['tags' => ['alpha', null]], $request->getMetadata());
    }

    public function test_getAspectRatioReturnsNullWhenUnset(): void
    {
        $request = new MediaRequest('Prompt');
        self::assertSame('1:1', $request->getAspectRatio());
    }
}
