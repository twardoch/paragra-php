<?php

declare(strict_types=1);

// this_file: paragra-php/tests/VectorStore/PineconeVectorStoreTest.php

namespace ParaGra\Tests\VectorStore;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use ParaGra\VectorStore\PineconeVectorStore;
use ParaGra\VectorStore\VectorNamespace;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use const JSON_THROW_ON_ERROR;

/**
 * @covers \ParaGra\VectorStore\PineconeVectorStore
 */
final class PineconeVectorStoreTest extends TestCase
{
    public function testUpsertSendsVectorsAndReturnsCounts(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['upsertedCount' => 2], JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client(['handler' => $handler, 'base_uri' => 'https://example.pinecone.io']);

        $store = new PineconeVectorStore(
            baseUrl: 'https://example.pinecone.io',
            apiKey: 'test-key',
            indexName: 'demo-index',
            defaultNamespace: new VectorNamespace('docs'),
            apiVersion: '2024-07',
            httpClient: $client,
        );

        $records = [
            [
                'id' => 'doc-1',
                'values' => [0.1, 0.2],
                'metadata' => ['text' => 'Chunk one', 'source' => 'kb'],
            ],
            [
                'id' => 'doc-2',
                'values' => [0.3, 0.4],
                'metadata' => ['text' => 'Chunk two'],
            ],
        ];

        $result = $store->upsert(new VectorNamespace('docs'), $records);

        self::assertSame(['upserted' => 2, 'updated' => 0], $result);
        self::assertCount(1, $history);
        $request = $history[0]['request'];

        self::assertSame('POST', $request->getMethod());
        self::assertSame('/vectors/upsert', $request->getUri()->getPath());
        self::assertSame('test-key', $request->getHeaderLine('Api-Key'));
        self::assertSame('2024-07', $request->getHeaderLine('X-Pinecone-API-Version'));

        $payload = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('docs', $payload['namespace']);
        self::assertCount(2, $payload['vectors']);
        self::assertSame('doc-1', $payload['vectors'][0]['id']);
        self::assertSame([0.1, 0.2], $payload['vectors'][0]['values']);
        self::assertSame(['text' => 'Chunk one', 'source' => 'kb'], $payload['vectors'][0]['metadata']);
    }

    public function testDeleteByIds(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['deletedCount' => 3], JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client(['handler' => $handler, 'base_uri' => 'https://example.pinecone.io']);

        $store = new PineconeVectorStore(
            baseUrl: 'https://example.pinecone.io',
            apiKey: 'test-key',
            indexName: 'demo-index',
            defaultNamespace: new VectorNamespace('docs'),
            httpClient: $client,
        );

        $result = $store->delete(new VectorNamespace('docs'), ['doc-1', 'doc-2']);

        self::assertSame(['deleted' => 3], $result);
        self::assertCount(1, $history);

        $payload = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['doc-1', 'doc-2'], $payload['ids']);
        self::assertSame('docs', $payload['namespace']);
    }

    public function testQueryConvertsMatchesToUnifiedResponse(): void
    {
        $response = [
            'namespace' => 'docs',
            'matches' => [
                [
                    'id' => 'doc-1',
                    'score' => 0.92,
                    'metadata' => [
                        'text' => 'First chunk',
                        'source' => 'kb',
                        'url' => 'https://example.test/doc-1',
                    ],
                ],
                [
                    'id' => 'doc-2',
                    'score' => 0.61,
                    'metadata' => [
                        'content' => 'Second chunk body',
                        'title' => 'Doc 2',
                    ],
                ],
            ],
        ];

        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode($response, JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client(['handler' => $handler, 'base_uri' => 'https://example.pinecone.io']);

        $store = new PineconeVectorStore(
            baseUrl: 'https://example.pinecone.io',
            apiKey: 'test-key',
            indexName: 'demo-index',
            defaultNamespace: new VectorNamespace('docs'),
            httpClient: $client,
        );

        $vector = [0.1, 0.2, 0.3];
        $result = $store->query(new VectorNamespace('docs'), $vector, ['top_k' => 2]);

        self::assertSame('pinecone', $result->getProvider());
        self::assertSame('demo-index', $result->getModel());
        self::assertCount(2, $result->getChunks());

        $chunks = $result->getChunks();
        self::assertSame('First chunk', $chunks[0]['text']);
        self::assertSame(0.92, $chunks[0]['score']);
        self::assertSame('doc-1', $chunks[0]['document_id']);
        self::assertSame(['source' => 'kb', 'url' => 'https://example.test/doc-1'], $chunks[0]['metadata']);

        self::assertSame('Second chunk body', $chunks[1]['text']);
        self::assertSame('doc-2', $chunks[1]['document_id']);
        self::assertSame('Doc 2', $chunks[1]['document_name']);

        self::assertCount(1, $history);
        $payload = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([0.1, 0.2, 0.3], $payload['vector']);
        self::assertSame(2, $payload['topK']);
        self::assertTrue($payload['includeMetadata']);
    }

    public function testQueryAppliesMetadataFilterAndIncludesVectors(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['matches' => []], JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client(['handler' => $handler, 'base_uri' => 'https://example.pinecone.io']);

        $metadataNamespace = new VectorNamespace('docs', metadata: ['labels' => ['kb'], 'priority' => 2]);
        $store = new PineconeVectorStore(
            baseUrl: 'https://example.pinecone.io',
            apiKey: 'test-key',
            indexName: 'demo-index',
            defaultNamespace: $metadataNamespace,
            httpClient: $client,
        );

        $store->query($metadataNamespace, [0.5], ['include_vectors' => true]);

        $payload = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['includeValues']);
        self::assertSame(['labels' => ['$in' => ['kb']], 'priority' => ['$eq' => 2]], $payload['filter']);
    }

    public function testConstructorRejectsEmptyBaseUrl(): void
    {
        $this->expectException(RuntimeException::class);
        new PineconeVectorStore(baseUrl: '  ', apiKey: 'key', indexName: 'idx');
    }
}
