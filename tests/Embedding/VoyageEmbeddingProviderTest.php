<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Embedding/VoyageEmbeddingProviderTest.php

namespace ParaGra\Tests\Embedding;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use ParaGra\Embedding\EmbeddingRequest;
use ParaGra\Embedding\VoyageEmbeddingConfig;
use ParaGra\Embedding\VoyageEmbeddingProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class VoyageEmbeddingProviderTest extends TestCase
{
    public function test_embed_sends_payload_and_returns_vectors(): void
    {
        $config = new VoyageEmbeddingConfig(
            apiKey: 'vk',
            model: 'voyage-3',
            baseUri: 'https://api.voyageai.com',
            endpoint: '/v1/embeddings',
            maxBatchSize: 4,
            timeout: 12,
            inputType: 'query',
            truncate: false,
            encodingFormat: 'float',
            defaultDimensions: 1024,
        );

        $request = new EmbeddingRequest(
            inputs: [
                ['id' => 'a', 'text' => ' First ', 'metadata' => ['lang' => 'en']],
                ['id' => 'b', 'text' => 'Second'],
            ],
            normalize: true,
        );

        $responseBody = json_encode([
            'data' => [
                ['embedding' => [3.0, 4.0], 'index' => 0],
                ['embedding' => [0.0, 0.0], 'index' => 1],
            ],
            'model' => 'voyage-3',
            'usage' => ['prompt_tokens' => 64],
        ], JSON_THROW_ON_ERROR);

        $response = new Response(200, [], $responseBody);

        /** @var ClientInterface&MockObject $client */
        $client = $this->createMock(ClientInterface::class);
        $client
            ->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'https://api.voyageai.com/v1/embeddings',
                $this->callback(function (array $options): bool {
                    self::assertSame('Bearer vk', $options['headers']['Authorization']);
                    self::assertSame('application/json', $options['headers']['Content-Type']);
                    self::assertSame(12, $options['timeout']);
                    self::assertSame('voyage-3', $options['json']['model']);
                    self::assertSame(['First', 'Second'], $options['json']['input']);
                    self::assertSame('query', $options['json']['input_type']);
                    self::assertFalse($options['json']['truncate']);
                    self::assertSame('float', $options['json']['encoding_format']);
                    self::assertSame(1024, $options['json']['output_dimension']);

                    return true;
                })
            )
            ->willReturn($response);

        $provider = new VoyageEmbeddingProvider($config, $client);
        $result = $provider->embed($request);

        self::assertSame('voyage', $result['provider']);
        self::assertSame('voyage-3', $result['model']);
        self::assertSame(2, $result['dimensions']);
        self::assertCount(2, $result['vectors']);
        self::assertSame('a', $result['vectors'][0]['id']);
        self::assertEqualsWithDelta([0.6, 0.8], $result['vectors'][0]['values'], 1e-9);
        self::assertSame(['lang' => 'en'], $result['vectors'][0]['metadata']);
        self::assertSame(['prompt_tokens' => 64], $result['usage']);
    }

    public function test_embed_supports_embeddings_key_shape(): void
    {
        $config = new VoyageEmbeddingConfig(
            apiKey: 'vk',
            model: 'voyage-3-lite',
            defaultDimensions: 512,
        );

        $request = new EmbeddingRequest(['text'], normalize: false);

        $response = new Response(200, [], json_encode([
            'embeddings' => [
                [1, 2],
            ],
        ], JSON_THROW_ON_ERROR));

        $client = $this->createMock(ClientInterface::class);
        $client
            ->expects(self::once())
            ->method('request')
            ->willReturn($response);

        $provider = new VoyageEmbeddingProvider($config, $client);
        $result = $provider->embed($request);

        self::assertSame(2, $result['dimensions']);
        self::assertSame([[ 'id' => null, 'values' => [1.0, 2.0], 'metadata' => null ]], $result['vectors']);
    }

    public function test_embed_enforces_batch_limit(): void
    {
        $config = new VoyageEmbeddingConfig(
            apiKey: 'vk',
            model: 'voyage-3',
            maxBatchSize: 1,
        );

        $provider = new VoyageEmbeddingProvider($config, $this->createMock(ClientInterface::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('batch');

        $provider->embed(new EmbeddingRequest(['one', 'two']));
    }

    public function test_embed_wraps_http_errors(): void
    {
        $config = new VoyageEmbeddingConfig(apiKey: 'vk', model: 'voyage-3');

        $client = $this->createMock(ClientInterface::class);
        $client
            ->expects(self::once())
            ->method('request')
            ->willThrowException(new RequestException('fail', new Request('POST', 'https://api.voyageai.com/v1/embeddings')));

        $provider = new VoyageEmbeddingProvider($config, $client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Voyage embeddings request failed');

        $provider->embed(new EmbeddingRequest(['only']));
    }

    public function test_embed_throws_when_response_missing_vectors(): void
    {
        $config = new VoyageEmbeddingConfig(apiKey: 'vk', model: 'voyage-3');

        $response = new Response(200, [], json_encode(['data' => []], JSON_THROW_ON_ERROR));

        $client = $this->createMock(ClientInterface::class);
        $client
            ->expects(self::once())
            ->method('request')
            ->willReturn($response);

        $provider = new VoyageEmbeddingProvider($config, $client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Voyage embeddings response missing vectors');

        $provider->embed(new EmbeddingRequest(['text']));
    }

    public function test_metadata_helpers(): void
    {
        $config = new VoyageEmbeddingConfig(
            apiKey: 'vk',
            model: 'voyage-3-large',
            maxBatchSize: 32,
            defaultDimensions: 2048,
        );

        $provider = new VoyageEmbeddingProvider($config, $this->createMock(ClientInterface::class));
        $dimensions = $provider->getSupportedDimensions();

        self::assertSame('voyage', $provider->getProvider());
        self::assertSame('voyage-3-large', $provider->getModel());
        self::assertContains(2048, $dimensions);
        self::assertContains(1024, $dimensions);
        self::assertContains(512, $dimensions);
        self::assertSame(32, $provider->getMaxBatchSize());
    }
}
