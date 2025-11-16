<?php

declare(strict_types=1);

// this_file: paragra-php/src/ProviderCatalog/CapabilityMap.php

namespace ParaGra\ProviderCatalog;

use InvalidArgumentException;
use function array_diff;
use function array_key_exists;
use function array_keys;
use function implode;
use function sprintf;

/**
 * Normalized capability flags describing what a provider supports.
 */
final class CapabilityMap
{
    private const ALLOWED_KEYS = [
        'llm_chat',
        'embeddings',
        'vector_store',
        'moderation',
        'image_generation',
        'byok',
    ];

    /** @var array<string, bool> */
    private array $flags;

    /**
     * @param array<string, bool> $flags
     */
    private function __construct(array $flags)
    {
        $this->flags = $flags;
    }

    /**
     * Build the capability map from a raw array.
     *
     * Missing keys default to false, unknown keys trigger an exception
     * so we never silently ignore typos introduced by the sync script.
     *
     * @param array<string, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        $flags = [];
        foreach (self::ALLOWED_KEYS as $key) {
            $flags[$key] = isset($values[$key]) ? (bool) $values[$key] : false;
        }

        $unknown = array_diff(array_keys($values), self::ALLOWED_KEYS);
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                'Unknown capability keys detected: %s',
                implode(', ', $unknown)
            ));
        }

        return new self($flags);
    }

    public function supports(string $capability): bool
    {
        if (!array_key_exists($capability, $this->flags)) {
            throw new InvalidArgumentException(sprintf('Capability "%s" is not tracked.', $capability));
        }

        return $this->flags[$capability];
    }

    public function llmChat(): bool
    {
        return $this->flags['llm_chat'];
    }

    public function embeddings(): bool
    {
        return $this->flags['embeddings'];
    }

    public function vectorStore(): bool
    {
        return $this->flags['vector_store'];
    }

    public function moderation(): bool
    {
        return $this->flags['moderation'];
    }

    public function imageGeneration(): bool
    {
        return $this->flags['image_generation'];
    }

    public function byok(): bool
    {
        return $this->flags['byok'];
    }

    /**
     * @return array<string, bool>
     */
    public function toArray(): array
    {
        return $this->flags;
    }
}
