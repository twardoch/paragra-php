<?php

declare(strict_types=1);

// this_file: paragra-php/tests/VectorStore/WeaviateVectorStoreTest.php

namespace ParaGra\Tests\VectorStore;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use ParaGra\Response\UnifiedResponse;
use ParaGra\VectorStore\VectorNamespace;
use ParaGra\VectorStore\WeaviateVectorStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ReflectionClass;

use const JSON_THROW_ON_ERROR;

/**
 * @covers \ParaGra\VectorStore\WeaviateVectorStore
 */
final class WeaviateVectorStoreTest extends TestCase
{
    public function testUpsertSendsBatchPayloadWithTenant(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'results' => ['objects' => [], 'successfullyProcessed' => 2],
            ], JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client(['handler' => $handler, 'base_uri' => 'https://example.weaviate.network/v1/']);

        $namespace = new VectorNamespace('kb', collection: 'Articles', metadata: ['tenant' => 'tenant-b']);
        $store = new WeaviateVectorStore(
            baseUrl: 'https://example.weaviate.network',
            className: 'Articles',
            apiKey: 'secret',
            defaultNamespace: $namespace,
            consistencyLevel: 'QUORUM',
            defaultProperties: ['text'],
            httpClient: $client,
        );

        $records = [
            [
                'id' => 'chunk-1',
                'values' => [0.1, 0.2],
                'metadata' => ['text' => 'Chunk one', 'source' => 'kb'],
            ],
            [
                'id' => 'chunk-2',
                'values' => [0.3, 0.4],
                'metadata' => ['text' => 'Chunk two'],
            ],
        ];

        $result = $store->upsert($namespace, $records, ['consistency_level' => 'ALL']);

        self::assertSame(['upserted' => 2, 'updated' => 0], $result);
        self::assertCount(1, $history);

        $request = $history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/v1/batch/objects', $request->getUri()->getPath());
        self::assertSame('consistency_level=ALL', $request->getUri()->getQuery());
        self::assertSame('Bearer secret', $request->getHeaderLine('Authorization'));
        self::assertSame('tenant-b', $request->getHeaderLine('X-Weaviate-Tenant'));

