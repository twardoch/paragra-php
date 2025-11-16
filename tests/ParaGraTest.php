<?php

declare(strict_types=1);

// this_file: paragra-php/tests/ParaGraTest.php

namespace ParaGra\Tests;

use InvalidArgumentException;
use ParaGra\Config\PriorityPool;
use ParaGra\Config\ProviderSpec;
use ParaGra\Llm\NeuronAiAdapter;
use ParaGra\Llm\PromptBuilder;
use ParaGra\Moderation\ModerationResult;
use ParaGra\Moderation\ModeratorInterface;
use ParaGra\ParaGra;
use ParaGra\Providers\ProviderFactory;
use ParaGra\Providers\ProviderInterface;
use ParaGra\Response\UnifiedResponse;
use ParaGra\Router\FallbackStrategy;
use ParaGra\Router\KeyRotator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ReflectionClass;

#[CoversClass(ParaGra::class)]
#[UsesClass(PriorityPool::class)]
#[UsesClass(ProviderSpec::class)]
#[UsesClass(FallbackStrategy::class)]
#[UsesClass(KeyRotator::class)]
#[UsesClass(UnifiedResponse::class)]
final class ParaGraTest extends TestCase
{
    public function test_from_config_when_priority_pools_missing_then_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('priority_pools');

