<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Router/FallbackStrategyTest.php

namespace ParaGra\Tests\Router;

use ParaGra\Config\PriorityPool;
use ParaGra\Config\ProviderSpec;
use ParaGra\Planner\PoolBuilder;
use ParaGra\Router\FallbackStrategy;
use ParaGra\Router\KeyRotator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(FallbackStrategy::class)]
#[UsesClass(PriorityPool::class)]
#[UsesClass(ProviderSpec::class)]
#[UsesClass(KeyRotator::class)]
final class FallbackStrategyTest extends TestCase
{
    public function test_execute_when_free_pool_rotates_all_specs_before_fallback(): void
    {
        $config = [
            [
                $this->providerConfig('free-1', ['plan' => PoolBuilder::PRESET_FREE]),
                $this->providerConfig('free-2', ['plan' => PoolBuilder::PRESET_FREE]),
                $this->providerConfig('free-3', ['plan' => PoolBuilder::PRESET_FREE]),
            ],
            [
                $this->providerConfig('paid-1', ['plan' => PoolBuilder::PRESET_HYBRID]),
            ],
        ];

        $pools = PriorityPool::fromArray($config);
        $rotator = new KeyRotator(static fn (): int => 0);
        $logs = [];

        $strategy = new FallbackStrategy(
            $pools,
            $rotator,
            familyPolicies: [],
            logger: static function (string $message) use (&$logs): void {
                $logs[] = $message;
            }
        );

        $attempts = [];
        $result = $strategy->execute(function (ProviderSpec $spec) use (&$attempts): string {
            $attempts[] = $spec->apiKey;
            if (count($attempts) < 3) {
                throw new RuntimeException('fail-' . $spec->apiKey);
            }

            return 'ok-' . $spec->apiKey;
        });

        self::assertSame('ok-free-3', $result);
        self::assertSame(['free-1', 'free-2', 'free-3'], $attempts);
        self::assertCount(2, $logs, 'Failures should be logged for each rotated key.');
    }

    public function test_execute_when_policy_limits_attempts_then_moves_to_next_pool(): void
    {
        $config = [
            [
                $this->providerConfig('hybrid-1', ['plan' => PoolBuilder::PRESET_HYBRID]),
                $this->providerConfig('hybrid-2', ['plan' => PoolBuilder::PRESET_HYBRID]),
                $this->providerConfig('hybrid-3', ['plan' => PoolBuilder::PRESET_HYBRID]),
            ],
            [
                $this->providerConfig('free-1', ['plan' => PoolBuilder::PRESET_FREE]),
            ],
        ];

        $pools = PriorityPool::fromArray($config);
        $rotator = new KeyRotator(static fn (): int => 0);

        $strategy = new FallbackStrategy(
            $pools,
            $rotator,
            familyPolicies: [
                'hybrid' => ['max_attempts' => 1],
            ]
        );

        $attempts = [];
        $result = $strategy->execute(function (ProviderSpec $spec) use (&$attempts): string {
            $attempts[] = $spec->apiKey;
            if ($spec->apiKey === 'hybrid-1') {
                throw new RuntimeException('fail-hybrid-1');
            }

            return 'ok-' . $spec->apiKey;
        });

        self::assertSame('ok-free-1', $result);
        self::assertSame(['hybrid-1', 'free-1'], $attempts);
    }

    public function test_execute_when_hosted_family_defaults_to_single_attempt(): void
    {
        $config = [
            [
                $this->providerConfig('hosted-1', ['plan' => PoolBuilder::PRESET_HOSTED]),
                $this->providerConfig('hosted-2', ['plan' => PoolBuilder::PRESET_HOSTED]),
            ],
            [
                $this->providerConfig('hybrid-1', ['plan' => PoolBuilder::PRESET_HYBRID]),
            ],
        ];

        $pools = PriorityPool::fromArray($config);
        $rotator = new KeyRotator(static fn (): int => 0);

        $strategy = new FallbackStrategy($pools, $rotator);

        $attempts = [];
        $result = $strategy->execute(function (ProviderSpec $spec) use (&$attempts): string {
            $attempts[] = $spec->apiKey;
            if ($spec->apiKey === 'hosted-1') {
                throw new RuntimeException('hosted-down');
            }

            return 'ok-' . $spec->apiKey;
        });

        self::assertSame('ok-hybrid-1', $result);
        self::assertSame(['hosted-1', 'hybrid-1'], $attempts);
    }

    public function test_execute_when_first_pool_succeeds_then_does_not_fallback(): void
    {
        $config = [
            [
                [
                    'provider' => 'cerebras',
                    'model' => 'llama-3.3-70b',
                    'api_key' => 'free',
                    'solution' => ['type' => 'ragie'],
                ],
            ],
            [
                [
                    'provider' => 'openai',
                    'model' => 'gpt-4o-mini',
                    'api_key' => 'paid',
                    'solution' => ['type' => 'ragie'],
                ],
            ],
        ];

        $pools = PriorityPool::fromArray($config);
        $rotator = new KeyRotator(static fn (): int => 0);

        $strategy = new FallbackStrategy($pools, $rotator);

        $calls = 0;
        $result = $strategy->execute(static function (ProviderSpec $spec) use (&$calls): string {
            $calls++;
            return 'ok-' . $spec->apiKey;
        });

        self::assertSame('ok-free', $result);
        self::assertSame(1, $calls);
    }

