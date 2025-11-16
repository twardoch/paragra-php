<?php

declare(strict_types=1);

// this_file: paragra-php/src/Embedding/GeminiEmbeddingConfig.php

namespace ParaGra\Embedding;

use Gemini\Enums\TaskType;
use InvalidArgumentException;
use ParaGra\Exception\ConfigurationException;
use ParaGra\Util\ConfigValidator;

use function in_array;
use function sprintf;
use function str_replace;
use function strtoupper;

final class GeminiEmbeddingConfig
{
    /**
     * @var array<string, int>
     */
    public const MODEL_DIMENSIONS = [
        'text-embedding-004' => 768,
        'embedding-001' => 3072,
    ];

    /**
     * Models that accept the `outputDimensionality` override.
     *
     * @var list<string>
     */
    private const DIMENSIONALITY_OVERRIDE_MODELS = [
        'text-embedding-004',
    ];

    public function __construct(
        public string $apiKey,
        public string $model,
        public int $maxBatchSize = 250,
        public ?string $baseUrl = null,
        public ?TaskType $taskType = null,
        public ?string $title = null,
        public ?int $defaultDimensions = null,
    ) {
    }

    public static function fromEnv(): self
    {
        $apiKey = ConfigValidator::getEnv('GEMINI_EMBED_API_KEY', '');
        if ($apiKey === '') {
            $apiKey = ConfigValidator::getEnv('GOOGLE_API_KEY', '');
        }

        if ($apiKey === '') {
            throw ConfigurationException::invalid(
                'GEMINI_EMBED_API_KEY',
                'Set GEMINI_EMBED_API_KEY or GOOGLE_API_KEY to enable Gemini embeddings.'
            );
        }

        $model = ConfigValidator::getEnv('GEMINI_EMBED_MODEL', 'text-embedding-004');
        if ($model === '') {
            $model = 'text-embedding-004';
        }

        $batchValue = (int) ConfigValidator::getEnv('GEMINI_EMBED_MAX_BATCH', '250');
        $maxBatchSize = $batchValue > 0 ? $batchValue : 250;

        $defaultDimensions = self::parseDimensions(
            ConfigValidator::getEnv('GEMINI_EMBED_DIMENSIONS', ''),
            $model
        );

        return new self(
            apiKey: $apiKey,
            model: $model,
            maxBatchSize: $maxBatchSize,
            baseUrl: ConfigValidator::getEnv('GEMINI_EMBED_BASE_URL', '') ?: null,
            taskType: self::parseTaskType(ConfigValidator::getEnv('GEMINI_EMBED_TASK_TYPE', '')),
            title: ConfigValidator::getEnv('GEMINI_EMBED_TITLE', '') ?: null,
            defaultDimensions: $defaultDimensions,
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function parseDimensions(string $value, string $model): ?int
    {
        if ($value === '') {
            return self::MODEL_DIMENSIONS[$model] ?? null;
        }

        $dimensions = (int) $value;
        if ($dimensions <= 0) {
            throw new InvalidArgumentException('GEMINI_EMBED_DIMENSIONS must be a positive integer.');
        }

        if (!self::supportsDimensionalityOverride($model)) {
            $canonical = self::MODEL_DIMENSIONS[$model] ?? null;
            if ($canonical !== null && $dimensions !== $canonical) {
                throw new InvalidArgumentException(sprintf(
                    'Model "%s" does not support overriding dimensions.',
                    $model
                ));
            }
        }

        return $dimensions;
    }

    private static function supportsDimensionalityOverride(string $model): bool
    {
        return in_array($model, self::DIMENSIONALITY_OVERRIDE_MODELS, true);
    }

    private static function parseTaskType(string $value): ?TaskType
    {
        if ($value === '') {
            return null;
        }

        $normalized = strtoupper(str_replace('-', '_', $value));
        foreach (TaskType::cases() as $case) {
            if ($case->name === $normalized || $case->value === $normalized) {
                if ($case === TaskType::TASK_TYPE_UNSPECIFIED) {
                    return null;
                }

                return $case;
            }
        }

        throw ConfigurationException::invalid('GEMINI_EMBED_TASK_TYPE', 'Unsupported task type: ' . $value);
    }

    public function allowsCustomDimensions(): bool
    {
        return self::supportsDimensionalityOverride($this->model);
    }

    public function canonicalDimensions(): ?int
    {
        return self::MODEL_DIMENSIONS[$this->model] ?? null;
    }
}