        ParaGra::fromConfig([]);
    }

    public function test_retrieve_returns_response_from_primary_pool(): void
    {
        $pool = $this->createPriorityPool();
        $response = UnifiedResponse::fromChunks('cerebras', 'llama-3.3-70b', [
            ['text' => 'Context block'],
        ]);

        $paragra = $this->createParaGra(
            $pool,
            providerMap: [
                'cerebras:llama-3.3-70b' => new StubProvider(
                    provider: 'cerebras',
                    model: 'llama-3.3-70b',
                    response: $response
                ),
            ]
        );

        $result = $paragra->retrieve('What is ParaGra?');

        self::assertSame($response, $result);
    }

    public function test_retrieve_when_primary_pool_fails_then_uses_next_pool(): void
    {
        $pool = $this->createPriorityPool();
        $fallbackResponse = UnifiedResponse::fromChunks('openai', 'gpt-4o-mini', [
            ['text' => 'Fallback context'],
        ]);

        $paragra = $this->createParaGra(
            $pool,
            providerMap: [
                'cerebras:llama-3.3-70b' => new StubProvider(
                    provider: 'cerebras',
                    model: 'llama-3.3-70b',
                    exception: new RuntimeException('rate limited')
                ),
                'openai:gpt-4o-mini' => new StubProvider(
                    provider: 'openai',
                    model: 'gpt-4o-mini',
                    response: $fallbackResponse
                ),
            ]
        );

        $result = $paragra->retrieve('Need fallback');

        self::assertSame($fallbackResponse, $result);
    }

    public function test_retrieve_when_three_pools_then_attempts_each_until_success(): void
    {
        $pool = PriorityPool::fromArray([
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
                    'model' => 'llama-3.3-8b',
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
        ]);

        $callOrder = [];
        $successResponse = UnifiedResponse::fromChunks('openai', 'gpt-4o-mini', [
            ['text' => 'Tier three'],
        ]);

        $paragra = $this->createParaGra(
            $pool,
            providerMap: [
                'cerebras:llama-3.3-70b' => new StubProvider(
                    provider: 'cerebras',
                    model: 'llama-3.3-70b',
                    exception: new RuntimeException('tier one down'),
                    spy: function (string $query, array $options) use (&$callOrder): void {
                        $callOrder[] = 'cerebras:llama-3.3-70b';
                    }
                ),
                'cerebras:llama-3.3-8b' => new StubProvider(
                    provider: 'cerebras',
                    model: 'llama-3.3-8b',
                    exception: new RuntimeException('tier two down'),
                    spy: function (string $query, array $options) use (&$callOrder): void {
                        $callOrder[] = 'cerebras:llama-3.3-8b';
                    }
                ),
                'openai:gpt-4o-mini' => new StubProvider(
                    provider: 'openai',
                    model: 'gpt-4o-mini',
                    response: $successResponse,
                    spy: function (string $query, array $options) use (&$callOrder): void {
                        $callOrder[] = 'openai:gpt-4o-mini';
                    }
                ),
            ]
        );

        $result = $paragra->retrieve('Trigger fallback chain');

        self::assertSame($successResponse, $result);
        self::assertSame(
            ['cerebras:llama-3.3-70b', 'cerebras:llama-3.3-8b', 'openai:gpt-4o-mini'],
            $callOrder
        );
    }

    public function test_retrieve_with_moderation_invokes_moderator_once(): void
    {
        $pool = $this->createPriorityPool();
        $response = UnifiedResponse::fromChunks('cerebras', 'llama-3.3-70b', [
            ['text' => 'Context'],
        ]);

        $moderator = $this->createMock(ModeratorInterface::class);
        $moderator->expects(self::once())
            ->method('moderate')
            ->with('Moderate me')
            ->willReturn(new ModerationResult(false, [], []));

        $paragra = $this->createParaGra(
            $pool,
            providerMap: [
                'cerebras:llama-3.3-70b' => new StubProvider(
                    provider: 'cerebras',
                    model: 'llama-3.3-70b',
                    response: $response
                ),
            ]
        )->withModeration($moderator);

        $paragra->retrieve('Moderate me');
    }

    public function test_answer_builds_prompt_and_calls_llm(): void
    {
        $pool = $this->createPriorityPool();
        $context = UnifiedResponse::fromChunks('cerebras', 'llama-3.3-70b', [
            ['text' => 'Chunk A'],
            ['text' => 'Chunk B'],
        ]);

        $llm = $this->createMock(NeuronAiAdapter::class);
        $llm->expects(self::once())
            ->method('generate')
            ->with(
                self::callback(static function (string $prompt): bool {
                    self::assertStringContainsString('Chunk A', $prompt);
                    self::assertStringContainsString('Chunk B', $prompt);
                    self::assertStringContainsString('Explain ParaGra', $prompt);
                    return true;
                }),
                ['temperature' => 0.2]
            )
            ->willReturn('Final answer');

        $moderator = $this->createMock(ModeratorInterface::class);
        $moderator->expects(self::once())
            ->method('moderate')
            ->with('Explain ParaGra')
            ->willReturn(new ModerationResult(false, [], []));

        $paragra = $this->createParaGra(
            $pool,
            providerMap: [
                'cerebras:llama-3.3-70b' => new StubProvider(
                    provider: 'cerebras',
                    model: 'llama-3.3-70b',
                    response: $context,
                    spy: function (string $query, array $options): void {
                        self::assertSame('Explain ParaGra', $query);
                        self::assertSame(['top_k' => 5], $options);
                    }
                ),
            ],
            llmMap: [
                'cerebras:llama-3.3-70b' => $llm,
            ]
        )->withModeration($moderator);

        $result = $paragra->answer('Explain ParaGra', [
            'retrieval' => ['top_k' => 5],
            'generation' => ['temperature' => 0.2],
        ]);

        self::assertSame('Final answer', $result['answer']);
        self::assertSame($context, $result['context']);
        self::assertSame('cerebras', $result['metadata']['provider']);
        self::assertSame('llama-3.3-70b', $result['metadata']['model']);
    }

    public function test_answer_when_primary_provider_retrieval_fails_then_rotates(): void
    {
        $pool = PriorityPool::fromArray([
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
                    'provider' => 'openai',
                    'model' => 'gpt-4o-mini',
                    'api_key' => 'paid-1',
                    'solution' => ['type' => 'ragie'],
                ],
            ],
        ]);

        $calls = [];

        $fallbackContext = UnifiedResponse::fromChunks('openai', 'gpt-4o-mini', [
            ['text' => 'Fallback context'],
        ]);

        $openaiLlm = $this->createMock(NeuronAiAdapter::class);
        $openaiLlm->expects(self::once())
            ->method('generate')
            ->willReturnCallback(function (string $prompt, array $options) use (&$calls): string {
                $calls[] = 'llm:openai';
                self::assertStringContainsString('Need fallback rotation', $prompt);
                self::assertSame([], $options);
                return 'Fallback answer';
            });

        $paragra = $this->createParaGra(
            $pool,
            providerMap: [
                'cerebras:llama-3.3-70b' => new StubProvider(
                    provider: 'cerebras',
                    model: 'llama-3.3-70b',
                    response: null,
                    exception: new RuntimeException('rate limited'),
                    spy: function (string $query, array $options) use (&$calls): void {
                        $calls[] = 'retrieve:cerebras';
                        self::assertSame('Need fallback rotation', $query);
                        self::assertSame([], $options);
                    }
                ),
                'openai:gpt-4o-mini' => new StubProvider(
                    provider: 'openai',
                    model: 'gpt-4o-mini',
                    response: $fallbackContext,
                    spy: function (string $query, array $options) use (&$calls): void {
                        $calls[] = 'retrieve:openai';
                        self::assertSame('Need fallback rotation', $query);
                        self::assertSame([], $options);
                    }
                ),
            ],
            llmMap: [
                'openai:gpt-4o-mini' => $openaiLlm,
            ]
        );

        $result = $paragra->answer('Need fallback rotation');

        self::assertSame('Fallback answer', $result['answer']);
        self::assertSame($fallbackContext, $result['context']);
        self::assertSame('openai', $result['metadata']['provider']);
        self::assertSame('gpt-4o-mini', $result['metadata']['model']);
        self::assertSame(
            ['retrieve:cerebras', 'retrieve:openai', 'llm:openai'],
            $calls,
        );
    }

    public function test_answer_when_llm_throws_then_next_pool_used(): void
    {
        $pool = PriorityPool::fromArray([
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
                    'provider' => 'openai',
                    'model' => 'gpt-4o-mini',
                    'api_key' => 'paid-1',
                    'solution' => ['type' => 'ragie'],
                ],
            ],
        ]);

        $calls = [];

        $primaryContext = UnifiedResponse::fromChunks('cerebras', 'llama-3.3-70b', [
            ['text' => 'Tier one context'],
        ]);
        $fallbackContext = UnifiedResponse::fromChunks('openai', 'gpt-4o-mini', [
            ['text' => 'Tier two context'],
        ]);

        $failingLlm = $this->createMock(NeuronAiAdapter::class);
        $failingLlm->expects(self::once())
            ->method('generate')
            ->willReturnCallback(function (string $prompt, array $options) use (&$calls): string {
                $calls[] = 'llm:cerebras';
                throw new RuntimeException('llm failure simulated');
            });

        $openaiLlm = $this->createMock(NeuronAiAdapter::class);
        $openaiLlm->expects(self::once())
            ->method('generate')
            ->willReturnCallback(function (string $prompt, array $options) use (&$calls): string {
                $calls[] = 'llm:openai';
                self::assertStringContainsString('LLM fallback question', $prompt);
                self::assertSame([], $options);
                return 'Recovered answer';
            });

        $paragra = $this->createParaGra(
            $pool,
            providerMap: [
                'cerebras:llama-3.3-70b' => new StubProvider(
                    provider: 'cerebras',
                    model: 'llama-3.3-70b',
                    response: $primaryContext,
                    spy: function (string $query, array $options) use (&$calls): void {
                        $calls[] = 'retrieve:cerebras';
                        self::assertSame('LLM fallback question', $query);
                        self::assertSame([], $options);
                    }
                ),
                'openai:gpt-4o-mini' => new StubProvider(
                    provider: 'openai',
                    model: 'gpt-4o-mini',
                    response: $fallbackContext,
                    spy: function (string $query, array $options) use (&$calls): void {
                        $calls[] = 'retrieve:openai';
                        self::assertSame('LLM fallback question', $query);
                        self::assertSame([], $options);
                    }
                ),
            ],
            llmMap: [
                'cerebras:llama-3.3-70b' => $failingLlm,
                'openai:gpt-4o-mini' => $openaiLlm,
            ]
        );

        $result = $paragra->answer('LLM fallback question');

        self::assertSame('Recovered answer', $result['answer']);
        self::assertSame($fallbackContext, $result['context']);
        self::assertSame('openai', $result['metadata']['provider']);
        self::assertSame('gpt-4o-mini', $result['metadata']['model']);
        self::assertSame(
            ['retrieve:cerebras', 'llm:cerebras', 'retrieve:openai', 'llm:openai'],
            $calls,
        );
    }

    public function test_retrieve_rejects_empty_questions(): void
    {
        $paragra = $this->createParaGra($this->createPriorityPool());

        $this->expectException(InvalidArgumentException::class);
        $paragra->retrieve('   ');
    }

    public function test_answer_rejects_non_array_generation_options(): void
    {
        $paragra = $this->createParaGra($this->createPriorityPool());

        $this->expectException(InvalidArgumentException::class);
        $paragra->answer('Explain', ['generation' => 'not-an-array']);
    }

    public function test_answer_rejects_non_array_retrieval_options(): void
    {
        $paragra = $this->createParaGra($this->createPriorityPool());

        $this->expectException(InvalidArgumentException::class);
        $paragra->answer('Explain', ['retrieval' => 'invalid']);
    }

    public function test_from_config_loads_catalog_entries_when_slug_present(): void
    {
        putenv('OPENAI_API_KEY=test-openai');

        $instance = ParaGra::fromConfig([
            'priority_pools' => [
                [
                    [
                        'catalog_slug' => 'openai',
                        'catalog_model_type' => 'generation',
                    ],
                ],
            ],
        ]);

        self::assertInstanceOf(ParaGra::class, $instance);
    }

    public function test_resolveProviderRejectsInvalidResolverResults(): void
    {
        $paragra = new ParaGra(
            pools: $this->createPriorityPool(),
            providerFactory: new ProviderFactory(),
            providerResolver: static fn (): string => 'invalid'
        );

        $ref = new ReflectionClass($paragra);
        $method = $ref->getMethod('resolveProvider');
        $method->setAccessible(true);
        $spec = ProviderSpec::fromArray([
            'provider' => 'cerebras',
            'model' => 'llama-3.3-70b',
            'api_key' => 'key',
            'solution' => ['type' => 'ragie'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $method->invoke($paragra, $spec);
    }

    public function test_resolveLlmRejectsInvalidResolverResults(): void
    {
        $provider = new StubProvider('cerebras', 'llama-3.3-70b');
        $paragra = new ParaGra(
            pools: $this->createPriorityPool(),
            providerFactory: new ProviderFactory(),
            providerResolver: static fn (): ProviderInterface => $provider,
            llmResolver: static fn (): string => 'invalid'
        );

        $ref = new ReflectionClass($paragra);
        $method = $ref->getMethod('resolveLlm');
        $method->setAccessible(true);
        $spec = ProviderSpec::fromArray([
            'provider' => 'cerebras',
            'model' => 'llama-3.3-70b',
            'api_key' => 'key',
            'solution' => ['type' => 'ragie'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $method->invoke($paragra, $spec);
    }

    /**
     * @param array<string, StubProvider> $providerMap
     * @param array<string, NeuronAiAdapter> $llmMap
     */
    private function createParaGra(
        PriorityPool $pool,
        array $providerMap = [],
        array $llmMap = [],
        ?callable $timeProvider = null,
    ): ParaGra {
        $providerResolver = function (ProviderSpec $spec) use ($providerMap): ProviderInterface {
            $key = self::specKey($spec);
            if (!isset($providerMap[$key])) {
                throw new RuntimeException('Missing provider stub for ' . $key);
            }

            return $providerMap[$key];
        };

        $llmResolver = function (ProviderSpec $spec) use ($llmMap): NeuronAiAdapter {
            $key = self::specKey($spec);
            if (!isset($llmMap[$key])) {
                $mock = $this->createMock(NeuronAiAdapter::class);
                $mock->method('generate')->willReturn('unused');
                return $mock;
            }

            return $llmMap[$key];
        };

        $rotator = new KeyRotator($timeProvider ?? static fn (): int => 0);
        $fallback = new FallbackStrategy($pool, $rotator);

        return new ParaGra(
            pools: $pool,
            providerFactory: new ProviderFactory(),
            fallback: $fallback,
            promptBuilder: new PromptBuilder(),
            providerResolver: $providerResolver,
            llmResolver: $llmResolver
        );
    }

    private static function specKey(ProviderSpec $spec): string
    {
        return $spec->provider . ':' . $spec->model;
    }

    private function createPriorityPool(): PriorityPool
    {
        return PriorityPool::fromArray([
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
                    'provider' => 'openai',
                    'model' => 'gpt-4o-mini',
                    'api_key' => 'paid-1',
                    'solution' => ['type' => 'ragie'],
                ],
            ],
        ]);
    }
}

/**
 * @internal Test stub for ProviderInterface
 */
final class StubProvider implements ProviderInterface
{
    /**
     * @var callable(string, array<string, mixed>): void|null
     */
    private $spy;

    /**
     * @param callable(string, array<string, mixed>): void|null $spy
     */
    public function __construct(
        private readonly string $provider,
        private readonly string $model,
        private readonly ?UnifiedResponse $response = null,
        private readonly ?\Throwable $exception = null,
        ?callable $spy = null,
    ) {
        $this->spy = $spy;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getCapabilities(): array
    {
        return ['retrieval'];
    }

    public function supports(string $capability): bool
    {
        return $capability === 'retrieval';
    }

    public function retrieve(string $query, array $options = []): UnifiedResponse
    {
        if ($this->spy !== null) {
            ($this->spy)($query, $options);
        }

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->response ?? UnifiedResponse::fromChunks(
            $this->provider,
            $this->model,
            [['text' => 'stub']]
        );
    }
}
