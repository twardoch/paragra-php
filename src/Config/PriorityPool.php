<?php

declare(strict_types=1);

// this_file: paragra-php/src/Config/PriorityPool.php

namespace ParaGra\Config;

use InvalidArgumentException;
use ParaGra\ProviderCatalog\ProviderDiscovery;

use function array_key_exists;
use function array_replace;
use function array_values;
use function count;
use function get_debug_type;
use function is_array;
use function is_string;
use function sprintf;
use function trim;

final class PriorityPool
{
    /**
     * @param array<int, array<int, ProviderSpec>> $pools
     */
    public function __construct(private array $pools)
    {
        foreach ($this->pools as $index => $pool) {
            if (!is_array($pool)) {
                throw new InvalidArgumentException(sprintf('Pool "%d" must be an array.', $index));
            }

            foreach ($pool as $spec) {
                if (!$spec instanceof ProviderSpec) {
                    $type = get_debug_type($spec);
                    throw new InvalidArgumentException(sprintf('Pool "%d" contains invalid entry of type "%s".', $index, $type));
                }
            }
        }

        $this->pools = array_values($this->pools);
    }

    /**
     * @param array<int, array<int, array<string, mixed>>> $config
     */
    public static function fromArray(array $config, ?ProviderDiscovery $catalog = null): self
    {
        $pools = [];
        foreach ($config as $poolIndex => $poolSpecs) {
            if (!is_array($poolSpecs)) {
                throw new InvalidArgumentException(sprintf('Pool "%d" must be an array of provider specs.', $poolIndex));
            }

            $pool = [];
            foreach ($poolSpecs as $specIndex => $specData) {
                if (!is_array($specData)) {
                    throw new InvalidArgumentException(sprintf('Pool "%d" entry "%d" must be an array.', $poolIndex, $specIndex));
                }

                $pool[] = self::createSpecFromConfig($specData, $catalog);
            }

            $pools[] = $pool;
        }

        return new self($pools);
    }

    /**
     * @return array<int, ProviderSpec>
     */
    public function getPool(int $priority): array
    {
        return $this->pools[$priority] ?? [];
    }

    public function getPoolCount(): int
    {
        return count($this->pools);
    }

    /**
     * @param array<string, mixed> $specData
     */
    private static function createSpecFromConfig(array $specData, ?ProviderDiscovery $catalog): ProviderSpec
    {
        if (!self::isCatalogReference($specData)) {
            return ProviderSpec::fromArray($specData);
        }

        if ($catalog === null) {
            throw new InvalidArgumentException(
                'Catalog-backed provider entries require a provider catalog. '
                . 'Set "provider_catalog" in your config or pass a ProviderDiscovery instance.'
            );
        }

        return self::buildSpecFromCatalog($specData, $catalog);
    }

    /**
     * @param array<string, mixed> $specData
     */
    private static function isCatalogReference(array $specData): bool
    {
        return array_key_exists('catalog', $specData) || array_key_exists('catalog_slug', $specData);
    }

    /**
     * @param array<string, mixed> $specData
     */
    private static function buildSpecFromCatalog(array $specData, ProviderDiscovery $catalog): ProviderSpec
    {
        $catalogConfig = self::normalizeCatalogConfig($specData);

        $slug = self::extractSlug($catalogConfig);
        $modelType = self::extractModelType($catalogConfig);
        $overrides = self::extractOverrides($catalogConfig);
        $metadataOverrides = self::extractMetadataOverrides($specData, $catalogConfig);

        if ($metadataOverrides !== []) {
            if (!isset($overrides['solution']) || !is_array($overrides['solution'])) {
                $overrides['solution'] = [];
            }

            $existingMetadata = [];
            if (isset($overrides['solution']['metadata']) && is_array($overrides['solution']['metadata'])) {
                $existingMetadata = $overrides['solution']['metadata'];
            }

            $overrides['solution']['metadata'] = array_replace($existingMetadata, $metadataOverrides);
        }

        return $catalog->buildProviderSpec($slug, $modelType, $overrides);
    }

    /**
     * @param array<string, mixed> $specData
     * @return array<string, mixed>
     */
    private static function normalizeCatalogConfig(array $specData): array
    {
        $catalogConfig = [];
        if (array_key_exists('catalog', $specData)) {
            $catalog = $specData['catalog'];
            if (is_array($catalog)) {
                $catalogConfig = $catalog;
            } elseif (is_string($catalog)) {
                $catalogConfig['slug'] = $catalog;
            }
        }

        if (isset($specData['catalog_slug'])) {
            $catalogConfig['slug'] = $specData['catalog_slug'];
        }

        if (isset($specData['catalog_model_type'])) {
            $catalogConfig['model_type'] = $specData['catalog_model_type'];
        } elseif (!isset($catalogConfig['model_type']) && isset($specData['model_type'])) {
            $catalogConfig['model_type'] = $specData['model_type'];
        }

        if (isset($specData['catalog_overrides']) && is_array($specData['catalog_overrides'])) {
            $catalogConfig['overrides'] = $specData['catalog_overrides'];
        } elseif (!isset($catalogConfig['overrides']) && isset($specData['overrides']) && is_array($specData['overrides'])) {
            $catalogConfig['overrides'] = $specData['overrides'];
        }

        if (isset($specData['metadata']) && is_array($specData['metadata'])) {
            $catalogConfig['metadata'] = $specData['metadata'];
        }

        return $catalogConfig;
    }

    /**
     * @param array<string, mixed> $catalogConfig
     */
    private static function extractSlug(array $catalogConfig): string
    {
        if (!isset($catalogConfig['slug']) || trim((string) $catalogConfig['slug']) === '') {
            throw new InvalidArgumentException('Catalog-backed provider entries require a non-empty "slug".');
        }

        return trim((string) $catalogConfig['slug']);
    }

    /**
     * @param array<string, mixed> $catalogConfig
     */
    private static function extractModelType(array $catalogConfig): string
    {
        $modelType = isset($catalogConfig['model_type']) ? trim((string) $catalogConfig['model_type']) : '';
        return $modelType === '' ? 'generation' : $modelType;
    }

    /**
     * @param array<string, mixed> $catalogConfig
     * @return array<string, mixed>
     */
    private static function extractOverrides(array $catalogConfig): array
    {
        if (!isset($catalogConfig['overrides']) || !is_array($catalogConfig['overrides'])) {
            return [];
        }

        return $catalogConfig['overrides'];
    }

    /**
     * @param array<string, mixed> $specData
     * @param array<string, mixed> $catalogConfig
     * @return array<string, mixed>
     */
    private static function extractMetadataOverrides(array $specData, array $catalogConfig): array
    {
        $metadata = [];
        if (isset($catalogConfig['metadata']) && is_array($catalogConfig['metadata'])) {
            $metadata = array_replace($metadata, $catalogConfig['metadata']);
        }

        if (isset($catalogConfig['metadata_overrides']) && is_array($catalogConfig['metadata_overrides'])) {
            $metadata = array_replace($metadata, $catalogConfig['metadata_overrides']);
        }

        if (isset($specData['metadata_overrides']) && is_array($specData['metadata_overrides'])) {
            $metadata = array_replace($metadata, $specData['metadata_overrides']);
        }

        foreach (['tier', 'latency_tier', 'cost_ceiling', 'compliance'] as $key) {
            if (array_key_exists($key, $specData) && $specData[$key] !== null) {
                $metadata[$key] = $specData[$key];
            }
        }

        return $metadata;
    }
}
