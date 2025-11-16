<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Llm/NeuronAiAdapterTest.php

namespace ParaGra\Tests\Llm;

use InvalidArgumentException;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\Deepseek\Deepseek;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Providers\Mistral\Mistral;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\OpenAILike;
use NeuronAI\Providers\XAI\Grok;
use ParaGra\Llm\NeuronAiAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(NeuronAiAdapter::class)]
final class NeuronAiAdapterTest extends TestCase
{
    public function test_generate_usesInjectedProviderFactory(): void
    {
        $provider = $this->createMock(AIProviderInterface::class);
        $provider->expects($this->once())
            ->method('systemPrompt')
            ->with('You are helpful')
            ->willReturnSelf();

        $provider->expects($this->once())
            ->method('chat')
            ->with($this->callback(function (array $messages): bool {
                self::assertCount(1, $messages);
                self::assertInstanceOf(UserMessage::class, $messages[0]);
                self::assertSame('Explain ParaGra', $messages[0]->getContent());
                return true;
            }))
            ->willReturn(new AssistantMessage('ParaGra explanation'));

        $adapter = new NeuronAiAdapter(
            provider: 'openai',
            model: 'gpt-4o-mini',
            apiKey: 'sk-test',
            parameters: [],
            systemPrompt: 'You are helpful',
            providerFactory: fn (...$args) => $provider
        );

        self::assertSame('ParaGra explanation', $adapter->generate('Explain ParaGra'));
    }

    public function test_generate_trimsArrayResponseAndHonoursSystemOverride(): void
    {
        $provider = $this->createMock(AIProviderInterface::class);
        $provider->expects($this->once())
            ->method('systemPrompt')
            ->with('custom-system')
            ->willReturnSelf();

        $provider->expects($this->once())
            ->method('chat')
            ->willReturn(new AssistantMessage(['Primary answer', 'Secondary']));

        $adapter = new NeuronAiAdapter(
            provider: 'openai',
            model: 'gpt-4o-mini',
            apiKey: 'sk-test',
            providerFactory: fn () => $provider
        );

        self::assertSame('Primary answer', $adapter->generate('Explain ParaGra', ['system_prompt' => 'custom-system']));
    }

    #[DataProvider('nativeProviderData')]
    public function test_resolveProvider_buildsNativeProviders(string $providerName, string $expectedClass): void
    {
        $adapter = new NeuronAiAdapter($providerName, 'model-name', 'api-key');
        $provider = $this->callResolveProvider($adapter);

        self::assertInstanceOf($expectedClass, $provider);
    }

    public static function nativeProviderData(): iterable
    {
        yield ['openai', OpenAI::class];
        yield ['anthropic', Anthropic::class];
        yield ['gemini', Gemini::class];
        yield ['mistral', Mistral::class];
        yield ['xai', Grok::class];
        yield ['deepseek', Deepseek::class];
        yield ['cerebras', OpenAILike::class];
        yield ['groq', OpenAILike::class];
    }

    public function test_resolveProvider_mergesScalarOverridesIntoParameters(): void
    {
        $adapter = new NeuronAiAdapter(
            provider: 'openai',
            model: 'gpt-4o-mini',
            apiKey: 'sk-123',
            parameters: ['temperature' => 0.3]
        );

        /** @var OpenAI $provider */
        $provider = $this->callResolveProvider($adapter, [
            'parameters' => ['top_p' => 0.95],
            'temperature' => 0.8,
            'max_tokens' => 1024,
        ]);

        $parameters = $this->readProtectedProperty($provider, 'parameters');
        self::assertSame(0.8, $parameters['temperature']);
        self::assertSame(1024, $parameters['max_tokens']);
        self::assertSame(0.95, $parameters['top_p']);
    }

    public function test_resolveProvider_rejectsUnknownProviders(): void
    {
        $adapter = new NeuronAiAdapter('unsupported', 'model', 'key');

        $this->expectException(InvalidArgumentException::class);
        $this->callResolveProvider($adapter);
    }

    private function callResolveProvider(NeuronAiAdapter $adapter, array $options = []): AIProviderInterface
    {
        $reflection = new ReflectionClass($adapter);
        $method = $reflection->getMethod('resolveProvider');
        $method->setAccessible(true);

        /** @var AIProviderInterface $provider */
        $provider = $method->invoke($adapter, $options);

        return $provider;
    }

    /**
     * @return array<string, mixed>
     */
    private function readProtectedProperty(object $object, string $property): array
    {
        $reflection = new ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);

        /** @var array<string, mixed> $value */
        $value = $prop->getValue($object);

        return $value;
    }
}
