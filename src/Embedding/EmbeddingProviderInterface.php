<?php

declare(strict_types=1);

// this_file: paragra-php/src/Embedding/EmbeddingProviderInterface.php

namespace ParaGra\Embedding;

/**
 * Standard contract for providers that expose embedding endpoints.
 *
 * Providers should implement batch-friendly logic (OpenAI, Cohere, Gemini,
 * Voyage, etc.) and honour the metadata filtering conventions captured
 * in {@see EmbeddingRequest}.
 */
interface EmbeddingProviderInterface
{
    /**
     * Machine-friendly provider slug such as "openai" or "cohere".
     */
    public function getProvider(): string;

    /**
     * Returns the upstream model identifier (e.g. text-embedding-3-large).
     */
    public function getModel(): string;

    /**
     * Report supported output dimensions for validation/tuning.
     *
     * @return list<int>
     */
    public function getSupportedDimensions(): array;

    /**
     * Maximum number of inputs accepted by a single API call.
     */
    public function getMaxBatchSize(): int;

    /**
     * Generate embeddings for the provided batch.
     *
     * @return array{
     *     provider: string,
     *     model: string,
     *     dimensions: int,
     *     vectors: list<array{
     *         id: string|null,
     *         values: list<float>,
     *         metadata: array<string, mixed>|null
     *     }>,
     *     usage: array<string, mixed>|null
     * }
     */
    public function embed(EmbeddingRequest $request): array;
}
