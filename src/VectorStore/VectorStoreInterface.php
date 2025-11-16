<?php

declare(strict_types=1);

// this_file: paragra-php/src/VectorStore/VectorStoreInterface.php

namespace ParaGra\VectorStore;

use ParaGra\Response\UnifiedResponse;

/**
 * Abstraction that standardizes CRUD + query operations across vector stores
 * such as Pinecone, Weaviate, Qdrant, Chroma, or Gemini File Search.
 */
interface VectorStoreInterface
{
    /**
     * Machine-friendly provider slug (pinecone, qdrant, etc.).
     */
    public function getProvider(): string;

    /**
     * Default namespace used when callers omit an explicit target.
     */
    public function getDefaultNamespace(): VectorNamespace;

    /**
     * Insert or update embeddings within the namespace.
     *
     * @param list<array{
     *     id: string,
     *     values: list<float>,
     *     metadata?: array<string, mixed>
     * }> $records
     * @param array{
     *     consistency?: 'strong'|'eventual',
     *     wait_for_sync?: bool,
     *     timeout_ms?: int
     * } $options
     *
     * @return array{upserted: int, updated: int, task_id?: string}
     */
    public function upsert(VectorNamespace $namespace, array $records, array $options = []): array;

    /**
     * Delete embeddings by ID.
     *
     * @param list<string> $ids
     * @param array{
     *     wait_for_sync?: bool,
     *     timeout_ms?: int
     * } $options
     *
     * @return array{deleted: int}
     */
    public function delete(VectorNamespace $namespace, array $ids, array $options = []): array;

    /**
     * Execute a KNN search against the namespace.
     *
     * @param list<float> $vector
     * @param array{
     *     top_k?: int,
     *     filter?: array<string, mixed>,
     *     include_vectors?: bool
     * } $options
     */
    public function query(VectorNamespace $namespace, array $vector, array $options = []): UnifiedResponse;
}
