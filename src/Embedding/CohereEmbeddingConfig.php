<?php

declare(strict_types=1);

// this_file: paragra-php/src/Embedding/CohereEmbeddingConfig.php

namespace ParaGra\Embedding;

use InvalidArgumentException;
use ParaGra\Util\ConfigValidator;

final class CohereEmbeddingConfig
{
    public const DEFAULT_MODEL = 'embed-english-v3.0';
    public const DEFAULT_INPUT_TYPE = 'search_document';
    public const DEFAULT_BASE_URI = 'https://api.cohere.ai';
    public const DEFAULT_ENDPOINT = '/v1/embed';
    public const DEFAULT_MAX_BATCH = 96;

    /**
     * @var array<string, int>
     */
    public const MODEL_DIMENSIONS = [
        'embed-english-v3.0' => 1024,
        'embed-english-light-v3.0' => 384,
        'embed-multilingual-v3.0' => 1024,
        'embed-multilingual-light-v3.0' => 384,
        'embed-multilingual-v2.0' => 768,
    ];

    private const ALLOWED_TRUNCATE = ['START', 'END', 'NONE'];

    private const ALLOWED_EMBEDDING_TYPES = ['float', 'int8', 'binary'];

    /**
     * @param list<string> $embeddingTypes
     */
    public function __construct(
        public string $apiKey,
        public string $model,
        public string $inputType = self::DEFAULT_INPUT_TYPE,
        public ?string $truncate = null,
        public array $embeddingTypes = ['float'],
        public int $maxBatchSize = self::DEFAULT_MAX_BATCH,
        public string $baseUri = self::DEFAULT_BASE_URI,
        public string $endpoint = self::DEFAULT_ENDPOINT,
        public ?int $defaultDimensions = null,
    ) {
        if ($embeddingTypes === []) {
            throw new InvalidArgumentException('Cohere embedding types must include at least one entry.');
        }
    }

    public static function fromEnv(): self
    {
        $apiKey = ConfigValidator::requireEnv('COHERE_API_KEY');
        $model = ConfigValidator::getEnv('COHERE_EMBED_MODEL', self::DEFAULT_MODEL);
        $model = $model !== '' ? $model : self::DEFAULT_MODEL;

        $inputType = ConfigValidator::getEnv('COHERE_EMBED_INPUT_TYPE', self::DEFAULT_INPUT_TYPE);
        $inputType = $inputType !== '' ? strtolower($inputType) : self::DEFAULT_INPUT_TYPE;

        $truncate = ConfigValidator::getEnv('COHERE_EMBED_TRUNCATE', '');
        $truncate = self::normalizeTruncate($truncate);

        $embeddingTypes = self::parseEmbeddingTypes(ConfigValidator::getEnv('COHERE_EMBED_TYPES', 'float'));

        $maxBatchEnv = (int) ConfigValidator::getEnv(
            'COHERE_EMBED_MAX_BATCH',
            (string) self::DEFAULT_MAX_BATCH
        );
        $maxBatchSize = $maxBatchEnv > 0 ? $maxBatchEnv : self::DEFAULT_MAX_BATCH;

        $baseUri = ConfigValidator::getEnv('COHERE_EMBED_BASE_URL', self::DEFAULT_BASE_URI);
        $baseUri = $baseUri !== '' ? $baseUri : self::DEFAULT_BASE_URI;

        $endpoint = ConfigValidator::getEnv('COHERE_EMBED_ENDPOINT', self::DEFAULT_ENDPOINT);
        $endpoint = $endpoint !== '' ? $endpoint : self::DEFAULT_ENDPOINT;

        $dimensions = self::MODEL_DIMENSIONS[$model] ?? null;

        return new self(
            apiKey: $apiKey,
            model: $model,
            inputType: $inputType,
            truncate: $truncate,
            embeddingTypes: $embeddingTypes,
            maxBatchSize: $maxBatchSize,
            baseUri: $baseUri,
            endpoint: $endpoint,
            defaultDimensions: $dimensions,
        );
    }

    private static function normalizeTruncate(string $truncate): ?string
    {
        if ($truncate === '') {
            return null;
        }

        $upper = strtoupper($truncate);
        if (!in_array($upper, self::ALLOWED_TRUNCATE, true)) {
            throw new InvalidArgumentException('COHERE_EMBED_TRUNCATE must be START, END, or NONE.');
        }

        return $upper === 'NONE' ? null : $upper;
    }

    /**
     * @return list<string>
     */
    private static function parseEmbeddingTypes(string $raw): array
    {
        $parts = array_filter(array_map(
            static fn (string $value): string => strtolower(trim($value)),
            explode(',', $raw)
        ), static fn (string $value): bool => $value !== '');

        if ($parts === []) {
            $parts = ['float'];
        }

        $validated = [];
        foreach ($parts as $type) {
            if (!in_array($type, self::ALLOWED_EMBEDDING_TYPES, true)) {
                throw new InvalidArgumentException('COHERE_EMBED_TYPES includes an unsupported value.');
            }
            $validated[] = $type;
        }

        return array_values(array_unique($validated));
    }
}
