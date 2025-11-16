<?php

declare(strict_types=1);

// this_file: paragra-php/src/ProviderCatalog/ProviderDiscovery.php

namespace ParaGra\ProviderCatalog;

use JsonException;
use ParaGra\Config\ProviderSpec;
use ParaGra\Exception\ConfigurationException;
use RuntimeException;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_replace_recursive;
use function array_values;
use function file_get_contents;
use function getenv;
use function in_array;
use function is_array;
use function is_file;
use function sprintf;
use function str_ends_with;
use function trim;
use const JSON_THROW_ON_ERROR;

/**
 * Catalog-aware helper that exposes lookups + ProviderSpec builders.
 */
final class ProviderDiscovery
{
    /** @var array<string, ProviderSummary> */
    private array $providers = [];

    /**
     * @param iterable<ProviderSummary> $providers
     */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->slug()] = $provider;
        }
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Catalog file "%s" not found.', $path));
        }

        if (str_ends_with($path, '.php')) {
            /** @var array<string, mixed> $catalog */
            $catalog = require $path;
        } else {
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new RuntimeException(sprintf('Unable to read catalog file "%s".', $path));
            }

            try {
                /** @var array<string, mixed> $catalog */
                $catalog = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new RuntimeException(sprintf('Invalid catalog JSON: %s', $e->getMessage()), 0, $e);
            }
        }

        return self::fromCatalogArray($catalog);
    }

    /**
     * @param array<string, mixed> $catalog
     */
    public static function fromCatalogArray(array $catalog): self
    {
        if (!array_key_exists('providers', $catalog) || !is_array($catalog['providers'])) {
            throw new RuntimeException('Catalog payload missing "providers" array.');
        }

        $providers = array_map(
            static fn (array $provider): ProviderSummary => ProviderSummary::fromArray($provider),
            $catalog['providers']
        );

        return new self($providers);
    }

    /**
     * @return list<ProviderSummary>
     */
    public function listProviders(): array
    {
        return array_values($this->providers);
    }

    public function get(string $slug): ?ProviderSummary
    {
        return $this->providers[$slug] ?? null;
    }

    /**
     * @return list<ProviderSummary>
     */
    public function filterByCapability(string $capability): array
    {
        return array_values(array_filter(
            $this->providers,
            static fn (ProviderSummary $summary): bool => $summary->capabilities()->supports($capability)
        ));
    }

    public function supportsEmbeddingDimension(string $slug, int $dimension): bool
    {
        $summary = $this->providers[$slug] ?? null;
        if ($summary === null) {
            return false;
        }

        return in_array($dimension, $summary->embeddingDimensions(), true);
    }

    public function preferredVectorStore(string $slug): ?string
    {
        $summary = $this->providers[$slug] ?? null;

        return $summary?->preferredVectorStore();
    }

    /**
     * Build a ProviderSpec for the given provider slug.
     *
     * @param array<string, mixed> $overrides Supports `model`, `api_key`, and nested `solution` overrides.
     */
    public function buildProviderSpec(string $slug, string $modelType = 'generation', array $overrides = []): ProviderSpec
    {
        $summary = $this->get($slug);
        if ($summary === null) {
            throw ConfigurationException::invalid('provider', sprintf('Unknown provider slug "%s".', $slug));
        }

        $model = $overrides['model'] ?? ($summary->defaultModels()[$modelType] ?? null);
        if ($model === null) {
            throw ConfigurationException::invalid(
                'model',
                sprintf('Provider "%s" does not declare a "%s" model preset.', $slug, $modelType)
            );
        }

        $apiKey = $overrides['api_key'] ?? $this->resolveApiKey($summary);

        $solution = $summary->defaultSolution() ?? [
            'type' => 'ragie',
            'metadata' => $summary->metadata(),
        ];

        if (isset($overrides['solution']) && is_array($overrides['solution'])) {
            $solution = array_replace_recursive($solution, $overrides['solution']);
        }

        return ProviderSpec::fromArray([
            'provider' => $summary->slug(),
            'model' => (string) $model,
            'api_key' => (string) $apiKey,
            'solution' => $solution,
        ]);
    }

    private function resolveApiKey(ProviderSummary $summary): string
    {
        $env = $summary->apiKeyEnv();
        if ($env === null) {
            throw ConfigurationException::invalid(
                'api_key_env',
                sprintf('Provider "%s" does not expose an api_key_env value.', $summary->slug())
            );
        }

        $value = getenv($env);
        if ($value === false) {
            throw ConfigurationException::missingEnv($env);
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            throw ConfigurationException::emptyEnv($env);
        }

        return $trimmed;
    }
}
