<?php

declare(strict_types=1);

// this_file: paragra-php/tests/VectorStore/GeminiFileSearchVectorStoreTest.php

namespace ParaGra\Tests\VectorStore;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use ParaGra\VectorStore\GeminiFileSearchVectorStore;
use ParaGra\VectorStore\VectorNamespace;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ReflectionClass;

use function json_decode;
use function json_encode;
use function parse_str;

use const JSON_THROW_ON_ERROR;

/**
 * @covers \ParaGra\VectorStore\GeminiFileSearchVectorStore
 */
final class GeminiFileSearchVectorStoreTest extends TestCase
{
    public function testUpsertCreatesDocuments(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['documents' => []], JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client([
            'handler' => $handler,
            'base_uri' => 'https://generativelanguage.googleapis.com',
        ]);

        $store = new GeminiFileSearchVectorStore(
            apiKey: 'test-key',
            resourceName: 'fileSearchStores/demo-store',
            httpClient: $client,
        );

        $namespace = new VectorNamespace('demo', 'fileSearchStores/demo-store');

        $records = [
            [
                'id' => 'DocOne',
                'values' => [0.1, 0.2],
                'metadata' => [
                    'text' => 'First chunk body',
                    'display_name' => 'Doc One',
                    'topic' => 'kb',
                ],
            ],
            [
                'id' => 'DocTwo',
                'values' => [0.3, 0.9],
                'metadata' => [
                    'text' => 'Second chunk',
                    'source' => 'manual',
                    'tags' => ['alpha', 'beta'],
                ],
            ],
        ];

        $result = $store->upsert($namespace, $records);

        self::assertSame(['upserted' => 2, 'updated' => 0], $result);
        self::assertCount(1, $history);

        $request = $history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            '/v1beta/fileSearchStores/demo-store/documents:batchCreate',
            $request->getUri()->getPath()
        );

        parse_str($request->getUri()->getQuery(), $query);
        self::assertSame('test-key', $query['key']);

        $payload = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(2, $payload['requests']);

