<?php

declare(strict_types=1);

// this_file: paragra-php/src/Embedding/VoyageEmbeddingConfig.php

namespace ParaGra\Embedding;

use InvalidArgumentException;
use ParaGra\Util\ConfigValidator;

use function array_filter;
use function array_unique;
use function array_values;
use function ctype_digit;
use function in_array;
use function ltrim;
use function sort;
use function strtolower;

final class VoyageEmbeddingConfig
{
    public const DEFAULT_MODEL = 'voyage-3';
    public const DEFAULT_BASE_URI = 'https://api.voyageai.com';
    public const DEFAULT_ENDPOINT = '/v1/embeddings';
    public const DEFAULT_MAX_BATCH = 128;
    public const DEFAULT_TIMEOUT = 30;

    /**
     * @var array<string, int>
     */
    public const MODEL_DIMENSIONS = [
        'voyage-3-large' => 2048,
        'voyage-3' => 1024,
        'voyage-3-lite' => 512,
        'voyage-2' => 1024,
        'voyage-2-lite' => 512,
    ];

    private const ALLOWED_INPUT_TYPES = ['document', 'query'];

    /**
     * @param string|null $inputType One of document/query or null (omit)
     */
    public function __construct(
        public string $apiKey,
        public string $model,
        public string $baseUri = self::DEFAULT_BASE_URI,
        public string $endpoint = self::DEFAULT_ENDPOINT,
        public int $maxBatchSize = self::DEFAULT_MAX_BATCH,
        public int $timeout = self::DEFAULT_TIMEOUT,
        public ?string $inputType = 'document',
        public bool $truncate = true,
        public string $encodingFormat = 'float',
        public ?int $defaultDimensions = null,
    ) {
        if ($this->maxBatchSize <= 0) {
            throw new InvalidArgumentException('Voyage embedding max batch size must be positive.');
        }

        if ($this->timeout <= 0) {
            throw new InvalidArgumentException('Voyage embedding timeout must be positive.');
        }

        if ($this->inputType !== null && !in_array($this->inputType, self::ALLOWED_INPUT_TYPES, true)) {
            throw new InvalidArgumentException('VOYAGE_EMBED_INPUT_TYPE must be document or query.');
        }

        if ($this->encodingFormat !== 'float') {
            throw new InvalidArgumentException('VOYAGE_EMBED_ENCODING currently only supports "float".');
        }
    }

    public static function fromEnv(): self
    {
        $apiKey = ConfigValidator::requireEnv('VOYAGE_API_KEY');
        $model = ConfigValidator::getEnv('VOYAGE_EMBED_MODEL', self::DEFAULT_MODEL);
        $model = $model !== '' ? $model : self::DEFAULT_MODEL;

        $baseUri = ConfigValidator::getEnv('VOYAGE_EMBED_BASE_URL', self::DEFAULT_BASE_URI);
        $baseUri = $baseUri !== '' ? $baseUri : self::DEFAULT_BASE_URI;

        $endpoint = ConfigValidator::getEnv('VOYAGE_EMBED_ENDPOINT', self::DEFAULT_ENDPOINT);
        $endpoint = $endpoint !== '' ? $endpoint : self::DEFAULT_ENDPOINT;

        $maxBatchValue = (int) ConfigValidator::getEnv(
            'VOYAGE_EMBED_MAX_BATCH',
            (string) self::DEFAULT_MAX_BATCH
        );
        $maxBatchSize = $maxBatchValue > 0 ? $maxBatchValue : self::DEFAULT_MAX_BATCH;

        $timeoutValue = (int) ConfigValidator::getEnv(
            'VOYAGE_EMBED_TIMEOUT',
            (string) self::DEFAULT_TIMEOUT
        );
        $timeout = $timeoutValue > 0 ? $timeoutValue : self::DEFAULT_TIMEOUT;

        $inputType = self::normalizeInputType(ConfigValidator::getEnv('VOYAGE_EMBED_INPUT_TYPE', 'document'));
        $truncate = self::parseBoolean(ConfigValidator::getEnv('VOYAGE_EMBED_TRUNCATE', 'true'));
        $encodingFormat = self::normalizeEncoding(ConfigValidator::getEnv('VOYAGE_EMBED_ENCODING', 'float'));
        $dimensions = self::parseDimensions(ConfigValidator::getEnv('VOYAGE_EMBED_DIMENSIONS', ''), $model);

        return new self(
            apiKey: $apiKey,
            model: $model,
            baseUri: $baseUri,
            endpoint: $endpoint,
            maxBatchSize: $maxBatchSize,
            timeout: $timeout,
            inputType: $inputType,
            truncate: $truncate,
            encodingFormat: $encodingFormat,
            defaultDimensions: $dimensions,
        );
    }

    private static function normalizeInputType(string $value): ?string
    {
        if ($value === '') {
            return 'document';
        }

        $normalized = strtolower($value);

        if ($normalized === 'none' || $normalized === 'null') {
            return null;
        }

        if (in_array($normalized, self::ALLOWED_INPUT_TYPES, true)) {
            return $normalized;
        }

        throw new InvalidArgumentException('VOYAGE_EMBED_INPUT_TYPE must be "document", "query", or blank.');
    }

    private static function parseBoolean(string $value): bool
    {
        if ($value === '') {
            return true;
        }

        $normalized = strtolower($value);

        return match ($normalized) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => throw new InvalidArgumentException('VOYAGE_EMBED_TRUNCATE must be boolean.'),
        };
    }

    private static function normalizeEncoding(string $value): string
    {
        if ($value === '' || strtolower($value) === 'float') {
            return 'float';
        }

        throw new InvalidArgumentException('VOYAGE_EMBED_ENCODING currently supports only "float".');
    }

    private static function parseDimensions(string $value, string $model): ?int
    {
        if ($value === '') {
            return self::MODEL_DIMENSIONS[$model] ?? null;
        }

        if (!ctype_digit(ltrim($value, '-'))) {
            throw new InvalidArgumentException('VOYAGE_EMBED_DIMENSIONS must be a positive integer.');
        }

        $dimensions = (int) $value;
        if ($dimensions <= 0) {
            throw new InvalidArgumentException('VOYAGE_EMBED_DIMENSIONS must be positive.');
        }

        return $dimensions;
    }

    /**
     * @return list<int>
     */
    public function supportedDimensions(): array
    {
        $dimensions = array_values(array_unique(array_filter(
            [
                $this->defaultDimensions,
                ...array_values(self::MODEL_DIMENSIONS),
            ],
            static fn (?int $value): bool => $value !== null && $value > 0
        )));
        sort($dimensions);

        return $dimensions;
    }
}
