<?php

declare(strict_types=1);

// this_file: paragra-php/src/Llm/NeuronAiAdapter.php

namespace ParaGra\Llm;

use InvalidArgumentException;
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

use function array_replace;
use function is_array;
use function is_callable;
use function sprintf;
use function strtolower;

/**
 * Thin wrapper around neuron-ai providers that exposes a simplified generate() API.
 */
final class NeuronAiAdapter
{
    public function __construct(
        private readonly string $provider,
        private readonly string $model,
        private readonly string $apiKey,
        private readonly array $parameters = [],
        private readonly ?string $systemPrompt = null,
        private readonly mixed $providerFactory = null,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function generate(string $prompt, array $options = []): string
    {
        $provider = $this->resolveProvider($options);
        $systemPrompt = $options['system_prompt'] ?? $this->systemPrompt;
        $provider->systemPrompt($systemPrompt);

        /** @var Message $response */
        $response = $provider->chat([new UserMessage($prompt)]);
        $content = $response->getContent();

        if (is_array($content)) {
            $content = $content[0] ?? '';
        }

        return (string) $content;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolveProvider(array $options): AIProviderInterface
    {
        if (is_callable($this->providerFactory)) {
            return ($this->providerFactory)(
                $this->provider,
                $this->model,
                $this->apiKey,
                $this->parameters,
                $options
            );
        }

        $parameters = array_replace(
            $this->parameters,
            $options['parameters'] ?? [],
            $this->extractScalarOverrides($options)
        );

        return match (strtolower($this->provider)) {
            'openai' => new OpenAI($this->apiKey, $this->model, $parameters),
            'anthropic' => $this->createAnthropicProvider($parameters),
            'gemini' => new Gemini($this->apiKey, $this->model, $parameters),
            'mistral' => new Mistral($this->apiKey, $this->model, $parameters),
            'xai' => new Grok($this->apiKey, $this->model, $parameters),
            'deepseek' => new Deepseek($this->apiKey, $this->model, $parameters),
            'cerebras' => $this->createOpenAiLike('https://api.cerebras.ai/v1/', $parameters),
            'groq' => $this->createOpenAiLike('https://api.groq.com/openai/v1/', $parameters),
            default => throw new InvalidArgumentException(sprintf('Unsupported LLM provider "%s".', $this->provider)),
        };
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function extractScalarOverrides(array $options): array
    {
        $overrides = [];

        if (isset($options['temperature'])) {
            $overrides['temperature'] = (float) $options['temperature'];
        }

        if (isset($options['max_tokens'])) {
            $overrides['max_tokens'] = (int) $options['max_tokens'];
        }

        return $overrides;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function createOpenAiLike(string $baseUri, array $parameters): OpenAILike
    {
        return new OpenAILike(
            $baseUri,
            $this->apiKey,
            $this->model,
            $parameters
        );
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function createAnthropicProvider(array $parameters): Anthropic
    {
        $anthropicParameters = $parameters;
        $version = (string) ($anthropicParameters['anthropic_version'] ?? '2023-06-01');
        unset($anthropicParameters['anthropic_version']);

        $maxTokens = (int) ($anthropicParameters['max_tokens'] ?? 8192);

        return new Anthropic(
            $this->apiKey,
            $this->model,
            $version,
            $maxTokens,
            $anthropicParameters
        );
    }
}
