<?php

declare(strict_types=1);

// this_file: paragra-php/src/Router/KeyRotator.php

namespace ParaGra\Router;

use ParaGra\Config\ProviderSpec;
use RuntimeException;

use function count;
use function time;

/**
 * Timestamp-based rotation for pool entries.
 */
final class KeyRotator
{
    /**
     * @var callable(): int
     */
    private $timeProvider;

    public function __construct(?callable $timeProvider = null)
    {
        $this->timeProvider = $timeProvider ?? static fn (): int => time();
    }

    /**
     * @param array<int, ProviderSpec> $pool
     */
    public function selectSpec(array $pool): ProviderSpec
    {
        if ($pool === []) {
            throw new RuntimeException('Cannot select provider from an empty pool.');
        }

        if (count($pool) === 1) {
            return $pool[0];
        }

        $timestamp = ($this->timeProvider)();
        $index = $timestamp % count($pool);

        return $pool[$index];
    }

    /**
     * @param array<int, ProviderSpec> $pool
     */
    public function getNextSpec(array $pool, int $currentIndex): ProviderSpec
    {
        if ($pool === []) {
            throw new RuntimeException('Cannot rotate within an empty pool.');
        }

        $nextIndex = ($currentIndex + 1) % count($pool);

        return $pool[$nextIndex];
    }
}
