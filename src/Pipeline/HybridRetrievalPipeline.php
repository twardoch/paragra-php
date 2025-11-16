<?php

declare(strict_types=1);

// this_file: paragra-php/src/Pipeline/HybridRetrievalPipeline.php

namespace ParaGra\Pipeline;

use ParaGra\Embedding\EmbeddingProviderInterface;
use ParaGra\Embedding\EmbeddingRequest;
use ParaGra\Response\UnifiedResponse;
use ParaGra\VectorStore\VectorNamespace;
use ParaGra\VectorStore\VectorStoreInterface;
use RuntimeException;

use function array_filter;
use function array_key_exists;
use function array_slice;
use function array_values;
use function is_array;
use function is_numeric;
use function is_string;
use function max;
use function preg_replace;
use function sha1;
use function sprintf;
use function strtolower;
use function trim;
use function usort;

/**
 * Coordinates hybrid retrieval by:
 * - calling a Ragie-backed retriever for keyword/RAG contexts
 * - embedding text and storing it in an external vector store
 * - querying the vector store with semantic embeddings
 * - reranking + deduplicating both sources into a single UnifiedResponse
 */
final class HybridRetrievalPipeline
{
    /**
     * @var callable(string, array<string, mixed>): UnifiedResponse
     */
    private $ragieRetriever;

    public function __construct(
        callable $ragieRetriever,
        private readonly EmbeddingProviderInterface $embeddingProvider,
        private readonly VectorStoreInterface $vectorStore,
        private readonly VectorNamespace $namespace,
        private readonly int $maxCombinedChunks = 8,
        private readonly float $ragieWeight = 1.0,
        private readonly float $vectorStoreWeight = 0.85,
    ) {
        if ($maxCombinedChunks <= 0) {
            throw new RuntimeException('maxCombinedChunks must be positive.');
        }

        if ($ragieWeight <= 0 || $vectorStoreWeight <= 0) {
            throw new RuntimeException('Hybrid weights must be positive.');
        }

        $this->ragieRetriever = $ragieRetriever;
    }

    /**
     * Pulls Ragie context, embeds chunk text, and seeds the configured vector store.
     *
     * @param array{
     *     retrieval?: array<string, mixed>,
     *     vector_store?: array<string, mixed>
     * } $options
     *
     * @return array{
     *     context: UnifiedResponse,
     *     ingested_chunks: int,
     *     upsert: array<string, mixed>
     * }
     */
    public function ingestFromRagie(string $question, array $options = []): array
    {
        $context = $this->callRetriever($question, $options['retrieval'] ?? []);

        if ($context->isEmpty()) {
            return [
                'context' => $context,
                'ingested_chunks' => 0,
                'upsert' => ['upserted' => 0, 'updated' => 0],
            ];
        }

        $inputs = $this->buildEmbeddingInputs($context);
        $embeddingRequest = new EmbeddingRequest($inputs);
        $vectors = $this->embeddingProvider->embed($embeddingRequest);
        $records = $this->buildVectorRecords($vectors['vectors'], $inputs);

        $upsert = $this->vectorStore->upsert(
            $this->namespace,
            $records,
            $options['vector_store'] ?? [],
        );

        return [
            'context' => $context,
            'ingested_chunks' => count($records),
            'upsert' => $upsert,
        ];
    }

