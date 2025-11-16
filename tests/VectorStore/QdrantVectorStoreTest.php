<?php

declare(strict_types=1);

// this_file: paragra-php/tests/VectorStore/QdrantVectorStoreTest.php

namespace ParaGra\Tests\VectorStore;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use ParaGra\Response\UnifiedResponse;
use ParaGra\VectorStore\QdrantVectorStore;
use ParaGra\VectorStore\VectorNamespace;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use const JSON_THROW_ON_ERROR;

/**
 * @covers \ParaGra\VectorStore\QdrantVectorStore
 */
final class QdrantVectorStoreTest extends TestCase
{
    public function testUpsertCreatesPointsPayload(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['result' => ['status' => 'ok']], JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client(['handler' => $handler, 'base_uri' => 'http://localhost:6333']);

        $store = new QdrantVectorStore(
            baseUrl: 'http://localhost:6333',
            collection: 'docs',
            apiKey: 'secret',
            httpClient: $client,
        );

        $records = [
            [
                'id' => 'doc-1',
                'values' => [0.1, 0.2],
                'metadata' => ['text' => 'Body', 'source' => 'kb'],
            ],
        ];

        $result = $store->upsert(new VectorNamespace('docs'), $records, ['wait_for_sync' => true]);

        self::assertSame(['upserted' => 1, 'updated' => 0], $result);
        self::assertCount(1, $history);

        $request = $history[0]['request'];
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('/collections/docs/points', $request->getUri()->getPath());
        self::assertSame('secret', $request->getHeaderLine('api-key'));

        $payload = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['wait']);
        self::assertCount(1, $payload['points']);
        self::assertSame('doc-1', $payload['points'][0]['id']);
        self::assertSame(['text' => 'Body', 'source' => 'kb'], $payload['points'][0]['payload']);
    }

    public function testDeletePostsFilterPayload(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['status' => 'ok'], JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client(['handler' => $handler, 'base_uri' => 'http://localhost:6333']);

        $store = new QdrantVectorStore(
            baseUrl: 'http://localhost:6333',
            collection: 'docs',
            apiKey: 'secret',
            httpClient: $client,
        );

        $result = $store->delete(new VectorNamespace('docs'), ['doc-1', 'doc-2']);

        self::assertSame(['deleted' => 2], $result);
        self::assertCount(1, $history);

        $payload = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['doc-1', 'doc-2'], $payload['points']);
    }

    public function testQueryBuildsUnifiedResponse(): void
    {
        $qdrantResponse = [
            'result' => [
                [
                    'id' => 'doc-1',
                    'score' => 0.88,
                    'payload' => ['text' => 'Chunk text', 'url' => 'https://example.test'],
                ],
                [
                    'id' => 'doc-2',
                    'score' => 0.51,
                    'payload' => ['content' => 'Second record', 'title' => 'Doc 2'],
                ],
            ],
        ];

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode($qdrantResponse, JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client(['handler' => $handler, 'base_uri' => 'http://localhost:6333']);

        $store = new QdrantVectorStore(
            baseUrl: 'http://localhost:6333',
            collection: 'docs',
            apiKey: null,
            httpClient: $client,
        );

        $vector = [0.1, 0.2, 0.3];
        $response = $store->query(new VectorNamespace('docs'), $vector, ['top_k' => 5]);

        self::assertInstanceOf(UnifiedResponse::class, $response);
        self::assertSame('qdrant', $response->getProvider());
        self::assertSame('docs', $response->getModel());

        $chunks = $response->getChunks();
        self::assertCount(2, $chunks);
        self::assertSame('Chunk text', $chunks[0]['text']);
        self::assertSame(0.88, $chunks[0]['score']);
        self::assertSame(['url' => 'https://example.test'], $chunks[0]['metadata']);

        self::assertSame('Second record', $chunks[1]['text']);
        self::assertSame('Doc 2', $chunks[1]['document_name']);

        $payload = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($vector, $payload['vector']);
        self::assertSame(5, $payload['limit']);
        self::assertTrue($payload['with_payload']);
        self::assertFalse($payload['with_vector']);
    }

    public function testQueryAppliesNamespaceMetadataFilter(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['result' => []], JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client(['handler' => $handler, 'base_uri' => 'http://localhost:6333']);

        $store = new QdrantVectorStore(
            baseUrl: 'http://localhost:6333',
            collection: 'docs',
            apiKey: null,
            httpClient: $client,
        );

        $namespace = new VectorNamespace('docs', metadata: [
            'labels' => ['kb', 'manual'],
            'priority' => 2,
        ]);

        $store->query($namespace, [0.5], ['top_k' => 1]);

        $payload = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([
            'must' => [
                ['key' => 'labels', 'match' => ['any' => ['kb', 'manual']]],
                ['key' => 'priority', 'match' => ['value' => 2]],
            ],
        ], $payload['filter']);
    }

    public function testQueryIncludesVectorsWhenRequested(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['result' => []], JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client(['handler' => $handler, 'base_uri' => 'http://localhost:6333']);

        $store = new QdrantVectorStore(
            baseUrl: 'http://localhost:6333',
            collection: 'docs',
            httpClient: $client,
        );

        $store->query(new VectorNamespace('docs'), [0.2], ['include_vectors' => true]);

        $payload = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['with_vector']);
    }

    public function testConstructorRejectsEmptyBaseUrl(): void
    {
        $this->expectException(RuntimeException::class);
        new QdrantVectorStore(baseUrl: '   ', collection: 'docs');
    }
}
