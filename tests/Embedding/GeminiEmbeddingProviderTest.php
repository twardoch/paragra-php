<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Embedding/GeminiEmbeddingProviderTest.php

namespace ParaGra\Tests\Embedding;

use Gemini\Contracts\ClientContract as GeminiClient;
use Gemini\Contracts\Resources\EmbeddingModalContract;
use Gemini\Enums\TaskType;
use Gemini\Requests\GenerativeModel\EmbedContentRequest;
use Gemini\Responses\GenerativeModel\BatchEmbedContentsResponse;
use ParaGra\Embedding\EmbeddingRequest;
use ParaGra\Embedding\GeminiEmbeddingConfig;
use ParaGra\Embedding\GeminiEmbeddingProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GeminiEmbeddingProviderTest extends TestCase
{
    public function test_embed_sends_requests_and_normalizes_vectors(): void
    {
        $config = new GeminiEmbeddingConfig(
            apiKey: 'test',
            model: 'text-embedding-004',
            maxBatchSize: 5,
            baseUrl: null,
            taskType: TaskType::RETRIEVAL_DOCUMENT,
            title: 'ParaGra embeddings',
            defaultDimensions: 1536,
        );

        $request = new EmbeddingRequest(
            inputs: [
                ['id' => 'doc-1', 'text' => ' First chunk ', 'metadata' => ['source' => 'kb']],
                'Second chunk',
            ],
            dimensions: 512,
            normalize: true,
        );

        $response = BatchEmbedContentsResponse::from([
            'embeddings' => [
                ['values' => [3.0, 4.0]],
                ['values' => [0.0, 1.0]],
            ],
        ]);

        [$provider, $resource] = $this->makeProvider($config);
        $capturedBodies = [];
        $resource
            ->expects(self::once())
            ->method('batchEmbedContents')
            ->with(
                self::callback(function (EmbedContentRequest $req) use (&$capturedBodies): bool {
                    $capturedBodies[] = $req->body();
                    return true;
                }),
                self::callback(function (EmbedContentRequest $req) use (&$capturedBodies): bool {
                    $capturedBodies[] = $req->body();
                    return true;
                })
            )
            ->willReturn($response);

        $result = $provider->embed($request);

        self::assertSame('gemini', $result['provider']);
        self::assertSame('text-embedding-004', $result['model']);
        self::assertSame(2, $result['dimensions']);
        self::assertCount(2, $result['vectors']);
        self::assertSame('doc-1', $result['vectors'][0]['id']);
        self::assertEqualsWithDelta([0.6, 0.8], $result['vectors'][0]['values'], 1e-9);
        self::assertSame(['source' => 'kb'], $result['vectors'][0]['metadata']);
        self::assertNull($result['vectors'][1]['id']);
        self::assertEqualsWithDelta([0.0, 1.0], $result['vectors'][1]['values'], 1e-9);
        self::assertNull($result['usage']);

        self::assertCount(2, $capturedBodies);
        self::assertSame('RETRIEVAL_DOCUMENT', $capturedBodies[0]['taskType']);
        self::assertSame(512, $capturedBodies[0]['outputDimensionality']);
        self::assertSame('ParaGra embeddings', $capturedBodies[0]['title']);
        self::assertSame('Second chunk', $capturedBodies[1]['content']['parts'][0]['text']);
    }

    public function test_embed_disallows_dimension_override_for_fixed_models(): void
    {
        $config = new GeminiEmbeddingConfig(
            apiKey: 'key',
            model: 'embedding-001',
            maxBatchSize: 2,
            defaultDimensions: 3072,
        );

        $provider = new GeminiEmbeddingProvider($config, $this->createMock(GeminiClient::class));
        $request = new EmbeddingRequest(['alpha'], dimensions: 1024);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not support overriding dimensions');

        $provider->embed($request);
    }

    public function test_embed_throws_when_batch_exceeds_limit(): void
    {
        $config = new GeminiEmbeddingConfig(
            apiKey: 'key',
            model: 'text-embedding-004',
            maxBatchSize: 1,
        );

        $provider = new GeminiEmbeddingProvider($config, $this->createMock(GeminiClient::class));
        $request = new EmbeddingRequest(['one', 'two']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('batch exceeds the configured batch limit');

        $provider->embed($request);
    }

    public function test_embed_wraps_gemini_errors(): void
    {
        $config = new GeminiEmbeddingConfig(
            apiKey: 'key',
            model: 'text-embedding-004',
            maxBatchSize: 2,
        );

        /** @var EmbeddingModalContract&MockObject $resource */
        $resource = $this->createMock(EmbeddingModalContract::class);
        $resource
            ->expects(self::once())
            ->method('batchEmbedContents')
            ->willThrowException(new \RuntimeException('boom'));

        /** @var GeminiClient&MockObject $client */
        $client = $this->createMock(GeminiClient::class);
        $client
            ->method('embeddingModel')
            ->willReturn($resource);

        $provider = new GeminiEmbeddingProvider($config, $client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Gemini embeddings request failed');

        $provider->embed(new EmbeddingRequest(['chunk']));
    }

    public function test_metadata_helpers(): void
    {
        $config = new GeminiEmbeddingConfig(
            apiKey: 'key',
            model: 'text-embedding-004',
            maxBatchSize: 3,
            defaultDimensions: 768,
        );

        $provider = new GeminiEmbeddingProvider($config, $this->createMock(GeminiClient::class));
        $dimensions = $provider->getSupportedDimensions();

        self::assertSame('gemini', $provider->getProvider());
        self::assertSame('text-embedding-004', $provider->getModel());
        self::assertContains(768, $dimensions);
        self::assertSame(3, $provider->getMaxBatchSize());
    }

    /**
     * @return array{
     *     0: GeminiEmbeddingProvider,
     *     1: EmbeddingModalContract&MockObject
     * }
     */
    private function makeProvider(GeminiEmbeddingConfig $config): array
    {
        /** @var EmbeddingModalContract&MockObject $resource */
        $resource = $this->createMock(EmbeddingModalContract::class);

        /** @var GeminiClient&MockObject $client */
        $client = $this->createMock(GeminiClient::class);
        $client
            ->expects(self::once())
            ->method('embeddingModel')
            ->with($config->model)
            ->willReturn($resource);

        return [new GeminiEmbeddingProvider($config, $client), $resource];
    }
}