        $payload = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('objects', $payload);
        self::assertCount(2, $payload['objects']);
        self::assertSame('Articles', $payload['objects'][0]['class']);
        self::assertSame('tenant-b', $payload['objects'][0]['tenant']);
        self::assertSame([0.1, 0.2], $payload['objects'][0]['vector']);
        self::assertSame(['text' => 'Chunk one', 'source' => 'kb'], $payload['objects'][0]['properties']);
    }

    public function testDeleteBuildsBatchDeletePayload(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'results' => ['successful' => 2],
            ], JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client(['handler' => $handler, 'base_uri' => 'https://example.weaviate.network/v1/']);

        $namespace = new VectorNamespace('kb', collection: 'Articles', metadata: ['tenant' => 'tenant-c']);
        $store = new WeaviateVectorStore(
            baseUrl: 'https://example.weaviate.network',
            className: 'Articles',
            defaultNamespace: $namespace,
            defaultProperties: ['text'],
            httpClient: $client,
        );

        $result = $store->delete($namespace, ['chunk-1', 'chunk-2']);

        self::assertSame(['deleted' => 2], $result);
        self::assertCount(1, $history);

        $request = $history[0]['request'];
        self::assertSame('DELETE', $request->getMethod());
        self::assertSame('/v1/batch/objects', $request->getUri()->getPath());
        self::assertSame('tenant-c', $request->getHeaderLine('X-Weaviate-Tenant'));

        $payload = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Articles', $payload['match']['class']);
        self::assertSame(['id'], $payload['match']['where']['path']);
        self::assertSame('ContainsAny', $payload['match']['where']['operator']);
        self::assertSame(['chunk-1', 'chunk-2'], $payload['match']['where']['valueStringArray']);
    }

    public function testQueryCreatesUnifiedResponseFromGraphql(): void
    {
        $history = [];
        $graphqlResponse = [
            'data' => [
                'Get' => [
                    'Articles' => [
                        [
                            'text' => 'Chunk one',
                            'title' => 'Doc 1',
                            'source' => 'kb',
                            '_additional' => [
                                'id' => 'chunk-1',
                                'distance' => 0.2,
                                'score' => 0.8,
                                'vector' => [0.5, 0.6],
                            ],
                        ],
                        [
                            'text' => 'Chunk two',
                            '_additional' => [
                                'id' => 'chunk-2',
                                'certainty' => 0.65,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($graphqlResponse, JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client(['handler' => $handler, 'base_uri' => 'https://example.weaviate.network/v1/']);

        $namespace = new VectorNamespace('kb', collection: 'Articles', metadata: [
            'tenant' => 'tenant-d',
            'source' => 'docs',
        ]);

        $store = new WeaviateVectorStore(
            baseUrl: 'https://example.weaviate.network',
            className: 'Articles',
            defaultNamespace: $namespace,
            defaultProperties: ['text', 'title', 'source'],
            httpClient: $client,
        );

        $vector = [0.11, 0.22, 0.33];
        $response = $store->query($namespace, $vector, ['top_k' => 2, 'include_vectors' => true]);

        self::assertInstanceOf(UnifiedResponse::class, $response);
        self::assertSame('weaviate', $response->getProvider());
        self::assertSame('Articles', $response->getModel());
        self::assertCount(2, $response->getChunks());

        $chunks = $response->getChunks();
        self::assertSame('Chunk one', $chunks[0]['text']);
        self::assertSame('chunk-1', $chunks[0]['document_id']);
        self::assertSame(0.8, $chunks[0]['score']);
        self::assertSame('Doc 1', $chunks[0]['document_name']);
        self::assertSame(['source' => 'kb'], $chunks[0]['metadata']);

        self::assertSame('Chunk two', $chunks[1]['text']);
        self::assertSame('chunk-2', $chunks[1]['document_id']);
        self::assertSame(0.65, $chunks[1]['score']);

        self::assertCount(1, $history);
        $request = $history[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/v1/graphql', $request->getUri()->getPath());
        $body = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsString($body['query']);
        self::assertStringContainsString('Articles', $body['query']);
        self::assertStringContainsString('nearVector', $body['query']);
        self::assertStringContainsString('_additional', $body['query']);
        self::assertStringContainsString('vector', $body['query']);

        $variables = $body['variables'];
        self::assertSame($vector, $variables['vector']);
        self::assertSame('tenant-d', $variables['tenant']);
        self::assertSame('ONE', $variables['consistency']);
        self::assertSame([
            'operator' => 'And',
            'operands' => [
                [
                    'path' => ['source'],
                    'operator' => 'Equal',
                    'valueText' => 'docs',
                ],
            ],
        ], $variables['where']);
    }

    public function testDeleteWithSingleIdUsesEqualOperator(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'results' => ['successfullyDeleted' => 1],
            ], JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client(['handler' => $handler, 'base_uri' => 'https://example.weaviate.network/v1/']);
        $namespace = new VectorNamespace('kb', collection: 'Articles');
        $store = new WeaviateVectorStore(
            baseUrl: 'https://example.weaviate.network',
            className: 'Articles',
            httpClient: $client,
        );

        $result = $store->delete($namespace, ['chunk-1']);

        self::assertSame(['deleted' => 1], $result);
        $payload = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Equal', $payload['match']['where']['operator']);
        self::assertSame('chunk-1', $payload['match']['where']['valueText']);
    }

    public function testQueryThrowsWhenGraphqlReturnsErrors(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['errors' => [['message' => 'graphql boom']]], JSON_THROW_ON_ERROR)),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock), 'base_uri' => 'https://example.weaviate.network/v1/']);

        $store = new WeaviateVectorStore(
            baseUrl: 'https://example.weaviate.network',
            className: 'Articles',
            httpClient: $client,
        );

        $namespace = new VectorNamespace('kb', collection: 'Articles');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GraphQL query failed');
        $store->query($namespace, [0.1, 0.2], ['query' => 'What is ParaGra?']);
    }

    public function testQueryBuildsMetadataFiltersForListValues(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['data' => ['Get' => ['Articles' => []]]], JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client(['handler' => $handler, 'base_uri' => 'https://example.weaviate.network/v1/']);

        $namespace = new VectorNamespace(
            name: 'kb',
            collection: 'Articles',
            metadata: [
                'tenant' => 'tenant-y',
                'labels' => ['kb', 'manual'],
                'priority' => [1, 2],
                'boost' => [0.4],
                'active' => [true],
            ]
        );

        $store = new WeaviateVectorStore(
            baseUrl: 'https://example.weaviate.network',
            className: 'Articles',
            httpClient: $client,
        );

        $store->query($namespace, [0.11, 0.22], ['top_k' => 1]);

        $payload = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $where = $payload['variables']['where'];

        self::assertSame('And', $where['operator']);
        $operands = $where['operands'];
        self::assertContains(['path' => ['labels'], 'operator' => 'ContainsAny', 'valueStringArray' => ['kb', 'manual']], $operands);
        self::assertContains(['path' => ['priority'], 'operator' => 'ContainsAny', 'valueIntArray' => [1, 2]], $operands);
        self::assertContains(['path' => ['boost'], 'operator' => 'ContainsAny', 'valueNumberArray' => [0.4]], $operands);
        self::assertContains(['path' => ['active'], 'operator' => 'ContainsAny', 'valueBooleanArray' => [true]], $operands);
    }

    public function testConstructorRejectsEmptyBaseUrl(): void
    {
        $this->expectException(RuntimeException::class);
        new WeaviateVectorStore(baseUrl: '   ', className: 'Articles');
    }

    public function testUpsertIgnoresBlankConsistencyLevel(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'results' => ['objects' => [], 'successfullyProcessed' => 1],
            ], JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client(['handler' => $handler, 'base_uri' => 'https://example.weaviate.network/v1/']);
        $store = new WeaviateVectorStore(
            baseUrl: 'https://example.weaviate.network',
            className: 'Articles',
            httpClient: $client,
        );

        $namespace = new VectorNamespace('kb', collection: 'Articles');
        $store->upsert($namespace, [
            ['id' => 'doc-1', 'values' => [0.1], 'metadata' => ['text' => 'chunk']],
        ], ['consistency_level' => ' ']);

        self::assertSame('', $history[0]['request']->getUri()->getQuery());
    }

    public function testQueryTrimsConsistencyOverrides(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['data' => ['Get' => ['Articles' => []]]], JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::history($history));

        $client = new Client(['handler' => $handler, 'base_uri' => 'https://example.weaviate.network/v1/']);
        $store = new WeaviateVectorStore(
            baseUrl: 'https://example.weaviate.network',
            className: 'Articles',
            httpClient: $client,
        );
        $namespace = new VectorNamespace('kb', collection: 'Articles');

        $store->query($namespace, [0.1], ['query' => 'Hi', 'consistency_level' => '  QUORUM ']);

        $payload = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('QUORUM', $payload['variables']['consistency']);
    }

    public function testSanitizePropertiesRemovesDuplicates(): void
    {
        $store = new WeaviateVectorStore('https://example.weaviate.network', 'Articles');
        $ref = new ReflectionClass($store);
        $method = $ref->getMethod('sanitizeProperties');
        $method->setAccessible(true);

        /** @var array<int, string> $sanitized */
        $sanitized = $method->invoke($store, [' title ', '', 'text', 'title', 42]);

        self::assertSame(['title', 'text'], $sanitized);
    }

    public function testRequestWrapsHttpExceptions(): void
    {
        $mock = new MockHandler([
            new \GuzzleHttp\Exception\RequestException('boom', new \GuzzleHttp\Psr7\Request('GET', 'test')),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock), 'base_uri' => 'https://example.weaviate.network/v1/']);

        $store = new WeaviateVectorStore(
            baseUrl: 'https://example.weaviate.network',
            className: 'Articles',
            httpClient: $client,
        );

        $namespace = new VectorNamespace('kb', collection: 'Articles');
        $this->expectException(RuntimeException::class);
        $store->query($namespace, [0.1], ['query' => 'Hello']);
    }
}
