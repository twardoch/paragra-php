<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Llm/AskYodaClientTest.php

namespace ParaGra\Tests\Llm;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use ParaGra\Llm\AskYodaClient;
use ParaGra\Llm\AskYodaResponse;
use PHPUnit\Framework\TestCase;

final class AskYodaClientTest extends TestCase
{
    public function test_ask_returns_response(): void
    {
        $responseData = [
            'cost' => 0.001611,
            'result' => 'This is the answer',
            'llm_provider' => 'google',
            'llm_model' => 'gemini-2.0-flash-exp',
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
                'total_tokens' => 150,
            ],
            'chunks_ids' => ['id1', 'id2', 'id3'],
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($responseData) ?: ''),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new HttpClient(['handler' => $handlerStack]);

        $client = new AskYodaClient(
            'test-api-key',
            'test-project-id',
            'google',
            'gemini-2.0-flash-exp',
            $httpClient
        );

        $response = $client->ask('What is RAG?');

        self::assertInstanceOf(AskYodaResponse::class, $response);
        self::assertSame('This is the answer', $response->getResult());
        self::assertSame(0.001611, $response->getCost());
        self::assertSame('google', $response->getLlmProvider());
        self::assertSame(3, $response->getChunkCount());
    }

    public function test_ask_uses_custom_parameters(): void
    {
        $responseData = [
            'cost' => 0.002,
            'result' => 'Custom answer',
            'llm_provider' => 'openai',
            'llm_model' => 'gpt-4',
            'usage' => ['input_tokens' => 200, 'output_tokens' => 100, 'total_tokens' => 300],
            'chunks_ids' => ['id1'],
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($responseData) ?: ''),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new HttpClient(['handler' => $handlerStack]);

        $client = new AskYodaClient(
            'test-api-key',
            'test-project-id',
            'openai',
            'gpt-4',
            $httpClient
        );

        $response = $client->ask(
            query: 'Test query',
            k: 5,
            minScore: 0.5,
            temperature: 0.7,
            maxTokens: 500
        );

        self::assertInstanceOf(AskYodaResponse::class, $response);
        self::assertSame('Custom answer', $response->getResult());
    }

    public function test_ask_throws_on_invalid_json(): void
    {
        $mock = new MockHandler([
            new Response(200, [], 'invalid json'),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new HttpClient(['handler' => $handlerStack]);

        $client = new AskYodaClient(
            'test-api-key',
            'test-project-id',
            httpClient: $httpClient
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to decode AskYoda response');

        $client->ask('Test query');
    }

    public function test_from_env_throws_without_api_key(): void
    {
        $originalKey = getenv('EDENAI_API_KEY');
        $originalProject = getenv('EDENAI_ASKYODA_PROJECT');

        putenv('EDENAI_API_KEY');
        putenv('EDENAI_ASKYODA_PROJECT');
        unset($_ENV['EDENAI_API_KEY'], $_ENV['EDENAI_ASKYODA_PROJECT']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('EDENAI_API_KEY environment variable is required');

        try {
            AskYodaClient::fromEnv();
        } finally {
            if ($originalKey !== false) {
                putenv("EDENAI_API_KEY={$originalKey}");
            }
            if ($originalProject !== false) {
                putenv("EDENAI_ASKYODA_PROJECT={$originalProject}");
            }
        }
    }

    public function test_from_env_throws_without_project_id(): void
    {
        $originalKey = getenv('EDENAI_API_KEY');
        $originalProject = getenv('EDENAI_ASKYODA_PROJECT');

        putenv('EDENAI_API_KEY=test-key');
        putenv('EDENAI_ASKYODA_PROJECT');
        $_ENV['EDENAI_API_KEY'] = 'test-key';
        unset($_ENV['EDENAI_ASKYODA_PROJECT']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('EDENAI_ASKYODA_PROJECT environment variable is required');

        try {
            AskYodaClient::fromEnv();
        } finally {
            if ($originalKey !== false) {
                putenv("EDENAI_API_KEY={$originalKey}");
            } else {
                putenv('EDENAI_API_KEY');
            }
            if ($originalProject !== false) {
                putenv("EDENAI_ASKYODA_PROJECT={$originalProject}");
            }
            unset($_ENV['EDENAI_API_KEY'], $_ENV['EDENAI_ASKYODA_PROJECT']);
        }
    }

    public function test_from_env_uses_defaults(): void
    {
        putenv('EDENAI_API_KEY=test-key');
        putenv('EDENAI_ASKYODA_PROJECT=test-project');
        putenv('EDENAI_LLM_PROVIDER');
        putenv('EDENAI_LLM_MODEL');

        try {
            $client = AskYodaClient::fromEnv();
            self::assertInstanceOf(AskYodaClient::class, $client);
        } finally {
            putenv('EDENAI_API_KEY');
            putenv('EDENAI_ASKYODA_PROJECT');
        }
    }

    public function test_from_env_uses_custom_provider_and_model(): void
    {
        putenv('EDENAI_API_KEY=test-key');
        putenv('EDENAI_ASKYODA_PROJECT=test-project');
        putenv('EDENAI_LLM_PROVIDER=openai');
        putenv('EDENAI_LLM_MODEL=gpt-4');

        try {
            $client = AskYodaClient::fromEnv();
            self::assertInstanceOf(AskYodaClient::class, $client);
        } finally {
            putenv('EDENAI_API_KEY');
            putenv('EDENAI_ASKYODA_PROJECT');
            putenv('EDENAI_LLM_PROVIDER');
            putenv('EDENAI_LLM_MODEL');
        }
    }
}
