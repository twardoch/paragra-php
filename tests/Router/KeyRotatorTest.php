<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Router/KeyRotatorTest.php

namespace ParaGra\Tests\Router;

use ParaGra\Config\ProviderSpec;
use ParaGra\Router\KeyRotator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(KeyRotator::class)]
#[UsesClass(ProviderSpec::class)]
final class KeyRotatorTest extends TestCase
{
    public function test_select_spec_when_pool_empty_then_throws(): void
    {
        $rotator = new KeyRotator();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('empty');

        $rotator->selectSpec([]);
    }

    public function test_select_spec_when_pool_has_single_spec_then_returns_it(): void
    {
        $spec = $this->createSpec('key-1');
        $rotator = new KeyRotator();

        self::assertSame($spec, $rotator->selectSpec([$spec]));
    }

    public function test_select_spec_when_multiple_specs_then_uses_timestamp_distribution(): void
    {
        $pool = [
            $this->createSpec('key-1'),
            $this->createSpec('key-2'),
            $this->createSpec('key-3'),
        ];

        $rotator = new KeyRotator(static fn (): int => 5);

        $selected = $rotator->selectSpec($pool);

        self::assertSame('key-3', $selected->apiKey);
    }

    public function test_get_next_spec_wraps_back_to_first_entry(): void
    {
        $pool = [
            $this->createSpec('key-1'),
            $this->createSpec('key-2'),
        ];

        $rotator = new KeyRotator(static fn (): int => 0);

        $next = $rotator->getNextSpec($pool, 1);

        self::assertSame('key-1', $next->apiKey);
    }

    public function test_select_spec_rotates_evenly_between_two_specs(): void
    {
        $pool = [
            $this->createSpec('key-1'),
            $this->createSpec('key-2'),
        ];

        $rotator = $this->createSequentialRotator();
        $distribution = ['key-1' => 0, 'key-2' => 0];

        for ($i = 0; $i < 100; $i++) {
            $selected = $rotator->selectSpec($pool);
            $distribution[$selected->apiKey]++;
        }

        self::assertSame(50, $distribution['key-1']);
        self::assertSame(50, $distribution['key-2']);
    }

    public function test_select_spec_rotates_evenly_between_three_specs(): void
    {
        $pool = [
            $this->createSpec('key-1'),
            $this->createSpec('key-2'),
            $this->createSpec('key-3'),
        ];

        $rotator = $this->createSequentialRotator();
        $distribution = ['key-1' => 0, 'key-2' => 0, 'key-3' => 0];

        for ($i = 0; $i < 300; $i++) {
            $selected = $rotator->selectSpec($pool);
            $distribution[$selected->apiKey]++;
        }

        self::assertSame(100, $distribution['key-1']);
        self::assertSame(100, $distribution['key-2']);
        self::assertSame(100, $distribution['key-3']);
    }

    public function test_select_spec_rotates_evenly_between_five_specs(): void
    {
        $pool = [
            $this->createSpec('key-1'),
            $this->createSpec('key-2'),
            $this->createSpec('key-3'),
            $this->createSpec('key-4'),
            $this->createSpec('key-5'),
        ];

        $rotator = $this->createSequentialRotator();
        $distribution = [
            'key-1' => 0,
            'key-2' => 0,
            'key-3' => 0,
            'key-4' => 0,
            'key-5' => 0,
        ];

        for ($i = 0; $i < 500; $i++) {
            $selected = $rotator->selectSpec($pool);
            $distribution[$selected->apiKey]++;
        }

        self::assertSame(100, $distribution['key-1']);
        self::assertSame(100, $distribution['key-2']);
        self::assertSame(100, $distribution['key-3']);
        self::assertSame(100, $distribution['key-4']);
        self::assertSame(100, $distribution['key-5']);
    }

    private function createSpec(string $key): ProviderSpec
    {
        return new ProviderSpec(
            provider: 'openai',
            model: 'gpt-4o-mini',
            apiKey: $key,
            solution: ['type' => 'ragie'],
        );
    }

    private function createSequentialRotator(): KeyRotator
    {
        $tick = 0;

        return new KeyRotator(static function () use (&$tick): int {
            return $tick++;
        });
    }
}
