<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Embedding/EmbeddingRequestTest.php

namespace ParaGra\Tests\Embedding;

use InvalidArgumentException;
use ParaGra\Embedding\EmbeddingRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmbeddingRequest::class)]
final class EmbeddingRequestTest extends TestCase
{
    public function test_it_normalizes_inputs_and_metadata(): void
    {
        $request = new EmbeddingRequest(
            inputs: [
                '  first chunk  ',
                [
                    'id' => ' doc-2 ',
                    'text' => ' second chunk ',
                    'metadata' => [
                        'source' => 'docs',
                        'lang' => 'en',
                        'score' => 0.92,
                        'tags' => ['faq', 'intro'],
                    ],
                ],
            ],
            dimensions: 1536,
            normalize: false,
            metadataFilter: [
                'source' => ['docs', 'kb'],
                'lang' => 'en',
            ],
        );

        self::assertSame(2, $request->getBatchSize());
        self::assertSame(1536, $request->getDimensions());
        self::assertFalse($request->shouldNormalize());
        self::assertSame(
            [
                [
                    'id' => null,
                    'text' => 'first chunk',
                    'metadata' => null,
                ],
                [
                    'id' => 'doc-2',
                    'text' => 'second chunk',
                    'metadata' => [
                        'source' => 'docs',
                        'lang' => 'en',
                        'score' => 0.92,
                        'tags' => ['faq', 'intro'],
                    ],
                ],
            ],
            $request->getInputs(),
        );
        self::assertSame(
            [
                'source' => ['docs', 'kb'],
                'lang' => 'en',
            ],
            $request->getMetadataFilter(),
        );
    }

    public function test_it_rejects_empty_batches(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('EmbeddingRequest requires at least one input.');

        new EmbeddingRequest([]);
    }

    public function test_it_rejects_empty_text_payload(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Embedding input 0 must include non-empty text.');

        new EmbeddingRequest(['   ']);
    }

    public function test_it_rejects_non_string_inputs(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Embedding inputs must be strings or arrays.');

        new EmbeddingRequest([42]);
    }

    public function test_it_rejects_invalid_metadata_filter_types(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('metadata filter');

        new EmbeddingRequest(
            inputs: ['one'],
            metadataFilter: ['source' => ['docs' => 'kb']]
        );
    }

    public function test_to_array_exposes_full_payload(): void
    {
        $request = new EmbeddingRequest(['alpha'], metadataFilter: ['lang' => 'en']);

        self::assertSame(
            [
                'inputs' => [
                    [
                        'id' => null,
                        'text' => 'alpha',
                        'metadata' => null,
                    ],
                ],
                'dimensions' => null,
                'normalize' => true,
                'metadata_filter' => ['lang' => 'en'],
            ],
            $request->toArray(),
        );
    }

    public function test_dimensions_must_be_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dimensions must be positive');

        new EmbeddingRequest(inputs: ['text'], dimensions: 0);
    }
}
