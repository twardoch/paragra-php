<?php

declare(strict_types=1);

// this_file: paragra-php/src/Llm/OpenAiChatClient.php

namespace ParaGra\Llm;

use OpenAI\Contracts\ClientContract as OpenAiClient;
use OpenAI\Contracts\Resources\ChatContract as OpenAiChatResource;
use RuntimeException;

final class OpenAiChatClient
{
    private OpenAiChatConfig $config;

    private OpenAiClient $client;

    public function __construct(OpenAiChatConfig $config, ?OpenAiClient $client = null)
    {
        $this->config = $config;
        $this->client = $client ?? $this->createClient($config);
    }

    public function generateText(string $prompt, ?ChatRequestOptions $options = null): ChatResponse
    {
        $messages = [
            ['role' => 'user', 'content' => $prompt],
        ];

        return $this->generateMessages($messages, $options);
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     */
    public function generateMessages(array $messages, ?ChatRequestOptions $options = null): ChatResponse
    {
        $payload = $this->buildPayload($messages, $options);

        try {
            $response = $this->chatResource()->create($payload);
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to request chat completion: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }

        $text = $response->choices[0]->message->content ?? '';
        $usage = $response->usage;

        $chatUsage = new ChatUsage(
            promptTokens: $usage?->promptTokens ?? 0,
            completionTokens: $usage?->completionTokens ?? 0,
            totalTokens: $usage?->totalTokens ?? 0
        );

        return new ChatResponse($text, $chatUsage, $response);
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @return array<string, mixed>
     */
    private function buildPayload(array $messages, ?ChatRequestOptions $options): array
    {
        $temperature = $options?->temperature ?? $this->config->defaultTemperature;
        $topP = $options?->topP ?? $this->config->defaultTopP;
        $maxTokens = $options?->maxTokens ?? $this->config->defaultMaxTokens;

        $payload = [
            'model' => $options?->model ?? $this->config->model,
            'messages' => $messages,
            'temperature' => $temperature,
            'top_p' => $topP,
        ];

        if ($maxTokens !== null) {
            $payload['max_tokens'] = $maxTokens;
        }

        return $payload;
    }

    private function createClient(OpenAiChatConfig $config): OpenAiClient
    {
        $factory = \OpenAI::factory()->withApiKey($config->apiKey);

        if ($config->baseUrl) {
            $factory = $factory->withBaseUri($config->baseUrl);
        }

        return $factory->make();
    }

    private function chatResource(): OpenAiChatResource
    {
        return $this->client->chat();
    }
}