        $first = $payload['requests'][0];
        self::assertSame('docone', $first['documentId']);
        self::assertSame('Doc One', $first['document']['displayName']);
        self::assertSame(
            [
                ['key' => 'topic', 'value' => 'kb'],
            ],
            $first['document']['customMetadata']
        );
        $jsonData = json_decode($first['document']['jsonData'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('docone', $jsonData['id']);
        self::assertSame('First chunk body', $jsonData['text']);

        $second = $payload['requests'][1];
        self::assertSame('doctwo', $second['documentId']);
        $secondMetadata = $second['document']['customMetadata'];
        self::assertCount(3, $secondMetadata);
        self::assertSame(
            [
                ['key' => 'source', 'value' => 'manual'],
                ['key' => 'tags', 'value' => 'alpha'],
                ['key' => 'tags', 'value' => 'beta'],
            ],
            $secondMetadata
        );
    }

    public function testDeleteRemovesDocuments(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(204),
            new Response(204),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client([
            'handler' => $handler,
            'base_uri' => 'https://generativelanguage.googleapis.com',
        ]);

        $store = new GeminiFileSearchVectorStore(
            apiKey: 'test-key',
            resourceName: 'fileSearchStores/demo-store',
            httpClient: $client,
        );

        $namespace = new VectorNamespace('demo', 'fileSearchStores/demo-store');

        $result = $store->delete($namespace, ['doc-1', 'doc-2']);

        self::assertSame(['deleted' => 2], $result);
        self::assertCount(2, $history);

        $firstRequest = $history[0]['request'];
        self::assertSame('DELETE', $firstRequest->getMethod());
        self::assertSame(
            '/v1beta/fileSearchStores/demo-store/documents/doc-1',
            $firstRequest->getUri()->getPath()
        );
    }

    public function testQueryReturnsUnifiedResponse(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'relevantChunks' => [
                    [
                        'text' => 'Chunk one answer',
                        'relevanceScore' => 0.92,
                        'documentId' => 'doc-1',
                        'chunkId' => 'chunk-1',
                        'metadata' => ['source' => 'kb'],
                        'pageSpan' => ['startPage' => 1, 'endPage' => 2],
                    ],
                    [
                        'text' => 'Chunk two answer',
                        'relevanceScore' => 0.75,
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client([
            'handler' => $handler,
            'base_uri' => 'https://generativelanguage.googleapis.com',
        ]);

        $store = new GeminiFileSearchVectorStore(
            apiKey: 'test-key',
            resourceName: 'projects/demo/locations/us/corpora/my-corpus',
            httpClient: $client,
        );

        $namespace = new VectorNamespace(
            name: 'demo',
            collection: 'projects/demo/locations/us/corpora/my-corpus',
            metadata: ['source' => 'kb']
        );

        $response = $store->query($namespace, [], ['query' => 'Where is doc?', 'top_k' => 3]);

        self::assertSame('gemini-file-search', $response->getProvider());
        self::assertSame('projects/demo/locations/us/corpora/my-corpus', $response->getModel());
        self::assertCount(2, $response->getChunks());

        $chunks = $response->getChunks();
        self::assertSame('Chunk one answer', $chunks[0]['text']);
        self::assertSame('doc-1', $chunks[0]['document_id']);
        self::assertSame('chunk-1', $chunks[0]['metadata']['chunk_id']);
        self::assertSame(['startPage' => 1, 'endPage' => 2], $chunks[0]['metadata']['page_span']);

        self::assertSame('Chunk two answer', $chunks[1]['text']);

        $request = $history[0]['request'];
        $payload = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Where is doc?', $payload['query']);
        self::assertSame(['maxChunks' => 3], $payload['chunkControl']);
        self::assertSame(
            [['key' => 'source', 'value' => 'kb']],
            $payload['metadataFilters']
        );
    }

    public function testQueryAllowsResourceOverrideAndNormalizes(): void
    {
        $history = [];
        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['relevantChunks' => []], JSON_THROW_ON_ERROR)),
        ]));
        $handler->push(Middleware::history($history));

        $client = new Client(['handler' => $handler, 'base_uri' => 'https://generativelanguage.googleapis.com']);
        $store = new GeminiFileSearchVectorStore('key', 'fileSearchStores/default', httpClient: $client);

        $namespace = new VectorNamespace('default');
        $store->query($namespace, [], ['query' => 'Hello', 'resource' => 'custom-store']);

        $request = $history[0]['request'];
        self::assertSame(
            '/v1beta/fileSearchStores/custom-store/documents:query',
            $request->getUri()->getPath()
        );
    }

    public function testQueryRejectsEmptyResourceOverrides(): void
    {
        $store = new GeminiFileSearchVectorStore('key', 'fileSearchStores/default');
        $namespace = new VectorNamespace('default');

        $this->expectException(RuntimeException::class);
        $store->query($namespace, [], ['query' => 'Hello', 'resource' => '   ']);
    }

    public function testQueryBuildsMetadataFiltersForListsAndScalars(): void
    {
        $history = [];
        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['relevantChunks' => []], JSON_THROW_ON_ERROR)),
        ]));
        $handler->push(Middleware::history($history));

        $client = new Client(['handler' => $handler, 'base_uri' => 'https://generativelanguage.googleapis.com']);
        $store = new GeminiFileSearchVectorStore('key', 'fileSearchStores/default', httpClient: $client);

        $namespace = new VectorNamespace('default', metadata: [
            'labels' => ['kb', 'manual'],
            'active' => true,
            'score' => 0.91,
        ]);

        $store->query($namespace, [], ['query' => 'Explain ParaGra']);

        $payload = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertEqualsCanonicalizing(
            [
                ['key' => 'labels', 'value' => 'kb'],
                ['key' => 'labels', 'value' => 'manual'],
                ['key' => 'active', 'value' => 'true'],
                ['key' => 'score', 'value' => '0.91'],
            ],
            $payload['metadataFilters']
        );
    }

    public function testSanitizeDocumentIdGeneratesSafeFallbacks(): void
    {
        $store = new GeminiFileSearchVectorStore('key', 'fileSearchStores/default');
        $reflection = new ReflectionClass($store);
        $method = $reflection->getMethod('sanitizeDocumentId');
        $method->setAccessible(true);

        $generated = $method->invoke($store, '');
        self::assertNotSame('', $generated);
        self::assertSame(strtolower($generated), $generated);
    }

    public function testQueryRequiresNonEmptyQuery(): void
    {
        $store = new GeminiFileSearchVectorStore(
            apiKey: 'key',
            resourceName: 'fileSearchStores/demo-store'
        );

        $namespace = new VectorNamespace('demo', 'fileSearchStores/demo-store');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('non-empty "query" option');
        $store->query($namespace, [], ['query' => '   ']);
    }

    public function testUpsertThrowsWhenTextMissing(): void
    {
        $store = new GeminiFileSearchVectorStore(
            apiKey: 'key',
            resourceName: 'fileSearchStores/demo-store'
        );

        $namespace = new VectorNamespace('demo', 'fileSearchStores/demo-store');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('metadata["text"]');
        $store->upsert($namespace, [[
            'id' => 'missing-text',
            'values' => [0.1],
            'metadata' => ['display_name' => 'Doc'],
        ]]);
    }

    public function testDeleteWithNoIdsSkipsRequests(): void
    {
        $history = [];
        $handler = HandlerStack::create(new MockHandler([]));
        $handler->push(Middleware::history($history));
        $client = new Client(['handler' => $handler, 'base_uri' => 'https://generativelanguage.googleapis.com']);

        $store = new GeminiFileSearchVectorStore(
            apiKey: 'key',
            resourceName: 'fileSearchStores/demo-store',
            httpClient: $client
        );

        $namespace = new VectorNamespace('demo', 'fileSearchStores/demo-store');
        $result = $store->delete($namespace, []);

        self::assertSame(['deleted' => 0], $result);
        self::assertCount(0, $history);
    }
}
