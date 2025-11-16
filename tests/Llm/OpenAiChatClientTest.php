<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Llm/OpenAiChatClientTest.php

namespace ParaGra\Tests\Llm;

use OpenAI\Contracts\ClientContract as OpenAiClient;
use OpenAI\Contracts\Resources\ChatContract as OpenAiChatResource;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Responses\Meta\MetaInformation;
use ParaGra\Llm\ChatRequestOptions;
use ParaGra\Llm\OpenAiChatClient;
use ParaGra\Llm\OpenAiChatConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class OpenAiChatClientTest extends TestCase
{
    public function test_generate_text_sends_prompt_and_returns_text(): void
    {
        $config = new OpenAiChatConfig(
            apiKey: 'test-key',
            model: 'test-model',
            baseUrl: 'https://example.test/v1',
            defaultTemperature: 0.5,
            defaultTopP: 0.9,
            defaultMaxTokens: 256,
        );

        $chatResponse = $this->fakeCreateResponse('Hello there', 12, 8);

        /** @var OpenAiChatResource&MockObject $chatResource */
        $chatResource = $this->createMock(OpenAiChatResource::class);
        $chatResource
            ->expects(self::once())
            ->method('create')
            ->with($this->callback(function (array $payload): bool {
                self::assertSame('test-model', $payload['model']);
                self::assertSame([
                    ['role' => 'user', 'content' => 'hi'],
                ], $payload['messages']);
                self::assertSame(0.5, $payload['temperature']);
                self::assertSame(0.9, $payload['top_p']);
                self::assertSame(256, $payload['max_tokens']);

                return true;
            }))
            ->willReturn($chatResponse);

        /** @var OpenAiClient&MockObject $client */
        $client = $this->createMock(OpenAiClient::class);
        $client
            ->expects(self::once())
            ->method('chat')
            ->willReturn($chatResource);

        $chatClient = new OpenAiChatClient($config, $client);
        $result = $chatClient->generateText('hi');

        self::assertSame('Hello there', $result->getText());
        self::assertSame(12, $result->getUsage()->promptTokens);
        self::assertSame(8, $result->getUsage()->completionTokens);
        self::assertSame(20, $result->getUsage()->totalTokens);
    }

    public function test_generate_messages_applies_overrides(): void
    {
        $config = new OpenAiChatConfig(
            apiKey: 'xyz',
            model: 'base-model',
            defaultTemperature: 0.4,
            defaultTopP: 0.8,
        );

        $chatResponse = $this->fakeCreateResponse('Custom answer', 20, 5);

        /** @var OpenAiChatResource&MockObject $chatResource */
        $chatResource = $this->createMock(OpenAiChatResource::class);
        $chatResource
            ->expects(self::once())
            ->method('create')
            ->with($this->callback(function (array $payload): bool {
                self::assertSame('override-model', $payload['model']);
                self::assertSame(0.2, $payload['temperature']);
                self::assertSame(0.5, $payload['top_p']);
                self::assertSame(128, $payload['max_tokens']);
                self::assertCount(2, $payload['messages']);

                return true;
            }))
            ->willReturn($chatResponse);

        /** @var OpenAiClient&MockObject $client */
        $client = $this->createMock(OpenAiClient::class);
        $client
            ->expects(self::once())
            ->method('chat')
            ->willReturn($chatResource);

        $chatClient = new OpenAiChatClient($config, $client);

        $options = new ChatRequestOptions(
            model: 'override-model',
            temperature: 0.2,
            topP: 0.5,
            maxTokens: 128,
        );

        $messages = [
            ['role' => 'system', 'content' => 'Instruction'],
            ['role' => 'user', 'content' => 'Question'],
        ];

        $result = $chatClient->generateMessages($messages, $options);

        self::assertSame('Custom answer', $result->getText());
        self::assertSame(20, $result->getUsage()->promptTokens);
        self::assertSame(5, $result->getUsage()->completionTokens);
        self::assertSame(25, $result->getUsage()->totalTokens);
    }

    private function fakeCreateResponse(string $content, int $promptTokens, int $completionTokens): CreateResponse
    {
        return CreateResponse::from([
            'id' => 'chatcmpl-test',
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'test-model',
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => $content,
                    'function_call' => null,
                    'tool_calls' => null,
                ],
                'logprobs' => null,
                'finish_reason' => 'stop',
            ]],
            'usage' => [
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $promptTokens + $completionTokens,
            ],
        ], MetaInformation::from([]));
    }
}
