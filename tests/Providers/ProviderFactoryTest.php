<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Providers/ProviderFactoryTest.php

namespace ParaGra\Tests\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use ParaGra\Config\ProviderSpec;
use ParaGra\Llm\NeuronAiAdapter;
use ParaGra\Providers\AskYodaProvider;
use ParaGra\Providers\GeminiFileSearchProvider;
use ParaGra\Providers\ProviderFactory;
use ParaGra\Providers\RagieProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ragie\Client as RagieClient;

#[CoversClass(ProviderFactory::class)]
final class ProviderFactoryTest extends TestCase
{
    public function test_createProvider_whenSolutionIsRagie_thenReturnsRagieProvider(): void
    {
        $factory = new ProviderFactory(
            ragieClientFactory: fn (ProviderSpec $spec) => $this->createMock(RagieClient::class),
            httpClientFactory: fn () => new Client(['handler' => HandlerStack::create(new MockHandler())]),
        );

        $provider = $factory->createProvider($this->ragieSpec());
        self::assertInstanceOf(RagieProvider::class, $provider);
    }

    public function test_createProvider_whenSolutionIsGemini_thenReturnsGeminiProvider(): void
    {
        $factory = new ProviderFactory(
            ragieClientFactory: fn () => $this->createMock(RagieClient::class),
            httpClientFactory: fn () => new Client(['handler' => HandlerStack::create(new MockHandler([
                new Response(200, [], json_encode(['candidates' => []], JSON_THROW_ON_ERROR)),
            ]))]),
        );

        $provider = $factory->createProvider($this->geminiSpec());
        self::assertInstanceOf(GeminiFileSearchProvider::class, $provider);
    }

    public function test_createProvider_whenSolutionIsAskYoda_thenReturnsAskYodaProvider(): void
    {
        $factory = new ProviderFactory(
            ragieClientFactory: fn () => $this->createMock(RagieClient::class),
            httpClientFactory: fn () => new Client(['handler' => HandlerStack::create(new MockHandler([
                new Response(200, [], json_encode(['result' => 'ok'], JSON_THROW_ON_ERROR)),
            ]))]),
        );

        $provider = $factory->createProvider($this->askYodaSpec());
        self::assertInstanceOf(AskYodaProvider::class, $provider);
    }

    public function test_createLlmClient_returnsNeuronAdapter(): void
    {
        $factory = new ProviderFactory();
        $adapter = $factory->createLlmClient($this->ragieSpec());
        self::assertInstanceOf(NeuronAiAdapter::class, $adapter);
    }

    private function ragieSpec(): ProviderSpec
    {
        return new ProviderSpec(
            provider: 'openai',
            model: 'gpt-4o-mini',
            apiKey: 'sk-openai',
            solution: [
                'type' => 'ragie',
                'ragie_api_key' => 'ragie-key',
            ],
        );
    }

    private function geminiSpec(): ProviderSpec
    {
        return new ProviderSpec(
            provider: 'gemini',
            model: 'gemini-2.0-flash-exp',
            apiKey: 'ai-key',
            solution: [
                'type' => 'gemini-file-search',
                'vector_store' => 'projects/demo/locations/us/vectorStores/demo',
            ],
        );
    }

    private function askYodaSpec(): ProviderSpec
    {
        return new ProviderSpec(
            provider: 'askyoda',
            model: 'askyoda-default',
            apiKey: 'unused',
            solution: [
                'type' => 'askyoda',
                'askyoda_api_key' => 'edenai-key',
                'project_id' => 'project-1',
            ],
        );
    }
}
