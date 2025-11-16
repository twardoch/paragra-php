<?php

declare(strict_types=1);

// this_file: paragra-php/src/Providers/AbstractProvider.php

namespace ParaGra\Providers;

use InvalidArgumentException;
use ParaGra\Config\ProviderSpec;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function in_array;
use function sprintf;
use function strtolower;
use function trim;

/**
 * Convenience base class that wires a ProviderSpec into the ProviderInterface
 * and offers capability helpers plus shared metadata utilities.
 */
abstract class AbstractProvider implements ProviderInterface
{
    /** @var list<string> */
    private array $capabilities;

    public function __construct(
        private readonly ProviderSpec $spec,
        array $capabilities = []
    ) {
        $this->capabilities = $this->normalizeCapabilities($capabilities);
    }

    #[\Override]
    public function getProvider(): string
    {
        return $this->spec->provider;
    }

    #[\Override]
    public function getModel(): string
    {
        return $this->spec->model;
    }

    #[\Override]
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    #[\Override]
    public function supports(string $capability): bool
    {
        return in_array(strtolower($capability), $this->capabilities, true);
    }

    /**
     * Downstream providers often need the full config (API key, solution data).
     */
    final protected function getSpec(): ProviderSpec
    {
        return $this->spec;
    }

    /**
     * Shortcut for retrieving provider-specific solution configuration.
     *
     * @return array<string, mixed>
     */
    final protected function getSolution(): array
    {
        return $this->spec->solution;
    }

    /**
     * Trim and validate incoming queries before dispatching upstream.
     */
    final protected function sanitizeQuery(string $query): string
    {
        $trimmed = trim($query);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Query text cannot be empty.');
        }

        return $trimmed;
    }

    /**
     * Common metadata payload that every UnifiedResponse should include.
     *
     * @return array<string, string>
     */
    final protected function baseMetadata(): array
    {
        return [
            'provider' => $this->getProvider(),
            'model' => $this->getModel(),
        ];
    }

    /**
     * Helper used by descendants to throw consistent errors.
     */
    final protected function invalidOption(string $name, string $reason): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf('Invalid option "%s": %s', $name, $reason));
    }

    /**
     * @param array<string> $capabilities
     *
     * @return list<string>
     */
    private function normalizeCapabilities(array $capabilities): array
    {
        $normalized = array_map(static fn(string $capability): string => strtolower(trim($capability)), $capabilities);
        $normalized = array_filter($normalized, static fn(string $capability): bool => $capability !== '');

        /** @var list<string> $unique */
        $unique = array_values(array_unique($normalized));

        return $unique;
    }
}