    public function test_execute_when_first_pool_fails_then_tries_next_pool(): void
    {
        $config = [
            [
                [
                    'provider' => 'cerebras',
                    'model' => 'llama-3.3-70b',
                    'api_key' => 'free',
                    'solution' => ['type' => 'ragie'],
                ],
            ],
            [
                [
                    'provider' => 'openai',
                    'model' => 'gpt-4o-mini',
                    'api_key' => 'paid',
                    'solution' => ['type' => 'ragie'],
                ],
            ],
        ];

        $pools = PriorityPool::fromArray($config);
        $rotator = new KeyRotator(static fn (): int => 0);

        $strategy = new FallbackStrategy($pools, $rotator);

        $calls = 0;
        $result = $strategy->execute(function (ProviderSpec $spec) use (&$calls): string {
            $calls++;
            if ($spec->apiKey === 'free') {
                throw new RuntimeException('rate limited');
            }

            return 'ok-' . $spec->apiKey;
        });

        self::assertSame('ok-paid', $result);
        self::assertSame(2, $calls);
    }

    public function test_execute_when_all_pools_fail_then_throws(): void
    {
        $config = [
            [
                [
                    'provider' => 'cerebras',
                    'model' => 'llama-3.3-70b',
                    'api_key' => 'free',
                    'solution' => ['type' => 'ragie'],
                ],
            ],
        ];

        $pools = PriorityPool::fromArray($config);
        $rotator = new KeyRotator(static fn (): int => 0);

        $strategy = new FallbackStrategy($pools, $rotator);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('All priority pools exhausted');

        $strategy->execute(static function (): never {
            throw new RuntimeException('down');
        });
    }

    public function test_execute_when_three_pools_then_attempts_each_until_success(): void
    {
        $config = [
            [
                [
                    'provider' => 'cerebras',
                    'model' => 'llama-3.3-70b',
                    'api_key' => 'free-1',
                    'solution' => ['type' => 'ragie'],
                ],
            ],
            [
                [
                    'provider' => 'cerebras',
                    'model' => 'llama-3.3-70b',
                    'api_key' => 'free-2',
                    'solution' => ['type' => 'ragie'],
                ],
            ],
            [
                [
                    'provider' => 'openai',
                    'model' => 'gpt-4o-mini',
                    'api_key' => 'paid-2',
                    'solution' => ['type' => 'ragie'],
                ],
            ],
        ];

        $pools = PriorityPool::fromArray($config);
        $strategy = new FallbackStrategy($pools, new KeyRotator(static fn (): int => 0));

        $attempts = [];

        $result = $strategy->execute(function (ProviderSpec $spec) use (&$attempts): string {
            $attempts[] = $spec->apiKey;
            if ($spec->apiKey !== 'paid-2') {
                throw new RuntimeException('fail-' . $spec->apiKey);
            }

            return 'ok-' . $spec->apiKey;
        });

        self::assertSame('ok-paid-2', $result);
        self::assertSame(['free-1', 'free-2', 'paid-2'], $attempts);
    }

    public function test_execute_when_three_pools_all_fail_then_previous_exception_retained(): void
    {
        $config = [
            [
                [
                    'provider' => 'cerebras',
                    'model' => 'llama-3.3-70b',
                    'api_key' => 'free-1',
                    'solution' => ['type' => 'ragie'],
                ],
            ],
            [
                [
                    'provider' => 'cerebras',
                    'model' => 'llama-3.3-70b',
                    'api_key' => 'free-2',
                    'solution' => ['type' => 'ragie'],
                ],
            ],
            [
                [
                    'provider' => 'openai',
                    'model' => 'gpt-4o-mini',
                    'api_key' => 'paid-2',
                    'solution' => ['type' => 'ragie'],
                ],
            ],
        ];

        $pools = PriorityPool::fromArray($config);
        $strategy = new FallbackStrategy($pools, new KeyRotator(static fn (): int => 0));

        $attempts = [];

        try {
            $strategy->execute(function (ProviderSpec $spec) use (&$attempts): never {
                $attempts[] = $spec->apiKey;
                throw new RuntimeException('fail-' . $spec->apiKey);
            });
            self::fail('Expected exception not thrown');
        } catch (RuntimeException $exception) {
            self::assertSame('All priority pools exhausted', $exception->getMessage());
            $previous = $exception->getPrevious();
            self::assertInstanceOf(RuntimeException::class, $previous);
            self::assertSame('fail-paid-2', $previous->getMessage());
            self::assertSame(['free-1', 'free-2', 'paid-2'], $attempts);
        }
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function providerConfig(string $apiKey, array $metadata = []): array
    {
        return [
            'provider' => 'cerebras',
            'model' => 'llama-3.3-70b',
            'api_key' => $apiKey,
            'solution' => [
                'type' => 'ragie',
                'metadata' => $metadata,
            ],
        ];
    }
}
