<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Providers/AskYodaProviderTest.php

namespace ParaGra\Tests\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use ParaGra\Config\ProviderSpec;
use ParaGra\Providers\AskYodaProvider;
use ParaGra\Response\UnifiedResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AskYodaProvider::class)]
final class AskYodaProviderTest extends TestCase
{
    public function test_retrieve_whenPayloadPresent_thenReturnsChunkTexts(): void
    {
        $handler = new MockHandler([
            new Response(200, [], json_encode([
                'result' => 'This is the fallback answer from AskYoda.',
                'cost' => 0.0032,
                'llm_provider' => 'google',
                'llm_model' => 'gemini-2.0-flash-exp',
                'usage' => [
                    'input_tokens' => 120,
                    'output_tokens' => 80,
                    'total_tokens' => 200,
                ],
                'chunks' => [
                    [
                        'chunk_id' => 'alpha',
                        'payload' => ['text' => 'Chunk alpha'],
                        'metadata' => ['source' => 'doc-1'],
                    ],
                    [
                        'chunk_id' => 'beta',
                        'payload' => ['content' => 'Chunk beta text'],
                        'metadata' => ['source' => 'doc-2'],
                    ],
                ],
            ])),
        ]);

        $client = new Client(['handler' => HandlerStack::create($handler)]);
        $provider = new AskYodaProvider(
            $this->spec(),
            $client,
            [
                'default_options' => ['k' => 8],
                'llm' => [
                    'provider' => 'google',
                    'model' => 'gemini-2.0-flash-exp',
                ],
            ]
        );

        $response = $provider->retrieve('Explain ParaGra');
        self::assertInstanceOf(UnifiedResponse::class, $response);
        self::assertSame(['Chunk alpha', 'Chunk beta text'], $response->getChunkTexts());
        self::assertSame(120, $response->getUsage()['input_tokens'] ?? null);
        self::assertEquals(0.0032, $response->getCost()['amount'] ?? null);
    }

    public function test_retrieve_whenOnlyChunkIds_thenBuildsPlaceholders(): void
    {
        $handler = new MockHandler([
            new Response(200, [], json_encode([
                'result' => 'Fallback result',
                'chunks_ids' => ['a', 'b'],
            ])),
        ]);
        $client = new Client(['handler' => HandlerStack::create($handler)]);
        $provider = new AskYodaProvider($this->spec(), $client);

        $response = $provider->retrieve('  Hello  ');
        self::assertSame([
            'Chunk a',
            'Chunk b',
        ], $response->getChunkTexts());
    }

    private function spec(): ProviderSpec
    {
        return new ProviderSpec(
            provider: 'askyoda',
            model: 'edenai-askyoda',
            apiKey: 'unused',
            solution: [
                'type' => 'askyoda',
                'askyoda_api_key' => 'api-key',
                'project_id' => 'project-123',
            ],
        );
    }
}