    /**
     * Executes hybrid retrieval (Ragie + vector store) and returns raw + reranked contexts.
     *
     * @param array{
     *     retrieval?: array<string, mixed>,
     *     vector_store?: array<string, mixed>,
     *     hybrid_limit?: int
     * } $options
     *
     * @return array{
     *     ragie: UnifiedResponse,
     *     vector_store: UnifiedResponse,
     *     combined: UnifiedResponse
     * }
     */
    public function hybridRetrieve(string $question, array $options = []): array
    {
        $ragieContext = $this->callRetriever($question, $options['retrieval'] ?? []);
        $vectorResponse = $this->queryVectorStore($question, $options['vector_store'] ?? []);

        $limit = (int) ($options['hybrid_limit'] ?? $this->maxCombinedChunks);
        if ($limit <= 0) {
            $limit = $this->maxCombinedChunks;
        }

        $combined = $this->combineContexts($ragieContext, $vectorResponse, $limit);

        return [
            'ragie' => $ragieContext,
            'vector_store' => $vectorResponse,
            'combined' => $combined,
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function callRetriever(string $question, array $options): UnifiedResponse
    {
        $response = ($this->ragieRetriever)($question, $options);
        if (! $response instanceof UnifiedResponse) {
            throw new RuntimeException('Retriever must return a UnifiedResponse instance.');
        }

        return $response;
    }

    /**
     * @return list<array{id: string|null, text: string, metadata: array<string, mixed>|null}>
     */
    private function buildEmbeddingInputs(UnifiedResponse $context): array
    {
        $inputs = [];
        foreach ($context->getChunks() as $index => $chunk) {
            $metadata = [
                'origin' => 'ragie',
                'ragie_score' => $this->extractScore($chunk),
                'ragie_document_id' => $chunk['document_id'] ?? sprintf('ragie-%d', $index + 1),
                'ragie_model' => $this->slug($context->getModel()),
                'ragie_provider' => $this->slug($context->getProvider()),
                'snippet_index' => $index,
            ];

            $metadata = array_filter(
                $metadata,
                static fn (mixed $value): bool => $value !== null
            );

            $inputs[] = [
                'id' => $chunk['document_id'] ?? sprintf('ragie-%d', $index + 1),
                'text' => $chunk['text'],
                'metadata' => $metadata,
            ];
        }

        return $inputs;
    }

    /**
     * @param list<array{id: string|null, values: list<float>, metadata?: array<string, mixed>|null}> $vectors
     * @param list<array{id: string|null, text: string, metadata: array<string, mixed>|null}> $inputs
     *
     * @return list<array{id: string, values: list<float>, metadata: array<string, mixed>}>
     */
    private function buildVectorRecords(array $vectors, array $inputs): array
    {
        $records = [];
        foreach ($vectors as $index => $vector) {
            $input = $inputs[$index] ?? null;
            if ($input === null) {
                continue;
            }

            $id = $vector['id'] ?? $input['id'] ?? sprintf('ragie-chunk-%d', $index + 1);
            $metadata = is_array($vector['metadata'] ?? null) ? $vector['metadata'] : [];

            if ($input['metadata'] !== null) {
                $metadata += $input['metadata'];
            }

            $metadata['text'] ??= $input['text'];
            $metadata['origin'] ??= 'ragie';

            $records[] = [
                'id' => $id,
                'values' => $vector['values'],
                'metadata' => $metadata,
            ];
        }

        return $records;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function queryVectorStore(string $question, array $options): UnifiedResponse
    {
        $request = new EmbeddingRequest([
            [
                'id' => 'query',
                'text' => $question,
                'metadata' => ['origin' => 'query'],
            ],
        ]);

        $embedding = $this->embeddingProvider->embed($request);
        if (! array_key_exists(0, $embedding['vectors'])) {
            throw new RuntimeException('Embedding provider did not return a query vector.');
        }

        $vector = $embedding['vectors'][0]['values'];
        $vectorOptions = $options;
        if (! array_key_exists('query', $vectorOptions)) {
            $vectorOptions['query'] = $question;
        }

        return $this->vectorStore->query($this->namespace, $vector, $vectorOptions);
    }

    private function combineContexts(UnifiedResponse $ragie, UnifiedResponse $vectorStore, int $limit): UnifiedResponse
    {
        $combined = [];
        $ragieKeys = $this->collectChunkKeys($ragie);

        foreach ($ragie->getChunks() as $index => $chunk) {
            $combined[] = $this->decorateChunk(
                $chunk,
                'ragie',
                $ragie->getProvider(),
                $ragie->getModel(),
                $this->ragieWeight,
                $index,
                false,
            );
        }

        foreach ($vectorStore->getChunks() as $index => $chunk) {
            $combined[] = $this->decorateChunk(
                $chunk,
                'vector_store',
                $vectorStore->getProvider(),
                $vectorStore->getModel(),
                $this->vectorStoreWeight,
                $index,
                isset($ragieKeys[$this->chunkKey($chunk)]),
            );
        }

        $deduped = $this->deduplicateChunks($combined);

        usort(
            $deduped,
            static fn (array $a, array $b): int => ($b['score'] ?? 0.0) <=> ($a['score'] ?? 0.0)
        );

        $chunks = array_slice($deduped, 0, $limit);

        return UnifiedResponse::fromChunks(
            provider: 'hybrid',
            model: sprintf('%s+%s', $ragie->getProvider(), $this->vectorStore->getProvider()),
            chunks: $chunks,
            metadata: [
                'ragie_provider' => $ragie->getProvider(),
                'ragie_model' => $ragie->getModel(),
                'vector_store_provider' => $this->vectorStore->getProvider(),
                'vector_store_model' => $vectorStore->getModel(),
            ],
        );
    }

    /**
     * @param array<string, mixed> $chunk
     */
    private function decorateChunk(
        array $chunk,
        string $origin,
        string $provider,
        string $model,
        float $weight,
        int $index,
        bool $duplicatePenalty = false,
    ): array {
        $metadata = is_array($chunk['metadata'] ?? null) ? $chunk['metadata'] : [];
        $metadata['origin'] = $origin;
        $metadata['source_provider'] = $provider;
        $metadata['source_model'] = $model;

        $chunk['score'] = $this->weightedScore($chunk, $weight, $index);
        if ($duplicatePenalty) {
            $chunk['score'] *= 0.9;
        }
        $chunk['metadata'] = $metadata;

        return $chunk;
    }

    /**
     * @param list<array<string, mixed>> $chunks
     *
     * @return list<array<string, mixed>>
     */
    private function deduplicateChunks(array $chunks): array
    {
        $unique = [];

        foreach ($chunks as $chunk) {
            $key = $this->chunkKey($chunk);

            if (! array_key_exists($key, $unique) || ($chunk['score'] ?? 0.0) > ($unique[$key]['score'] ?? 0.0)) {
                $unique[$key] = $chunk;
            }
        }

        return array_values($unique);
    }

    /**
     * Extracts the upstream similarity score.
     *
     * @param array<string, mixed> $chunk
     */
    private function extractScore(array $chunk): ?float
    {
        if (! array_key_exists('score', $chunk)) {
            return null;
        }

        $score = $chunk['score'];
        if (! is_numeric($score)) {
            return null;
        }

        return (float) $score;
    }

    /**
     * @param array<string, mixed> $chunk
     */
    private function weightedScore(array $chunk, float $weight, int $index): float
    {
        $base = $this->extractScore($chunk);
        if ($base === null) {
            $base = max(0.01, 1 - ($index * 0.05));
        }

        return $base * $weight;
    }

    private function slug(string $value): string
    {
        $slug = (string) preg_replace('/[^a-zA-Z0-9]+/', '_', strtolower($value));
        $slug = trim($slug, '_');

        return $slug !== '' ? $slug : 'ragie';
    }

    /**
     * @return array<string, bool>
     */
    private function collectChunkKeys(UnifiedResponse $response): array
    {
        $keys = [];
        foreach ($response->getChunks() as $chunk) {
            $keys[$this->chunkKey($chunk)] = true;
        }

        return $keys;
    }

    /**
     * @param array<string, mixed> $chunk
     */
    private function chunkKey(array $chunk): string
    {
        if (isset($chunk['document_id']) && is_string($chunk['document_id'])) {
            $docId = trim($chunk['document_id']);
            if ($docId !== '') {
                return $docId;
            }
        }

        return sha1($chunk['text']);
    }
}
