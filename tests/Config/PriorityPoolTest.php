<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Config/PriorityPoolTest.php

namespace ParaGra\Tests\Config;

use InvalidArgumentException;
use ParaGra\Config\PriorityPool;
use ParaGra\Config\ProviderSpec;
use ParaGra\ProviderCatalog\ProviderDiscovery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

use function dirname;
use function putenv;

#[CoversClass(PriorityPool::class)]
#[UsesClass(ProviderSpec::class)]
final class PriorityPoolTest extends TestCase
{
    public function test_from_array_when_config_valid_then_returns_nested_specs(): void
    {
        $config = [
            [
                [
                    'provider' => 'cerebras',
                    'model' => 'llama-3.3-70b',
                    'api_key' => 'key-1',
                    'solution' => [
                        'type' => 'ragie',
                        'ragie_api_key' => 'ragie-1',
                    ],
                ],
                [
                    'provider' => 'cerebras',
                    'model' => 'llama-3.3-70b',
                    'api_key' => 'key-2',
                    'solution' => [
                        'type' => 'ragie',
                        'ragie_api_key' => 'ragie-2',
                    ],
                ],
            ],
            [
                [
                    'provider' => 'openai',
                    'model' => 'gpt-4o-mini',
                    'api_key' => 'key-3',
                    'solution' => [
                        'type' => 'ragie',
                        'ragie_api_key' => 'ragie-3',
                    ],
                ],
            ],
        ];

        $pool = PriorityPool::fromArray($config);

        self::assertSame(2, $pool->getPoolCount());
        $firstPool = $pool->getPool(0);
        self::assertCount(2, $firstPool);
        self::assertInstanceOf(ProviderSpec::class, $firstPool[0]);
        self::assertSame('key-1', $firstPool[0]->apiKey);

        $secondPool = $pool->getPool(1);
        self::assertCount(1, $secondPool);
        self::assertSame('openai', $secondPool[0]->provider);

        self::assertSame([], $pool->getPool(5));
    }

