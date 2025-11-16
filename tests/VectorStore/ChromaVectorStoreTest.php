<?php

declare(strict_types=1);

// this_file: paragra-php/tests/VectorStore/ChromaVectorStoreTest.php

namespace ParaGra\Tests\VectorStore;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use ParaGra\VectorStore\ChromaVectorStore;
use ParaGra\VectorStore\VectorNamespace;
use PHPUnit\Framework\TestCase;

use const JSON_THROW_ON_ERROR;

/**
 * @covers \ParaGra\VectorStore\ChromaVectorStore
 */
final class ChromaVectorStoreTest extends TestCase
{
    public function testUpsertSendsRecordsToCollection(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['ids' => ['doc-1', 'doc-2']], JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client([
            'handler' => $handler,
            'base_uri' => 'http://localhost:8000/api/v2/',
        ]);

        $store = new ChromaVectorStore(
            baseUrl: 'http://localhost:8000',
            tenant: 'default',
            database: 'db',
            collection: 'kb',
            defaultNamespace: new VectorNamespace('kb', 'kb'),
            authToken: 'secret',
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

        $namespace = new VectorNamespace('kb', 'kb');
        $result = $store->upsert($namespace, $records);

        self::assertSame(['upserted' => 2, 'updated' => 0], $result);
        self::assertCount(1, $history);

        $request = $history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            '/api/v2/tenants/default/databases/db/collections/kb/upsert',
            $request->getUri()->getPath()
        );
        self::assertSame('Bearer secret', $request->getHeaderLine('Authorization'));

        $payload = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['doc-1', 'doc-2'], $payload['ids']);
        self::assertSame([[0.1, 0.2], [0.3, 0.4]], $payload['embeddings']);
        self::assertSame(
            [
                ['text' => 'Chunk one', 'source' => 'kb'],
                ['text' => 'Chunk two'],
            ],
            $payload['metadatas']
        );
        self::assertSame(['Chunk one', 'Chunk two'], $payload['documents']);
    }

    public function testDeleteRemovesIdsFromCollection(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['ids' => ['doc-1']], JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client([
            'handler' => $handler,
            'base_uri' => 'http://localhost:8000/api/v2/',
        ]);

        $store = new ChromaVectorStore(
            baseUrl: 'http://localhost:8000',
            tenant: 'default',
            database: 'db',
            collection: 'kb',
            httpClient: $client,
        );

        $namespace = new VectorNamespace('kb', 'kb');
        $result = $store->delete($namespace, ['doc-1', 'doc-2']);

        self::assertSame(['deleted' => 2], $result);
        self::assertCount(1, $history);

        $request = $history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            '/api/v2/tenants/default/databases/db/collections/kb/delete',
            $request->getUri()->getPath()
        );

        $payload = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['doc-1', 'doc-2'], $payload['ids']);
    }

    public function testQueryNormalizesResponse(): void
    {
        $history = [];
        $response = [
            'ids' => [['doc-1', 'doc-2']],
            'distances' => [[0.15, 0.75]],
            'documents' => [['Document body one', '']],
            'metadatas' => [[
                ['title' => 'Doc One', 'url' => 'https://example.test/doc-1'],
                ['content' => 'Fallback chunk body', 'source' => 'import'],
            ]],
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($response, JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client([
            'handler' => $handler,
            'base_uri' => 'http://localhost:8000/api/v2/',
        ]);

        $store = new ChromaVectorStore(
            baseUrl: 'http://localhost:8000',
            tenant: 'default',
            database: 'db',
            collection: 'kb',
            httpClient: $client,
        );

        $namespace = new VectorNamespace('kb', 'kb', metadata: ['source' => 'kb']);
        $result = $store->query($namespace, [0.1, 0.2, 0.3], ['top_k' => 2]);

        self::assertSame('chroma', $result->getProvider());
        self::assertSame('kb', $result->getModel());
        self::assertCount(2, $result->getChunks());

        $chunks = $result->getChunks();
        self::assertSame('Document body one', $chunks[0]['text']);
        self::assertSame('doc-1', $chunks[0]['document_id']);
        self::assertSame('Doc One', $chunks[0]['document_name']);
        self::assertSame(
            ['url' => 'https://example.test/doc-1'],
            $chunks[0]['metadata']
        );
        self::assertEqualsWithDelta(0.8695, $chunks[0]['score'], 0.0001);

        self::assertSame('Fallback chunk body', $chunks[1]['text']);
        self::assertSame('doc-2', $chunks[1]['document_id']);
        self::assertSame(['source' => 'import'], $chunks[1]['metadata']);
        self::assertEqualsWithDelta(0.5714, $chunks[1]['score'], 0.0001);

        self::assertCount(1, $history);
        $request = $history[0]['request'];
        self::assertSame(
            '/api/v2/tenants/default/databases/db/collections/kb/query',
            $request->getUri()->getPath()
        );
        $payload = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([[0.1, 0.2, 0.3]], $payload['query_embeddings']);
        self::assertSame(2, $payload['n_results']);
        self::assertSame(
            ['source' => 'kb'],
            $payload['where']
        );
        self::assertSame(['documents', 'metadatas'], $payload['include']);

        $metadata = $result->getProviderMetadata();
        self::assertSame('default', $metadata['tenant']);
        self::assertSame('db', $metadata['database']);
        self::assertSame('kb', $metadata['collection']);
        self::assertSame(2, $metadata['match_count']);
    }

    public function testQueryIncludesEmbeddingsWhenRequested(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['ids' => [[]]], JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client([
            'handler' => $handler,
            'base_uri' => 'http://localhost:8000/api/v2/',
        ]);

        $store = new ChromaVectorStore(
            baseUrl: 'http://localhost:8000',
            tenant: 'default',
            database: 'db',
            collection: 'kb',
            httpClient: $client,
        );

        $namespace = new VectorNamespace('kb', metadata: ['topics' => ['kb', 'manual'], 'priority' => 2]);
        $store->query($namespace, [0.1], ['include_vectors' => true, 'top_k' => 1]);

        $payload = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['documents', 'metadatas', 'embeddings'], $payload['include']);
        self::assertSame(
            [
                '$and' => [
                    ['topics' => ['$in' => ['kb', 'manual']]],
                    ['priority' => 2],
                ],
            ],
            $payload['where']
        );
    }

    public function testDeleteHonoursTimeoutOption(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['ids' => ['doc-1']], JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client([
            'handler' => $handler,
            'base_uri' => 'http://localhost:8000/api/v2/',
        ]);

        $store = new ChromaVectorStore(
            baseUrl: 'http://localhost:8000',
            tenant: 'default',
            database: 'db',
            collection: 'kb',
            httpClient: $client,
        );

        $namespace = new VectorNamespace('kb');
        $store->delete($namespace, ['doc-1'], ['timeout_ms' => 500]);

        self::assertEquals(0.5, $history[0]['options']['timeout']);
    }

    public function testDeleteFallsBackToNamespaceNameWhenCollectionMissing(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['ids' => ['doc-1']], JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client(['handler' => $handler, 'base_uri' => 'http://localhost:8000/api/v2/']);

        $store = new ChromaVectorStore(
            baseUrl: 'http://localhost:8000',
            tenant: 'default',
            database: 'db',
            collection: 'kb',
            httpClient: $client,
        );

        $store->delete(new VectorNamespace('alias', null), ['doc-1']);

        $request = $history[0]['request'];
        self::assertStringContainsString('/collections/alias/delete', (string) $request->getUri());
    }

    public function testQuerySkipsChunksWithoutTextOrMetadata(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'ids' => [['doc-1']],
                'documents' => [['   ']],
                'metadatas' => [[[]]],
            ], JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client(['handler' => $handler, 'base_uri' => 'http://localhost:8000/api/v2/']);

        $store = new ChromaVectorStore(
            baseUrl: 'http://localhost:8000',
            tenant: 'default',
            database: 'db',
            collection: 'kb',
            httpClient: $client,
        );

        $namespace = new VectorNamespace('kb');
        $result = $store->query($namespace, [0.1], ['top_k' => 1]);

        self::assertCount(0, $result->getChunks());
    }
}
