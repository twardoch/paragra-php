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
 * High-level entry point that routes retrieval and answer flows across configured providers.
 *
 * Typical lifecycle:
 *   1. Instantiate via {@see ParaGra::fromConfig()} with a priority-pool config array.
 *   2. Optionally attach a {@see ModeratorInterface} with {@see ParaGra::withModeration()}.
 *   3. Call {@see ParaGra::retrieve()} for context passages or {@see ParaGra::answer()} for
 *      a full retrieval-augmented generation cycle.
 *
 * The library delegates all HTTP communication to the configured provider pool; no network
 * calls occur inside this class itself.
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

    /**
     * @param PriorityPool        $pools           Ordered tiers of ProviderSpec entries to try.
     * @param ProviderFactory     $providerFactory  Constructs concrete provider / LLM adapters.
     * @param FallbackStrategy|null $fallback       Rotation + fallback controller; defaults to a
     *                                              {@see FallbackStrategy} with a timestamp-based
     *                                              {@see KeyRotator}.
     * @param PromptBuilder|null  $promptBuilder    Formats retrieved chunks into an LLM prompt;
     *                                              defaults to {@see PromptBuilder}.
     * @param callable(ProviderSpec): ProviderInterface|null $providerResolver
     *        Override how a {@see ProviderSpec} is turned into a {@see ProviderInterface}.
     *        Defaults to {@see ProviderFactory::createProvider()}.
     * @param callable(ProviderSpec): NeuronAiAdapter|null $llmResolver
     *        Override how a {@see ProviderSpec} is turned into a {@see NeuronAiAdapter}.
     *        Defaults to {@see ProviderFactory::createLlmClient()}.
     */
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
     * Build a ParaGra instance from a flat configuration array.
     *
     * Required keys
     * -------------
     * - ``priority_pools`` (array<int, list<array<string, mixed>>>)
     *     Ordered tiers of provider specs. Each tier is a list of spec arrays. ParaGra works
     *     through tiers in index order and rotates within a tier before moving to the next.
     *
     * Optional keys
     * -------------
     * - ``provider_catalog`` (string)
     *     Absolute path to the PHP catalog file (defaults to config/providers/catalog.php).
     *     Required only when any spec uses ``catalog_slug`` or ``catalog_model_type``.
     *
     * Provider spec array keys
     * ------------------------
     * - ``provider``           (string, required) – Provider slug, e.g. "ragie", "openai", "gemini".
     * - ``model``              (string, required) – Model identifier within the provider.
     * - ``api_key``            (string, required) – API key for this entry.
     * - ``solution``           (array, required)  – Provider-specific options; must include ``type``.
     * - ``catalog_slug``       (string, optional) – Short name to look up defaults from the catalog.
     * - ``catalog_model_type`` (string, optional) – Catalog look-up type, e.g. "generation".
     *
     * @param array<string, mixed> $config
     *
     * @throws \InvalidArgumentException When ``priority_pools`` is missing or not an array.
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

    /**
     * Attach a content moderator that is called once per query before retrieval.
     *
     * The moderator runs synchronously inside {@see guardQuestion()}. If the moderator
     * raises an exception the query is aborted and no provider is contacted.
     *
     * Returns the same instance (fluent interface) so it can be chained:
     *
     * ```php
     * $paragra = ParaGra::fromConfig($config)->withModeration(new OpenAiModerator($key));
     * ```
     *
     * @return $this
     */
    public function withModeration(ModeratorInterface $moderator): self
    {
        $this->moderator = $moderator;

        return $this;
    }

    /**
     * Execute a retrieval request with automatic provider rotation and fallback.
     *
     * Tries each priority tier in order. Within a tier the {@see KeyRotator} selects
     * the starting entry based on the current Unix timestamp modulo the pool size, then
     * walks forward on failure. When every entry in a tier has been exhausted the strategy
     * moves to the next tier. A {@see RuntimeException} is thrown only when all tiers fail.
     *
     * @param string               $query   The user query or search text (must be non-empty
     *                                      after trimming; {@see \InvalidArgumentException} is
     *                                      thrown otherwise).
     * @param array<string, mixed> $options Provider-specific retrieval overrides, e.g.
     *                                      ``['top_k' => 5, 'filter' => ['lang' => 'en']]``.
     *
     * @throws \InvalidArgumentException When $query is empty after trimming.
     * @throws \RuntimeException         When all priority pools are exhausted.
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
     * Answer a question by combining retrieval and LLM generation in one call.
     *
     * Execution order (all within the same fallback tier):
     *   1. {@see retrieve()} — fetch context passages from the selected provider.
     *   2. {@see PromptBuilder::build()} — weave the passages and question into a prompt.
     *   3. {@see NeuronAiAdapter::generate()} — send the prompt to the LLM.
     *
     * If retrieval *or* generation throws, the fallback strategy rotates to the next
     * provider before retrying both steps together.
     *
     * @param string $question The user question (must be non-empty after trimming).
     *
     * @param array{
     *     retrieval?: array<string, mixed>,
     *     generation?: array<string, mixed>
     * } $options Two optional sub-arrays:
     *   - ``retrieval`` – Passed verbatim to the retrieval provider, e.g.
     *     ``['top_k' => 5, 'filter' => ['namespace' => 'docs']]``.
     *   - ``generation`` – Passed verbatim to the LLM adapter, e.g.
     *     ``['temperature' => 0.2, 'max_tokens' => 1024]``.
     *
     * @return array{
     *     answer: string,
     *     prompt: string,
     *     context: UnifiedResponse,
     *     metadata: array<string, mixed>
     * } Keys:
     *   - ``answer``   – Raw text returned by the LLM.
     *   - ``prompt``   – The full prompt sent to the LLM (useful for debugging).
     *   - ``context``  – The {@see UnifiedResponse} from retrieval (chunks + provider metadata).
     *   - ``metadata`` – Merged map of ``provider``, ``model``, and any provider-specific extras.
     *
     * @throws \InvalidArgumentException When $question is empty or an option sub-array is not an array.
     * @throws \RuntimeException         When all priority pools are exhausted.
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
