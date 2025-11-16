<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Providers/GeminiFileSearchProviderTest.php

namespace ParaGra\Tests\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use ParaGra\Config\ProviderSpec;
use ParaGra\Providers\GeminiFileSearchProvider;
use ParaGra\Response\UnifiedResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GeminiFileSearchProvider::class)]
final class GeminiFileSearchProviderTest extends TestCase
{
    public function test_retrieve_whenSearchEntriesPresent_thenReturnsChunks(): void
    {
        $payload = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Answer from Gemini'],
                        ],
                    ],
                    'groundingMetadata' => [
                        'searchEntries' => [
                            [
                                'score' => 0.91,
                                'chunk' => [
                                    'chunkId' => 'chunk-1',
                                    'content' => [
                                        'parts' => [
                                            ['text' => 'First part'],
                                            ['text' => 'Second part'],
                                        ],
                                    ],
                                    'source' => 'projects/demo',
                                ],
                                'uri' => 'https://example.com/doc1',
                                'title' => 'Doc 1',
                            ],
                        ],
                    ],
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 50,
                'candidatesTokenCount' => 120,
                'totalTokenCount' => 170,
            ],
        ];

        $handler = new MockHandler([new Response(200, [], json_encode($payload, JSON_THROW_ON_ERROR))]);
        $client = new Client(['handler' => HandlerStack::create($handler)]);

        $provider = new GeminiFileSearchProvider(
            $this->spec(),
            $client,
            [
                'vector_store' => 'projects/demo/locations/us/vectorStores/myStore',
                'api_key' => 'api-key',
            ]
        );

        $response = $provider->retrieve('Summarize ParaGra');
        self::assertInstanceOf(UnifiedResponse::class, $response);
        self::assertSame(['First part Second part'], $response->getChunkTexts());
        self::assertSame(50, $response->getUsage()['prompt_tokens'] ?? null);
    }

    public function test_retrieve_whenVectorStoreArray_thenPayloadUsesProvidedName(): void
    {
        $payload = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Answer'],
                        ],
                    ],
                ],
            ],
        ];

        $history = [];
        $handler = new MockHandler([new Response(200, [], json_encode($payload, JSON_THROW_ON_ERROR))]);
        $stack = HandlerStack::create($handler);
        $stack->push(Middleware::history($history));
        $client = new Client(['handler' => $stack]);

        $provider = new GeminiFileSearchProvider(
            $this->spec(),
            $client,
            [
                'vector_store' => ['datastore' => 'fileSearchStores/demo-store'],
                'api_key' => 'api-key',
            ]
        );

        $provider->retrieve('Summarize ParaGra');

        self::assertNotEmpty($history);
        $body = (string) $history[0]['request']->getBody();
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(
            'fileSearchStores/demo-store',
            $data['toolConfig']['fileSearch']['vectorStore'][0]['name'] ?? null
        );
    }

    public function test_retrieve_withoutVectorStoreConfig_throws(): void
    {
        $spec = new ProviderSpec(
            provider: 'gemini',
            model: 'gemini-2.0-flash-exp',
            apiKey: 'api-key',
            solution: ['type' => 'gemini-file-search'],
        );

        $provider = new GeminiFileSearchProvider(
            $spec,
            new Client(['handler' => HandlerStack::create(new MockHandler([]))]),
            ['api_key' => 'api-key']
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('vector_store');

        $provider->retrieve('Summarize ParaGra');
    }

    private function spec(): ProviderSpec
    {
        return new ProviderSpec(
            provider: 'gemini',
            model: 'gemini-2.0-flash-exp',
            apiKey: 'api-key',
            solution: [
                'type' => 'gemini-file-search',
                'vector_store' => 'projects/demo/locations/us/vectorStores/myStore',
            ],
        );
    }
}
