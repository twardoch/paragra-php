<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Pipeline/HybridRetrievalPipelineTest.php

namespace ParaGra\Tests\Pipeline;

use ParaGra\Embedding\EmbeddingProviderInterface;
use ParaGra\Embedding\EmbeddingRequest;
use ParaGra\Pipeline\HybridRetrievalPipeline;
use ParaGra\Response\UnifiedResponse;
use ParaGra\VectorStore\VectorNamespace;
use ParaGra\VectorStore\VectorStoreInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \ParaGra\Pipeline\HybridRetrievalPipeline
 */
final class HybridRetrievalPipelineTest extends TestCase
{
    public function testIngestFromRagieEmbedsChunksAndCallsVectorStore(): void
    {
        $ragieResponse = UnifiedResponse::fromChunks(
            provider: 'ragie',
            model: 'ragie-demo',
            chunks: [
                [
                    'text' => 'ParaGra handles key rotation to stay inside free tiers.',
                    'score' => 0.88,
                    'document_id' => 'doc-1',
                    'metadata' => ['source' => 'kb'],
                ],
                [
                    'text' => 'Vector stores keep frequently accessed passages nearby.',
                    'score' => 0.44,
                    'document_id' => 'doc-2',
                    'metadata' => ['source' => 'kb'],
                ],
            ],
            metadata: ['tier' => 'free'],
        );

        $capturedQuery = null;
        $capturedRetrievalOptions = null;

        $retrieval = static function (string $question, array $options = []) use ($ragieResponse, &$capturedQuery, &$capturedRetrievalOptions): UnifiedResponse {
            $capturedQuery = $question;
            $capturedRetrievalOptions = $options;

            return $ragieResponse;
        };

        $embedding = new FakeEmbeddingProvider();
        $store = new FakeVectorStore();
        $namespace = new VectorNamespace('hybrid-demo', 'kb');

        $pipeline = new HybridRetrievalPipeline(
            ragieRetriever: $retrieval,
            embeddingProvider: $embedding,
            vectorStore: $store,
            namespace: $namespace,
            maxCombinedChunks: 6,
        );

        $result = $pipeline->ingestFromRagie(
            'What does ParaGra do?',
            [
                'retrieval' => ['top_k' => 6],
                'vector_store' => ['consistency' => 'strong'],
            ],
        );

        self::assertSame('What does ParaGra do?', $capturedQuery);
        self::assertSame(['top_k' => 6], $capturedRetrievalOptions);
        self::assertSame(2, $result['ingested_chunks']);
        self::assertSame(['upserted' => 2, 'updated' => 0], $result['upsert']);
        self::assertSame('ragie-demo', $result['context']->getModel());

        self::assertNotNull($embedding->lastRequest);
        $inputs = $embedding->lastRequest->toArray()['inputs'];
        self::assertSame('doc-1', $inputs[0]['id']);
        self::assertSame('ParaGra handles key rotation to stay inside free tiers.', $inputs[0]['text']);
        self::assertSame('ragie', $inputs[0]['metadata']['origin']);
        self::assertSame('ragie_demo', $inputs[0]['metadata']['ragie_model']);

        self::assertNotNull($store->lastUpsert);
        self::assertSame($namespace, $store->lastUpsert['namespace']);
        self::assertSame(['consistency' => 'strong'], $store->lastUpsert['options']);
        $records = $store->lastUpsert['records'];
        self::assertSame('doc-1', $records[0]['id']);
        self::assertSame('ParaGra handles key rotation to stay inside free tiers.', $records[0]['metadata']['text']);
        self::assertSame('ragie', $records[0]['metadata']['origin']);
    }

