<?php

declare(strict_types=1);

// this_file: paragra-php/src/Response/UnifiedResponse.php

namespace ParaGra\Response;

use Countable;
use InvalidArgumentException;

use function array_key_exists;
use function array_map;
use function array_values;
use function is_array;
use function is_numeric;
use function is_string;
use function sprintf;
use function trim;

/**
 * Provider-agnostic retrieval payload so every ParaGra provider can emit
 * identical chunk, metadata, cost, and usage data without downstream adapters
 * caring about the original API response shape.
 */
final class UnifiedResponse implements Countable
{
    /** @var list<array<string, mixed>> */
    private array $chunks;

    /** @var list<string>|null */
    private ?array $chunkTexts = null;

    /**
     * @param list<array<string, mixed>> $chunks
     * @param array<string, mixed> $providerMetadata
     * @param array<string, mixed>|null $usage
     * @param array<string, mixed>|null $cost
     */
    public function __construct(
        private readonly string $provider,
        private readonly string $model,
        array $chunks,
        private readonly array $providerMetadata = [],
        private readonly ?array $usage = null,
        private readonly ?array $cost = null,
    ) {
        $this->chunks = $this->validateChunks($chunks);
    }

    /**
     * Convenience factory for creating responses without manually invoking the constructor.
     *
     * @param list<array<string, mixed>> $chunks
     * @param array<string, mixed> $metadata
     * @param array<string, mixed>|null $usage
     * @param array<string, mixed>|null $cost
     */
    public static function fromChunks(
        string $provider,
        string $model,
        array $chunks,
        array $metadata = [],
        ?array $usage = null,
        ?array $cost = null,
    ): self {
        return new self($provider, $model, $chunks, $metadata, $usage, $cost);
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
     * @return list<array<string, mixed>>
     */
    public function getChunks(): array
    {
        return $this->chunks;
    }

    /**
     * Convenience helper for prompt builders that only need context text.
     *
     * @return list<string>
     */
    public function getChunkTexts(): array
    {
        if ($this->chunkTexts === null) {
            $this->chunkTexts = array_map(
                static fn(array $chunk): string => $chunk['text'],
                $this->chunks
            );
        }

        return $this->chunkTexts;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProviderMetadata(): array
    {
        return $this->providerMetadata;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getUsage(): ?array
    {
        return $this->usage;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCost(): ?array
    {
        return $this->cost;
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    #[\Override]
    public function count(): int
    {
        return count($this->chunks);
    }

    /**
     * Convert the response back to a normalized array payload.
     *
     * @return array{
     *     provider: string,
     *     model: string,
     *     chunks: list<array<string, mixed>>,
     *     metadata: array<string, mixed>,
     *     usage: array<string, mixed>|null,
     *     cost: array<string, mixed>|null
     * }
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'chunks' => $this->chunks,
            'metadata' => $this->providerMetadata,
            'usage' => $this->usage,
            'cost' => $this->cost,
        ];
    }

    /**
     * @param list<array<string, mixed>> $chunks
     *
     * @return list<array<string, mixed>>
     */
    private function validateChunks(array $chunks): array
    {
        $normalized = [];

        foreach ($chunks as $index => $chunk) {
            if (!is_array($chunk)) {
                throw new InvalidArgumentException('Chunk entries must be arrays.');
            }

            if (!array_key_exists('text', $chunk) || !is_string($chunk['text'])) {
                throw new InvalidArgumentException(sprintf('Chunk %d is missing a valid "text" field.', $index));
            }

            $text = trim($chunk['text']);
            if ($text === '') {
                throw new InvalidArgumentException(sprintf('Chunk %d has an empty text payload.', $index));
            }

            $normalizedChunk = ['text' => $text];

            if (array_key_exists('score', $chunk) && $chunk['score'] !== null) {
                if (!is_numeric($chunk['score'])) {
                    throw new InvalidArgumentException('Chunk score must be numeric.');
                }

                $normalizedChunk['score'] = (float) $chunk['score'];
            }

            if (array_key_exists('document_id', $chunk) && $chunk['document_id'] !== null) {
                if (!is_string($chunk['document_id'])) {
                    throw new InvalidArgumentException('Document ID must be a string when provided.');
                }

                $docId = trim($chunk['document_id']);
                if ($docId !== '') {
                    $normalizedChunk['document_id'] = $docId;
                }
            }

            if (array_key_exists('document_name', $chunk) && $chunk['document_name'] !== null) {
                if (!is_string($chunk['document_name'])) {
                    throw new InvalidArgumentException('Document name must be a string when provided.');
                }

                $docName = trim($chunk['document_name']);
                if ($docName !== '') {
                    $normalizedChunk['document_name'] = $docName;
                }
            }

            if (array_key_exists('metadata', $chunk) && $chunk['metadata'] !== null) {
                if (!is_array($chunk['metadata'])) {
                    throw new InvalidArgumentException('Chunk metadata must be an array when provided.');
                }

                $normalizedChunk['metadata'] = $chunk['metadata'];
            }

            $normalized[] = $normalizedChunk;
        }

        /** @var list<array<string, mixed>> $normalized */
        $normalized = array_values($normalized);

        return $normalized;
    }
}
