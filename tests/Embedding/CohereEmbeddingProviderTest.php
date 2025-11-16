<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Embedding/CohereEmbeddingProviderTest.php

namespace ParaGra\Tests\Embedding;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use ParaGra\Embedding\CohereEmbeddingConfig;
use ParaGra\Embedding\CohereEmbeddingProvider;
use ParaGra\Embedding\EmbeddingRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CohereEmbeddingProviderTest extends TestCase
{
    public function test_embed_sends_payload_and_returns_vectors(): void
    {
        $config = new CohereEmbeddingConfig(
            apiKey: 'ckey',
            model: 'embed-english-v3.0',
            inputType: 'search_document',
            truncate: 'END',
            embeddingTypes: ['float'],
            maxBatchSize: 8,
            baseUri: 'https://api.cohere.ai',
            endpoint: '/v1/embed',
            defaultDimensions: 1024,
        );

        $request = new EmbeddingRequest(
            inputs: [
                ['id' => 'doc-1', 'text' => ' First chunk ', 'metadata' => ['source' => 'kb']],
                'Second chunk',
            ],
            normalize: true,
        );

        $responseBody = json_encode([
            'id' => 'embed-123',
            'embeddings' => [
                'float' => [
                    [3.0, 4.0],
                    [1.0, 0.0],
                ],
            ],
            'meta' => [
                'billed_units' => ['input_tokens' => 64],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = new Response(200, [], $responseBody);

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client
            ->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'https://api.cohere.ai/v1/embed',
                $this->callback(function (array $options): bool {
                    self::assertSame('Bearer ckey', $options['headers']['Authorization']);
                    self::assertSame('application/json', $options['headers']['Content-Type']);
                    self::assertSame('application/json', $options['headers']['Accept']);
                    self::assertSame([
                        'model' => 'embed-english-v3.0',
                        'texts' => ['First chunk', 'Second chunk'],
                        'input_type' => 'search_document',
                        'truncate' => 'END',
                        'embedding_types' => ['float'],
                    ], $options['json']);

                    return true;
                })
            )
            ->willReturn($response);

        $provider = new CohereEmbeddingProvider($config, $client);
        $result = $provider->embed($request);

        self::assertSame('cohere', $result['provider']);
        self::assertSame('embed-english-v3.0', $result['model']);
        self::assertSame(2, $result['dimensions']);
        self::assertCount(2, $result['vectors']);
        self::assertSame('doc-1', $result['vectors'][0]['id']);
        self::assertEqualsWithDelta([0.6, 0.8], $result['vectors'][0]['values'], 1e-9);
        self::assertSame(['source' => 'kb'], $result['vectors'][0]['metadata']);
        self::assertSame(['input_tokens' => 64], $result['usage']);
    }

    public function test_embed_throws_when_batch_limit_exceeded(): void
    {
        $config = new CohereEmbeddingConfig(
            apiKey: 'ckey',
            model: 'embed-english-v3.0',
            maxBatchSize: 1,
            embeddingTypes: ['float'],
            inputType: 'search_document',
        );

        $provider = new CohereEmbeddingProvider($config, $this->createMock(ClientInterface::class));

        $request = new EmbeddingRequest(['one', 'two']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('batch limit');

        $provider->embed($request);
    }

    public function test_embed_rejects_dimension_override(): void
    {
        $config = new CohereEmbeddingConfig(
            apiKey: 'ckey',
            model: 'embed-english-v3.0',
            embeddingTypes: ['float'],
            inputType: 'search_document',
        );

        $request = new EmbeddingRequest(['text'], dimensions: 123);

        $provider = new CohereEmbeddingProvider($config, $this->createMock(ClientInterface::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('dimensions');

        $provider->embed($request);
    }

    public function test_embed_wraps_http_errors(): void
    {
        $config = new CohereEmbeddingConfig(
            apiKey: 'ckey',
            model: 'embed-english-v3.0',
            embeddingTypes: ['float'],
            inputType: 'search_document',
        );

        $client = $this->createMock(ClientInterface::class);
        $client
            ->expects(self::once())
            ->method('request')
            ->willThrowException(new RequestException('boom', new Request('POST', 'https://api.cohere.ai/v1/embed')));

        $provider = new CohereEmbeddingProvider($config, $client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cohere embeddings request failed');

        $provider->embed(new EmbeddingRequest(['text']));
    }

    public function test_metadata_methods(): void
    {
        $config = new CohereEmbeddingConfig(
            apiKey: 'ckey',
            model: 'embed-multilingual-light-v3.0',
            embeddingTypes: ['float'],
            inputType: 'search_document',
            maxBatchSize: 4,
            defaultDimensions: 384,
        );

        $provider = new CohereEmbeddingProvider($config, $this->createMock(ClientInterface::class));

        $dimensions = $provider->getSupportedDimensions();

        self::assertSame('cohere', $provider->getProvider());
        self::assertSame('embed-multilingual-light-v3.0', $provider->getModel());
        self::assertContains(384, $dimensions);
        self::assertContains(1024, $dimensions);
        self::assertSame(4, $provider->getMaxBatchSize());
    }
}
