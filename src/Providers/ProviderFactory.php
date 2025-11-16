<?php

declare(strict_types=1);

// this_file: paragra-php/src/Providers/ProviderFactory.php

namespace ParaGra\Providers;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface;
use InvalidArgumentException;
use ParaGra\Config\ProviderSpec;
use ParaGra\Llm\NeuronAiAdapter;
use Ragie\Client as RagieClient;

use function array_replace;
use function is_string;
use function sprintf;
use function strtolower;
use function trim;

final class ProviderFactory
{
    /**
     * @var callable(ProviderSpec): RagieClient
     */
    private $ragieClientFactory;

    /**
     * @var callable(array<string, mixed>): ClientInterface
     */
    private $httpClientFactory;

    /**
     * @var callable(ProviderSpec): NeuronAiAdapter
     */
    private $neuronFactory;

    /**
     * @param callable(ProviderSpec): RagieClient|null $ragieClientFactory
     * @param callable(array<string, mixed>): ClientInterface|null $httpClientFactory
     * @param callable(ProviderSpec): NeuronAiAdapter|null $neuronFactory
     */
    public function __construct(
        ?callable $ragieClientFactory = null,
        ?callable $httpClientFactory = null,
        ?callable $neuronFactory = null,
    ) {
        $this->ragieClientFactory = $ragieClientFactory ?? static function (ProviderSpec $spec): RagieClient {
            $solution = $spec->solution;
            $apiKey = $solution['ragie_api_key'] ?? $solution['api_key'] ?? null;
            if (!is_string($apiKey) || trim($apiKey) === '') {
                throw new InvalidArgumentException('Ragie provider requires a ragie_api_key in solution config.');
            }

            $baseUrl = $solution['ragie_base_url'] ?? null;

            return new RagieClient(
                $apiKey,
                is_string($baseUrl) ? $baseUrl : null
            );
        };

        $this->httpClientFactory = $httpClientFactory ?? static fn (array $config = []): ClientInterface => new HttpClient(array_replace([
            'timeout' => 30,
        ], $config));

        $this->neuronFactory = $neuronFactory ?? static fn (ProviderSpec $spec): NeuronAiAdapter => new NeuronAiAdapter(
            provider: $spec->provider,
            model: $spec->model,
            apiKey: $spec->apiKey,
            parameters: $spec->solution['llm_parameters'] ?? [],
            systemPrompt: $spec->solution['system_prompt'] ?? null,
        );
    }

    public function createProvider(ProviderSpec $spec): ProviderInterface
    {
        $type = strtolower((string) ($spec->solution['type'] ?? $spec->provider));

        return match ($type) {
            'ragie' => $this->createRagieProvider($spec),
            'gemini-file-search', 'gemini_file_search' => $this->createGeminiProvider($spec),
            'askyoda' => $this->createAskYodaProvider($spec),
            default => throw new InvalidArgumentException(sprintf('Unsupported provider solution "%s".', $type)),
        };
    }

    public function createLlmClient(ProviderSpec $spec): NeuronAiAdapter
    {
        return ($this->neuronFactory)($spec);
    }

    private function createRagieProvider(ProviderSpec $spec): RagieProvider
    {
        $client = ($this->ragieClientFactory)($spec);

        return new RagieProvider(
            $spec,
            $client,
            [
                'default_options' => $spec->solution['default_options'] ?? [],
                'metadata' => $spec->solution['metadata'] ?? [],
            ]
        );
    }

    private function createGeminiProvider(ProviderSpec $spec): GeminiFileSearchProvider
    {
        $client = ($this->httpClientFactory)([
            'base_uri' => 'https://generativelanguage.googleapis.com',
            'timeout' => 30,
        ]);

        return new GeminiFileSearchProvider(
            $spec,
            $client,
            [
                'vector_store' => $spec->solution['vector_store'] ?? null,
                'generation' => $spec->solution['generation'] ?? [],
                'safety' => $spec->solution['safety'] ?? [],
                'metadata' => $spec->solution['metadata'] ?? [],
                'api_key' => $spec->solution['api_key'] ?? $spec->apiKey,
            ]
        );
    }

    private function createAskYodaProvider(ProviderSpec $spec): AskYodaProvider
    {
        $client = ($this->httpClientFactory)([
            'base_uri' => 'https://api.edenai.run',
            'timeout' => 30,
        ]);

        return new AskYodaProvider(
            $spec,
            $client,
            [
                'askyoda_api_key' => $spec->solution['askyoda_api_key'] ?? null,
                'project_id' => $spec->solution['project_id'] ?? null,
                'default_options' => $spec->solution['default_options'] ?? [],
                'metadata' => $spec->solution['metadata'] ?? [],
                'llm' => $spec->solution['llm'] ?? [],
            ]
        );
    }
}
