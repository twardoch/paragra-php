<?php

declare(strict_types=1);

// this_file: paragra-php/src/Config/ProviderSpec.php

namespace ParaGra\Config;

use InvalidArgumentException;

use function array_key_exists;
use function is_string;
use function sprintf;
use function trim;

final class ProviderSpec
{
    /**
     * @param array<string, mixed> $solution
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $model,
        public readonly string $apiKey,
        public readonly array $solution,
    ) {
    }

    /**
     * Build a specification from raw configuration array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        self::assertRequiredKeys($data);

        if (!is_array($data['solution'])) {
            throw new InvalidArgumentException('The solution configuration must be an array.');
        }

        return new self(
            provider: self::sanitizeString($data['provider'], 'provider'),
            model: self::sanitizeString($data['model'], 'model'),
            apiKey: self::sanitizeString($data['api_key'], 'api_key'),
            solution: $data['solution'],
        );
    }

    /**
     * Convert back to a normalized array payload.
     *
     * @return array{
     *     provider: string,
     *     model: string,
     *     api_key: string,
     *     solution: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'api_key' => $this->apiKey,
            'solution' => $this->solution,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function assertRequiredKeys(array $data): void
    {
        foreach (['provider', 'model', 'api_key', 'solution'] as $key) {
            if (!array_key_exists($key, $data)) {
                throw new InvalidArgumentException(sprintf('Missing required provider spec key "%s".', $key));
            }
        }
    }

    private static function sanitizeString(mixed $value, string $field): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException(sprintf('The "%s" field must be a string.', $field));
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new InvalidArgumentException(sprintf('The "%s" field cannot be empty.', $field));
        }

        return $trimmed;
    }
}
