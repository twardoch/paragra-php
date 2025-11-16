<?php

declare(strict_types=1);

// this_file: paragra-php/src/Embedding/EmbeddingRequest.php

namespace ParaGra\Embedding;

use InvalidArgumentException;

use function array_is_list;
use function array_key_exists;
use function count;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;
use function trim;

/**
 * Immutable value object describing a batch embedding request.
 *
 * Inspired by the `vector-search-apis` research, this structure models
 * batch-friendly inputs, metadata filters, and dimension hints so
 * downstream providers can share the same contract.
 */
final class EmbeddingRequest
{
    /**
     * @var list<array{
     *     id: string|null,
     *     text: string,
     *     metadata: array<string, string|int|float|bool|list<string|int|float|bool>>|null
     * }>
     */
    private array $inputs;

    /**
     * @var array<string, string|int|float|bool|list<string|int|float|bool>>|null
     */
    private ?array $metadataFilter;

    public function __construct(
        array $inputs,
        private readonly ?int $dimensions = null,
        private readonly bool $normalize = true,
        ?array $metadataFilter = null,
    ) {
        $this->inputs = $this->normalizeInputs($inputs);
        $this->metadataFilter = $metadataFilter !== null
            ? $this->validateMetadataMap($metadataFilter, 'metadata filter')
            : null;

        if ($this->dimensions !== null && $this->dimensions <= 0) {
            throw new InvalidArgumentException('Dimensions must be positive when provided.');
        }
    }

    /**
     * @return list<array{
     *     id: string|null,
     *     text: string,
     *     metadata: array<string, string|int|float|bool|list<string|int|float|bool>>|null
     * }>
     */
    public function getInputs(): array
    {
        return $this->inputs;
    }

    public function getDimensions(): ?int
    {
        return $this->dimensions;
    }

    public function shouldNormalize(): bool
    {
        return $this->normalize;
    }

    /**
     * @return array<string, string|int|float|bool|list<string|int|float|bool>>|null
     */
    public function getMetadataFilter(): ?array
    {
        return $this->metadataFilter;
    }

    public function getBatchSize(): int
    {
        return count($this->inputs);
    }

    /**
     * @return array{
     *     inputs: list<array{
     *         id: string|null,
     *         text: string,
     *         metadata: array<string, string|int|float|bool|list<string|int|float|bool>>|null
     *     }>,
     *     dimensions: int|null,
     *     normalize: bool,
     *     metadata_filter: array<string, string|int|float|bool|list<string|int|float|bool>>|null
     * }
     */
    public function toArray(): array
    {
        return [
            'inputs' => $this->inputs,
            'dimensions' => $this->dimensions,
            'normalize' => $this->normalize,
            'metadata_filter' => $this->metadataFilter,
        ];
    }

    /**
     * @param list<array{id?: string|null, text: string, metadata?: array<string, mixed>}|string> $inputs
     *
     * @return list<array{
     *     id: string|null,
     *     text: string,
     *     metadata: array<string, string|int|float|bool|list<string|int|float|bool>>|null
     * }>
     */
    private function normalizeInputs(array $inputs): array
    {
        if ($inputs === []) {
            throw new InvalidArgumentException('EmbeddingRequest requires at least one input.');
        }

        $normalized = [];
        foreach ($inputs as $index => $input) {
            if (is_string($input)) {
                $normalized[] = [
                    'id' => null,
                    'text' => $this->normalizeText($input, $index),
                    'metadata' => null,
                ];
                continue;
            }

            if (!is_array($input)) {
                throw new InvalidArgumentException('Embedding inputs must be strings or arrays.');
            }

            if (!array_key_exists('text', $input) || !is_string($input['text'])) {
                throw new InvalidArgumentException(sprintf('Embedding input %d must include non-empty text.', $index));
            }

            $id = null;
            if (array_key_exists('id', $input)) {
                $id = $this->sanitizeId($input['id']);
            }

            $metadata = null;
            if (array_key_exists('metadata', $input)) {
                if (!is_array($input['metadata'])) {
                    throw new InvalidArgumentException(
                        sprintf('Embedding input %d metadata must be an array.', $index)
                    );
                }

                $metadata = $this->validateMetadataMap(
                    $input['metadata'],
                    sprintf('input %d metadata', $index)
                );
            }

            $normalized[] = [
                'id' => $id,
                'text' => $this->normalizeText($input['text'], $index),
                'metadata' => $metadata,
            ];
        }

        return $normalized;
    }

    private function normalizeText(string $text, int $index): string
    {
        $clean = trim($text);
        if ($clean === '') {
            throw new InvalidArgumentException(sprintf('Embedding input %d must include non-empty text.', $index));
        }

        return $clean;
    }

    /**
     * @param array<string, string|int|float|bool|list<string|int|float|bool>> $metadata
     *
     * @return array<string, string|int|float|bool|list<string|int|float|bool>>
     */
    private function validateMetadataMap(array $metadata, string $context): array
    {
        $validated = [];
        foreach ($metadata as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidArgumentException(sprintf('Every %s key must be a non-empty string.', $context));
            }

            $cleanKey = trim($key);
            if ($cleanKey === '') {
                throw new InvalidArgumentException(sprintf('Every %s key must be a non-empty string.', $context));
            }

            $validated[$cleanKey] = $this->normalizeMetadataValue($value, $context, $cleanKey);
        }

        return $validated;
    }

    /**
     * @return string|int|float|bool|list<string|int|float|bool>
     */
    private function normalizeMetadataValue(mixed $value, string $context, string $key): string|int|float|bool|array
    {
        if ($this->isScalar($value)) {
            return $value;
        }

        if (!is_array($value) || $value === []) {
            throw new InvalidArgumentException(
                sprintf('Metadata values must be scalar or a non-empty list (%s:%s).', $context, $key)
            );
        }

        if (!array_is_list($value)) {
            throw new InvalidArgumentException(
                sprintf('Metadata lists must be indexed sequentially (%s:%s).', $context, $key)
            );
        }

        $list = [];
        foreach ($value as $entry) {
            if (!$this->isScalar($entry)) {
                throw new InvalidArgumentException(
                    sprintf('Metadata list entries must be scalar (%s:%s).', $context, $key)
                );
            }
            $list[] = $entry;
        }

        return $list;
    }

    private function sanitizeId(mixed $id): ?string
    {
        if ($id === null) {
            return null;
        }

        if (!is_string($id)) {
            throw new InvalidArgumentException('Embedding input IDs must be strings when provided.');
        }

        $clean = trim($id);

        return $clean === '' ? null : $clean;
    }

    private function isScalar(mixed $value): bool
    {
        return is_string($value) || is_int($value) || is_float($value) || is_bool($value);
    }
}
