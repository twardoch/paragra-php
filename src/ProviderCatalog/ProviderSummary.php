<?php

declare(strict_types=1);

// this_file: paragra-php/src/ProviderCatalog/ProviderSummary.php

namespace ParaGra\ProviderCatalog;

use InvalidArgumentException;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_values;
use function count;
use function is_array;
use function is_int;
use function is_string;
use function sprintf;
use function trim;

/**
 * Immutable view of a provider entry returned by the catalog sync script.
 */
final class ProviderSummary
{
    /**
     * @param list<string> $models
     * @param array<string, int> $embeddingDimensions
     * @param array<string, string> $defaultModels
     * @param array<string, mixed> $metadata
     */
    private function __construct(
        private readonly string $slug,
        private readonly string $displayName,
        private readonly string $description,
        private readonly ?string $apiKeyEnv,
        private readonly ?string $baseUrl,
        private readonly CapabilityMap $capabilities,
        private readonly int $modelCount,
        private readonly array $models,
        private readonly array $embeddingDimensions,
        private readonly ?string $preferredVectorStore,
        private readonly array $defaultModels,
        private readonly ?array $defaultSolution,
        private readonly array $metadata,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['slug', 'display_name', 'capabilities'] as $required) {
            if (!array_key_exists($required, $data)) {
                throw new InvalidArgumentException(sprintf('Missing provider field "%s".', $required));
            }
        }

        return new self(
            slug: self::sanitizeString($data['slug'], 'slug'),
            displayName: self::sanitizeString($data['display_name'], 'display_name'),
            description: isset($data['description']) ? trim((string) $data['description']) : '',
            apiKeyEnv: isset($data['api_key_env']) && $data['api_key_env'] !== '' ? (string) $data['api_key_env'] : null,
            baseUrl: isset($data['base_url']) && $data['base_url'] !== '' ? (string) $data['base_url'] : null,
            capabilities: CapabilityMap::fromArray((array) $data['capabilities']),
            modelCount: isset($data['model_count']) ? (int) $data['model_count'] : count(self::normalizeModels($data['models'] ?? [])),
            models: self::normalizeModels($data['models'] ?? []),
            embeddingDimensions: self::normalizeEmbeddingDimensions($data['embedding_dimensions'] ?? []),
            preferredVectorStore: isset($data['preferred_vector_store']) && $data['preferred_vector_store'] !== ''
                ? (string) $data['preferred_vector_store']
                : null,
            defaultModels: self::normalizeDefaultModels($data['default_models'] ?? []),
            defaultSolution: isset($data['default_solution']) && is_array($data['default_solution'])
                ? $data['default_solution']
                : null,
            metadata: self::normalizeMetadata($data['metadata'] ?? []),
        );
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function apiKeyEnv(): ?string
    {
        return $this->apiKeyEnv;
    }

    public function baseUrl(): ?string
    {
        return $this->baseUrl;
    }

    public function capabilities(): CapabilityMap
    {
        return $this->capabilities;
    }

    public function modelCount(): int
    {
        return $this->modelCount;
    }

    /**
     * @return list<string>
     */
    public function models(): array
    {
        return $this->models;
    }

    /**
     * @return array<string, int>
     */
    public function embeddingDimensions(): array
    {
        return $this->embeddingDimensions;
    }

    public function preferredVectorStore(): ?string
    {
        return $this->preferredVectorStore;
    }

    /**
     * @return array<string, string>
     */
    public function defaultModels(): array
    {
        return $this->defaultModels;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function defaultSolution(): ?array
    {
        return $this->defaultSolution;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<int|string, mixed> $models
     * @return list<string>
     */
    private static function normalizeModels(array $models): array
    {
        if ($models === []) {
            return [];
        }

        $normalized = array_map(
            static fn (mixed $value): string => (string) $value,
            array_values($models)
        );

        return array_values(array_filter($normalized, static fn (string $value): bool => $value !== ''));
    }

    /**
     * @param array<string, mixed> $dimensions
     * @return array<string, int>
     */
    private static function normalizeEmbeddingDimensions(array $dimensions): array
    {
        $normalized = [];
        foreach ($dimensions as $model => $dimension) {
            if (!is_string($model)) {
                continue;
            }
            if (!is_int($dimension)) {
                $dimension = (int) $dimension;
            }
            if ($dimension > 0) {
                $normalized[$model] = $dimension;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $defaults
     * @return array<string, string>
     */
    private static function normalizeDefaultModels(array $defaults): array
    {
        $normalized = [];
        foreach ($defaults as $key => $value) {
            if (is_string($key) && $value !== null && $value !== '') {
                $normalized[$key] = (string) $value;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private static function normalizeMetadata(array $metadata): array
    {
        return $metadata;
    }

    private static function sanitizeString(mixed $value, string $field): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf('The "%s" value must be a string.', $field));
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new InvalidArgumentException(sprintf('The "%s" value cannot be empty.', $field));
        }

        return $trimmed;
    }
}
