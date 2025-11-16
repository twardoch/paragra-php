<?php

declare(strict_types=1);

// this_file: paragra-php/src/ParaGra.php

namespace ParaGra;

use InvalidArgumentException;
use ParaGra\Config\PriorityPool;
use ParaGra\Config\ProviderSpec;
use ParaGra\Llm\NeuronAiAdapter;
use ParaGra\Llm\PromptBuilder;
use ParaGra\Moderation\ModeratorInterface;
use ParaGra\ProviderCatalog\ProviderDiscovery;
use ParaGra\Providers\ProviderFactory;
use ParaGra\Providers\ProviderInterface;
use ParaGra\Response\UnifiedResponse;
use ParaGra\Router\FallbackStrategy;
use ParaGra\Router\KeyRotator;

use function array_merge;
use function dirname;
use function is_array;
use function is_string;
use function sprintf;
use function trim;

/**
 * High-level entry point that routes retrieval + answer flows across configured providers.
 */
final class ParaGra
{
    private FallbackStrategy $fallback;

    private PromptBuilder $promptBuilder;

    private ?ModeratorInterface $moderator = null;

    /**
     * @var callable(ProviderSpec): ProviderInterface
     */
    private $providerResolver;

    /**
     * @var callable(ProviderSpec): NeuronAiAdapter
     */
    private $llmResolver;

    public function __construct(
        private readonly PriorityPool $pools,
        private readonly ProviderFactory $providerFactory,
        ?FallbackStrategy $fallback = null,
        ?PromptBuilder $promptBuilder = null,
        ?callable $providerResolver = null,
        ?callable $llmResolver = null,
    ) {
        $this->fallback = $fallback ?? new FallbackStrategy($pools, new KeyRotator());
        $this->promptBuilder = $promptBuilder ?? new PromptBuilder();
        $this->providerResolver = $providerResolver ?? [$this->providerFactory, 'createProvider'];
        $this->llmResolver = $llmResolver ?? [$this->providerFactory, 'createLlmClient'];
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromConfig(array $config): self
    {
        if (!isset($config['priority_pools']) || !is_array($config['priority_pools'])) {
            throw new InvalidArgumentException('ParaGra configuration requires a "priority_pools" array.');
        }

        $catalog = null;
        if (self::hasCatalogEntries($config['priority_pools'])) {
            $catalogPath = isset($config['provider_catalog']) && is_string($config['provider_catalog'])
                ? $config['provider_catalog']
                : self::defaultCatalogPath();
            $catalog = ProviderDiscovery::fromFile($catalogPath);
        }

        $pools = PriorityPool::fromArray($config['priority_pools'], $catalog);

        return new self($pools, new ProviderFactory());
    }

    public function withModeration(ModeratorInterface $moderator): self
    {
        $this->moderator = $moderator;

        return $this;
    }

    /**
     * Execute a retrieval request with automatic rotation + fallback.
     *
     * @param array<string, mixed> $options Provider-specific retrieval overrides
     */
    public function retrieve(string $query, array $options = []): UnifiedResponse
    {
        $cleanQuery = $this->guardQuestion($query);

        return $this->fallback->execute(function (ProviderSpec $spec) use ($cleanQuery, $options): UnifiedResponse {
            $provider = $this->resolveProvider($spec);
            return $provider->retrieve($cleanQuery, $options);
        });
    }

    /**
     * Answer a user question by retrieving context + invoking the configured LLM provider.
     *
     * @param array{
     *     retrieval?: array<string, mixed>,
     *     generation?: array<string, mixed>
     * } $options
     *
     * @return array{
     *     answer: string,
     *     prompt: string,
     *     context: UnifiedResponse,
     *     metadata: array<string, mixed>
     * }
     */
    public function answer(string $question, array $options = []): array
    {
        $cleanQuestion = $this->guardQuestion($question);
        $retrievalOptions = $this->extractNestedOptions($options, 'retrieval');
        $generationOptions = $this->extractNestedOptions($options, 'generation');

        return $this->fallback->execute(function (ProviderSpec $spec) use ($cleanQuestion, $retrievalOptions, $generationOptions): array {
            $provider = $this->resolveProvider($spec);
            $context = $provider->retrieve($cleanQuestion, $retrievalOptions);
            $prompt = $this->promptBuilder->build($cleanQuestion, $context->getChunkTexts());

            $llm = $this->resolveLlm($spec);
            $answer = $llm->generate($prompt, $generationOptions);

            return [
                'answer' => $answer,
                'prompt' => $prompt,
                'context' => $context,
                'metadata' => array_merge(
                    [
                        'provider' => $spec->provider,
                        'model' => $spec->model,
                    ],
                    $context->getProviderMetadata(),
                ),
            ];
        });
    }

    private function guardQuestion(string $question): string
    {
        $clean = trim($question);
        if ($clean === '') {
            throw new InvalidArgumentException('Query cannot be empty.');
        }

        if ($this->moderator !== null) {
            $this->moderator->moderate($clean);
        }

        return $clean;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractNestedOptions(array $options, string $key): array
    {
        if (!isset($options[$key])) {
            return [];
        }

        if (!is_array($options[$key])) {
            throw new InvalidArgumentException(sprintf('The "%s" option must be an array.', $key));
        }

        return $options[$key];
    }

    private function resolveProvider(ProviderSpec $spec): ProviderInterface
    {
        $provider = ($this->providerResolver)($spec);
        if (!$provider instanceof ProviderInterface) {
            throw new InvalidArgumentException('Provider resolver must return a ProviderInterface implementation.');
        }

        return $provider;
    }

    private function resolveLlm(ProviderSpec $spec): NeuronAiAdapter
    {
        $adapter = ($this->llmResolver)($spec);
        if (!$adapter instanceof NeuronAiAdapter) {
            throw new InvalidArgumentException('LLM resolver must return a NeuronAiAdapter instance.');
        }

        return $adapter;
    }

    /**
     * @param array<int, mixed> $priorityPools
     */
    private static function hasCatalogEntries(array $priorityPools): bool
    {
        foreach ($priorityPools as $pool) {
            if (!is_array($pool)) {
                continue;
            }

            foreach ($pool as $spec) {
                if (is_array($spec) && (isset($spec['catalog']) || isset($spec['catalog_slug']))) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function defaultCatalogPath(): string
    {
        return dirname(__DIR__) . '/config/providers/catalog.php';
    }
}