    public function testHybridRetrieveCombinesAndReranks(): void
    {
        $ragieResponse = UnifiedResponse::fromChunks(
            provider: 'ragie',
            model: 'ragie-demo',
            chunks: [
                [
                    'text' => 'Ragie chunk one',
                    'score' => 0.72,
                    'document_id' => 'doc-1',
                    'metadata' => ['source' => 'ragie'],
                ],
                [
                    'text' => 'Shared paragraph',
                    'score' => 0.50,
                    'document_id' => 'doc-2',
                ],
            ],
        );

        $capturedQuery = null;

        $retrieval = static function (string $question, array $options = []) use ($ragieResponse, &$capturedQuery): UnifiedResponse {
            $capturedQuery = $question;

            return $ragieResponse;
        };

        $embedding = new FakeEmbeddingProvider();
        $store = new FakeVectorStore();
        $store->nextQueryChunks = [
            [
                'text' => 'Semantic chunk',
                'score' => 0.91,
                'document_id' => 'doc-3',
                'metadata' => ['source' => 'vector'],
            ],
            [
                'text' => 'Shared paragraph',
                'score' => 0.93,
                'document_id' => 'doc-2',
            ],
        ];

        $pipeline = new HybridRetrievalPipeline(
            ragieRetriever: $retrieval,
            embeddingProvider: $embedding,
            vectorStore: $store,
            namespace: new VectorNamespace('hybrid-demo'),
            maxCombinedChunks: 3,
        );

        $result = $pipeline->hybridRetrieve(
            'Explain ParaGra hybrid retrieval',
            [
                'retrieval' => ['top_k' => 4],
                'vector_store' => ['top_k' => 2],
                'hybrid_limit' => 3,
            ],
        );

        self::assertSame('Explain ParaGra hybrid retrieval', $capturedQuery);
        self::assertNotNull($store->lastQuery);
        self::assertSame([strlen('Explain ParaGra hybrid retrieval'), 1.0], $store->lastQuery['vector']);
        self::assertSame('Explain ParaGra hybrid retrieval', $store->lastQuery['options']['query']);
        self::assertSame(['top_k' => 2, 'query' => 'Explain ParaGra hybrid retrieval'], $store->lastQuery['options']);

        $combined = $result['combined'];
        self::assertInstanceOf(UnifiedResponse::class, $combined);
        self::assertCount(3, $combined->getChunks());
        self::assertSame('hybrid', $combined->getProvider());
        self::assertSame('ragie+fake-store', $combined->getModel());

        $chunks = $combined->getChunks();
        self::assertSame('Semantic chunk', $chunks[0]['text']);
        self::assertSame('vector_store', $chunks[0]['metadata']['origin']);
        self::assertSame('Ragie chunk one', $chunks[1]['text']);
        self::assertSame('ragie', $chunks[1]['metadata']['origin']);

        // Shared paragraph should be kept once with the higher weighted score (vector store origin).
        $shared = array_values(array_filter($chunks, static fn (array $chunk): bool => $chunk['document_id'] === 'doc-2'));
        self::assertCount(1, $shared);
        self::assertSame('vector_store', $shared[0]['metadata']['origin']);
    }
}

final class FakeEmbeddingProvider implements EmbeddingProviderInterface
{
    public ?EmbeddingRequest $lastRequest = null;

    public function __construct(
        private readonly string $provider = 'fake-embedding',
        private readonly string $model = 'demo-embedding',
    ) {
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * @return list<int>
     */
    public function getSupportedDimensions(): array
    {
        return [2];
    }

    public function getMaxBatchSize(): int
    {
        return 32;
    }

    public function embed(EmbeddingRequest $request): array
    {
        $this->lastRequest = $request;

        $vectors = [];
        foreach ($request->getInputs() as $index => $input) {
            $vectors[] = [
                'id' => $input['id'],
                'values' => [strlen($input['text']), (float) ($index + 1)],
                'metadata' => $input['metadata'],
            ];
        }

        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'dimensions' => 2,
            'vectors' => $vectors,
            'usage' => null,
        ];
    }
}

final class FakeVectorStore implements VectorStoreInterface
{
    public ?array $lastUpsert = null;

    public ?array $lastQuery = null;

    /**
     * @var list<array<string, mixed>>
     */
    public array $nextQueryChunks = [];

    public function getProvider(): string
    {
        return 'fake-store';
    }

    public function getDefaultNamespace(): VectorNamespace
    {
        return new VectorNamespace('fake');
    }

    public function upsert(VectorNamespace $namespace, array $records, array $options = []): array
    {
        $this->lastUpsert = compact('namespace', 'records', 'options');

        return ['upserted' => count($records), 'updated' => 0];
    }

    public function delete(VectorNamespace $namespace, array $ids, array $options = []): array
    {
        return ['deleted' => count($ids)];
    }

    public function query(VectorNamespace $namespace, array $vector, array $options = []): UnifiedResponse
    {
        $this->lastQuery = compact('namespace', 'vector', 'options');

        return UnifiedResponse::fromChunks(
            provider: $this->getProvider(),
            model: 'demo-index',
            chunks: $this->nextQueryChunks,
            metadata: ['namespace' => $namespace->toArray()],
        );
    }
}
