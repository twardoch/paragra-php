<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Embedding/OpenAiEmbeddingProviderTest.php

namespace ParaGra\Tests\Embedding;

use InvalidArgumentException;
use OpenAI\Contracts\ClientContract as OpenAiClient;
use OpenAI\Contracts\Resources\EmbeddingsContract as OpenAiEmbeddingsResource;
use OpenAI\Responses\Embeddings\CreateResponse;
use OpenAI\Responses\Meta\MetaInformation;
use ParaGra\Embedding\EmbeddingRequest;
use ParaGra\Embedding\OpenAiEmbeddingConfig;
use ParaGra\Embedding\OpenAiEmbeddingProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OpenAiEmbeddingProviderTest extends TestCase
{
    public function test_embed_sends_payload_and_returns_normalized_vectors(): void
    {
        $config = new OpenAiEmbeddingConfig(
            apiKey: 'test-key',
            model: 'text-embedding-3-small',
            baseUrl: null,
            maxBatchSize: 5,
            defaultDimensions: 768,
        );

        $request = new EmbeddingRequest(
            inputs: [
                [
                    'id' => 'doc-1',
                    'text' => ' First chunk ',
                    'metadata' => ['source' => 'kb'],
                ],
                'Second chunk',
            ],
            normalize: true,
        );

        $response = $this->fakeEmbeddingsResponse([
            [3.0, 4.0],
            [1.0, 0.0],
        ]);

        [$provider, $resource] = $this->makeProvider($config);
        $resource
            ->expects(self::once())
            ->method('create')
            ->with($this->callback(function (array $payload): bool {
                self::assertSame('text-embedding-3-small', $payload['model']);
                self::assertSame(['First chunk', 'Second chunk'], $payload['input']);
                self::assertSame(768, $payload['dimensions']);

                return true;
            }))
            ->willReturn($response);

        $result = $provider->embed($request);

        self::assertSame('openai', $result['provider']);
        self::assertSame('text-embedding-3-small', $result['model']);
        self::assertSame(2, $result['dimensions']);
        self::assertCount(2, $result['vectors']);
        self::assertSame('doc-1', $result['vectors'][0]['id']);
        self::assertEqualsWithDelta([0.6, 0.8], $result['vectors'][0]['values'], 1e-9);
        self::assertSame(['source' => 'kb'], $result['vectors'][0]['metadata']);
        self::assertNull($result['vectors'][1]['id']);
        self::assertSame([1.0, 0.0], $result['vectors'][1]['values']);
        self::assertSame(
            ['prompt_tokens' => 42, 'total_tokens' => 84],
            $result['usage'],
        );
    }

    public function test_embed_respects_request_dimensions_and_skip_normalization(): void
    {
        $config = new OpenAiEmbeddingConfig(
            apiKey: 'test',
            model: 'text-embedding-3-large',
            baseUrl: null,
            maxBatchSize: 3,
            defaultDimensions: null,
        );

        $request = new EmbeddingRequest(
            inputs: ['alpha', 'beta'],
            dimensions: 512,
            normalize: false,
        );

        $response = $this->fakeEmbeddingsResponse([
            [0.25, 0.25, 0.25, 0.25],
            [0.5, 0.5, 0.5, 0.5],
        ]);

        [$provider, $resource] = $this->makeProvider($config);
        $resource
            ->expects(self::once())
            ->method('create')
            ->with($this->callback(function (array $payload): bool {
                self::assertSame(512, $payload['dimensions'], 'Request dimensions should override defaults.');

                return true;
            }))
            ->willReturn($response);

        $result = $provider->embed($request);

        self::assertSame([0.25, 0.25, 0.25, 0.25], $result['vectors'][0]['values']);
        self::assertSame([0.5, 0.5, 0.5, 0.5], $result['vectors'][1]['values']);
    }

    public function test_embed_throws_when_batch_exceeds_limit(): void
    {
        $config = new OpenAiEmbeddingConfig(
            apiKey: 'test',
            model: 'text-embedding-3-small',
            baseUrl: null,
            maxBatchSize: 1,
            defaultDimensions: 1536,
        );

        $request = new EmbeddingRequest(['one', 'two']);

        $provider = new OpenAiEmbeddingProvider($config, $this->createMock(OpenAiClient::class));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds the configured batch limit');

        $provider->embed($request);
    }

    public function test_embed_wraps_client_errors(): void
    {
        $config = new OpenAiEmbeddingConfig(
            apiKey: 'test',
            model: 'text-embedding-3-small',
            baseUrl: null,
            maxBatchSize: 2,
            defaultDimensions: 1536,
        );

        $request = new EmbeddingRequest(['single']);

        /** @var OpenAiEmbeddingsResource&MockObject $resource */
        $resource = $this->createMock(OpenAiEmbeddingsResource::class);
        $resource
            ->expects(self::once())
            ->method('create')
            ->willThrowException(new \RuntimeException('boom'));

        /** @var OpenAiClient&MockObject $client */
        $client = $this->createMock(OpenAiClient::class);
        $client
            ->expects(self::once())
            ->method('embeddings')
            ->willReturn($resource);

        $provider = new OpenAiEmbeddingProvider($config, $client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to generate embeddings');

        $provider->embed($request);
    }

    public function test_metadata_methods(): void
    {
        $config = new OpenAiEmbeddingConfig(
            apiKey: 'test',
            model: 'text-embedding-3-small',
            baseUrl: null,
            maxBatchSize: 7,
            defaultDimensions: 1024,
        );

        $provider = new OpenAiEmbeddingProvider($config, $this->createMock(OpenAiClient::class));

        $dimensions = $provider->getSupportedDimensions();

        self::assertSame('openai', $provider->getProvider());
        self::assertSame('text-embedding-3-small', $provider->getModel());
        self::assertContains(1024, $dimensions, 'Config default should appear in supported dims.');
        self::assertContains(1536, $dimensions, 'Known OpenAI dimension should be exposed.');
        self::assertSame(7, $provider->getMaxBatchSize());
    }

    /**
     * @return array{0: OpenAiEmbeddingProvider, 1: OpenAiEmbeddingsResource&MockObject}
     */
    private function makeProvider(OpenAiEmbeddingConfig $config): array
    {
        /** @var OpenAiEmbeddingsResource&MockObject $resource */
        $resource = $this->createMock(OpenAiEmbeddingsResource::class);

        /** @var OpenAiClient&MockObject $client */
        $client = $this->createMock(OpenAiClient::class);
        $client
            ->expects(self::once())
            ->method('embeddings')
            ->willReturn($resource);

        return [new OpenAiEmbeddingProvider($config, $client), $resource];
    }

    /**
     * @param list<list<float>> $vectors
     */
    private function fakeEmbeddingsResponse(array $vectors): CreateResponse
    {
        $data = [];
        foreach ($vectors as $index => $values) {
            $data[] = [
                'object' => 'embedding',
                'index' => $index,
                'embedding' => $values,
            ];
        }

        return CreateResponse::from([
            'object' => 'list',
            'model' => 'text-embedding-3-small',
            'data' => $data,
            'usage' => [
                'prompt_tokens' => 42,
                'total_tokens' => 84,
            ],
        ], MetaInformation::from([]));
    }
}
