<?php

declare(strict_types=1);

// this_file: paragra-php/src/VectorStore/VectorNamespace.php

namespace ParaGra\VectorStore;

use InvalidArgumentException;

use function array_is_list;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function preg_replace;
use function sprintf;
use function strlen;
use function strtolower;
use function trim;

/**
 * Represents a logical namespace/collection within a vector store.
 *
 * Captures slugged identifiers, optional collections (indexes), metadata
 * filters, and eventual consistency hints so adapters can share the same
 * connection contract.
 */
final class VectorNamespace
{
    private string $name;

    private ?string $collection;

    /**
     * @var array<string, string|int|float|bool|list<string|int|float|bool>>
     */
    private array $metadata;

    /**
     * @param array<string, string|int|float|bool|list<string|int|float|bool>> $metadata
     */
    public function __construct(
        string $name,
        ?string $collection = null,
        private readonly bool $eventuallyConsistent = false,
        array $metadata = [],
    ) {
        $this->name = $this->normalizeName($name);
        $this->collection = $this->normalizeCollection($collection);
        $this->metadata = $this->validateMetadata($metadata);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCollection(): ?string
    {
        return $this->collection;
    }

    public function isEventuallyConsistent(): bool
    {
        return $this->eventuallyConsistent;
    }

    /**
     * @return array<string, string|int|float|bool|list<string|int|float|bool>>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return array{
     *     name: string,
     *     collection: string|null,
     *     eventual_consistency: bool,
     *     metadata: array<string, string|int|float|bool|list<string|int|float|bool>>
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'collection' => $this->collection,
            'eventual_consistency' => $this->eventuallyConsistent,
            'metadata' => $this->metadata,
        ];
    }

    private function normalizeName(string $name): string
    {
        $clean = trim($name);
        if ($clean === '') {
            throw new InvalidArgumentException('Vector namespace name cannot be empty.');
        }

        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $clean));
        $slug = trim($slug, '-');
        if ($slug === '') {
            throw new InvalidArgumentException(sprintf('Vector namespace name "%s" is invalid.', $name));
        }

        if ($slug !== '' && strlen($slug) > 64) {
            throw new InvalidArgumentException('Vector namespace names must be <= 64 characters.');
        }

        return $slug;
    }

    private function normalizeCollection(?string $collection): ?string
    {
        if ($collection === null) {
            return null;
        }

        $clean = trim($collection);

        return $clean === '' ? null : $clean;
    }

    /**
     * @param array<string, string|int|float|bool|list<string|int|float|bool>> $metadata
     *
     * @return array<string, string|int|float|bool|list<string|int|float|bool>>
     */
    private function validateMetadata(array $metadata): array
    {
        $validated = [];
        foreach ($metadata as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('Metadata keys must be strings.');
            }

            $cleanKey = trim($key);
            if ($cleanKey === '') {
                throw new InvalidArgumentException('Metadata keys must be non-empty strings.');
            }

            $validated[$cleanKey] = $this->normalizeMetadataValue($value, $cleanKey);
        }

        return $validated;
    }

    /**
     * @return string|int|float|bool|list<string|int|float|bool>
     */
    private function normalizeMetadataValue(mixed $value, string $key): string|int|float|bool|array
    {
        if ($this->isScalar($value)) {
            return $value;
        }

        if (!is_array($value) || $value === []) {
            throw new InvalidArgumentException(
                sprintf('Metadata values must be scalar or non-empty lists (key: %s).', $key)
            );
        }

        if (!array_is_list($value)) {
            throw new InvalidArgumentException(
                sprintf('Metadata lists must be indexed sequentially (key: %s).', $key)
            );
        }

        $list = [];
        foreach ($value as $entry) {
            if (!$this->isScalar($entry)) {
                throw new InvalidArgumentException(
                    sprintf('Metadata list entries must be scalar (key: %s).', $key)
                );
            }
            $list[] = $entry;
        }

        return $list;
    }

    private function isScalar(mixed $value): bool
    {
        return is_string($value) || is_int($value) || is_float($value) || is_bool($value);
    }
}