    public function test_from_array_when_pool_contains_non_array_then_throws(): void
    {
        $config = [
            [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => 'key',
                'solution' => ['type' => 'ragie'],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pool "0" entry "0" must be an array.');

        PriorityPool::fromArray($config);
    }

    public function test_from_array_when_spec_invalid_then_bubbles_exception(): void
    {
        $config = [
            [
                [
                    'provider' => 'openai',
                    'model' => '   ',
                    'api_key' => 'key',
                    'solution' => ['type' => 'ragie'],
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('model');

        PriorityPool::fromArray($config);
    }

    public function test_constructor_when_pool_not_array_then_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pool "0" must be an array.');

        /** @phpstan-ignore-next-line intentionally passing invalid structure */
        new PriorityPool(['not-an-array']);
    }

    public function test_constructor_when_pool_contains_non_provider_spec_then_throws(): void
    {
        $validSpec = new ProviderSpec(
            provider: 'cerebras',
            model: 'llama-3.1',
            apiKey: 'key-1',
            solution: ['type' => 'ragie'],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('contains invalid entry of type "string"');

        /** @phpstan-ignore-next-line intentionally mixed contents */
        new PriorityPool([[$validSpec, 'oops']]);
    }

    public function test_from_array_when_pool_not_array_then_throws(): void
    {
        $config = [
            /** @phpstan-ignore-next-line intentionally invalid */
            'not-a-pool',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pool "0" must be an array of provider specs.');

        PriorityPool::fromArray($config);
    }

    public function test_from_array_when_solution_contains_nested_metadata_then_preserved(): void
    {
        $config = [
            [
                [
                    'provider' => 'cerebras',
                    'model' => 'llama-3.3-70b',
                    'api_key' => 'key-1',
                    'solution' => [
                        'type' => 'ragie',
                        'metadata' => [
                            'tier' => 'free',
                            'notes' => 'rotation',
                        ],
                        'default_options' => [
                            'top_k' => 8,
                            'rerank' => true,
                        ],
                    ],
                ],
            ],
        ];

        $pool = PriorityPool::fromArray($config);
        $specs = $pool->getPool(0);

        self::assertCount(1, $specs);
        self::assertSame('free', $specs[0]->solution['metadata']['tier']);
        self::assertTrue($specs[0]->solution['default_options']['rerank']);
        self::assertSame(8, $specs[0]->solution['default_options']['top_k']);
    }

    public function test_from_array_when_catalog_entry_then_builds_spec_with_overrides(): void
    {
        $catalogPath = dirname(__DIR__, 2) . '/config/providers/catalog.php';
        $catalog = ProviderDiscovery::fromFile($catalogPath);
        putenv('OPENAI_API_KEY=test-openai');

        try {
            $config = [
                [
                    [
                        'catalog' => [
                            'slug' => 'openai',
                            'model_type' => 'generation',
                            'overrides' => [
                                'solution' => [
                                    'metadata' => [
                                        'tier' => 'paid',
                                    ],
                                ],
                            ],
                        ],
                        'latency_tier' => 'medium',
                        'cost_ceiling' => 0.08,
                        'compliance' => ['soc2'],
                    ],
                ],
            ];

            $pool = PriorityPool::fromArray($config, $catalog);
            $specs = $pool->getPool(0);

            self::assertCount(1, $specs);
            self::assertSame('openai', $specs[0]->provider);
            self::assertSame('test-openai', $specs[0]->apiKey);
            self::assertSame('gpt-4o-mini', $specs[0]->model);
            self::assertSame('medium', $specs[0]->solution['metadata']['latency_tier']);
            self::assertSame(0.08, $specs[0]->solution['metadata']['cost_ceiling']);
            self::assertSame(['soc2'], $specs[0]->solution['metadata']['compliance']);
            self::assertSame('paid', $specs[0]->solution['metadata']['tier']);
        } finally {
            putenv('OPENAI_API_KEY');
        }
    }

    public function test_from_array_when_catalog_entry_without_discovery_then_throws(): void
    {
        $config = [
            [
                [
                    'catalog' => [
                        'slug' => 'openai',
                    ],
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Catalog-backed provider entries require a provider catalog');

        PriorityPool::fromArray($config);
    }

    public function test_catalog_entry_metadata_overrides_merge_all_sources(): void
    {
        $expectedSpec = new ProviderSpec(
            provider: 'voyage',
            model: 'voyage-2',
            apiKey: 'voyage-key',
            solution: ['type' => 'embedding']
        );

        $catalog = $this->createMock(ProviderDiscovery::class);
        $catalog->expects(self::once())
            ->method('buildProviderSpec')
            ->with(
                'voyage',
                'embeddings',
                self::callback(function (array $overrides): bool {
                    self::assertArrayHasKey('solution', $overrides);
                    self::assertArrayHasKey('metadata', $overrides['solution']);
                    $metadata = $overrides['solution']['metadata'];

                    self::assertSame('free-final', $metadata['tier']);
                    self::assertSame('final-latency', $metadata['latency_tier']);
                    self::assertSame(0.04, $metadata['cost_ceiling']);
                    self::assertSame(['soc2'], $metadata['compliance']);
                    self::assertSame('spec-override', $metadata['notes']);
                    self::assertSame('us', $metadata['region']);

                    return true;
                })
            )
            ->willReturn($expectedSpec);

        $config = [
            [
                [
                    'catalog' => [
                        'slug' => 'askyoda',
                        'model_type' => 'generation',
                        'metadata' => [
                            'tier' => 'catalog-base',
                            'latency_tier' => 'catalog-latency',
                        ],
                        'metadata_overrides' => [
                            'notes' => 'catalog-note',
                            'region' => 'catalog-region',
                        ],
                    ],
                    'catalog_slug' => 'voyage',
                    'catalog_model_type' => 'embeddings',
                    'catalog_overrides' => [
                        'solution' => [
                            'metadata' => [
                                'cost_ceiling' => 0.12,
                                'latency_tier' => 'override-latency',
                            ],
                        ],
                    ],
                    'metadata_overrides' => [
                        'notes' => 'spec-override',
                        'region' => 'us',
                    ],
                    'tier' => 'free-final',
                    'latency_tier' => 'final-latency',
                    'cost_ceiling' => 0.04,
                    'compliance' => ['soc2'],
                ],
            ],
        ];

        $pool = PriorityPool::fromArray($config, $catalog);
        $specs = $pool->getPool(0);

        self::assertCount(1, $specs);
        self::assertSame($expectedSpec, $specs[0]);
    }

    public function test_catalog_reference_when_slug_blank_then_throws(): void
    {
        $catalog = $this->createStub(ProviderDiscovery::class);

        $config = [
            [
                [
                    'catalog' => [
                        'slug' => '   ',
                    ],
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('slug');

        PriorityPool::fromArray($config, $catalog);
    }
}
