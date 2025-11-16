<?php

declare(strict_types=1);

// this_file: paragra-php/src/Embedding/OpenAiEmbeddingConfig.php

namespace ParaGra\Embedding;

use ParaGra\Util\ConfigValidator;

final class OpenAiEmbeddingConfig
{
    /**
     * @var array<string, int>
     */
    public const MODEL_DIMENSIONS = [
        'text-embedding-3-small' => 1536,
        'text-embedding-3-large' => 3072,
        'text-embedding-ada-002' => 1536,
    ];

    public function __construct(
        public string $apiKey,
        public string $model,
        public ?string $baseUrl = null,
        public int $maxBatchSize = 2048,
        public ?int $defaultDimensions = null,
    ) {
    }

    public static function fromEnv(): self
    {
        $apiKey = ConfigValidator::requireEnv('OPENAI_API_KEY');
        $model = ConfigValidator::getEnv('OPENAI_EMBED_MODEL', 'text-embedding-3-small');
        $resolvedModel = $model !== '' ? $model : 'text-embedding-3-small';
        $baseUrl = ConfigValidator::getEnv('OPENAI_EMBED_BASE_URL', '');
        $maxBatch = (int) ConfigValidator::getEnv('OPENAI_EMBED_MAX_BATCH', '2048');
        $maxBatchSize = $maxBatch > 0 ? $maxBatch : 2048;

        $dimensionStr = ConfigValidator::getEnv('OPENAI_EMBED_DIMENSIONS', '');
        $defaultDimensions = self::parseDimensions($dimensionStr, $resolvedModel);

        return new self(
            apiKey: $apiKey,
            model: $resolvedModel,
            baseUrl: $baseUrl !== '' ? $baseUrl : null,
            maxBatchSize: $maxBatchSize,
            defaultDimensions: $defaultDimensions,
        );
    }

    private static function parseDimensions(string $dimensionStr, string $model): ?int
    {
        if ($dimensionStr !== '') {
            $dimensions = (int) $dimensionStr;

            return $dimensions > 0 ? $dimensions : null;
        }

        return self::MODEL_DIMENSIONS[$model] ?? null;
    }
}
