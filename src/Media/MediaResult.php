<?php

declare(strict_types=1);

// this_file: paragra-php/src/Media/MediaResult.php

namespace ParaGra\Media;

use InvalidArgumentException;

use function array_key_exists;
use function array_map;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function trim;

/**
 * Immutable DTO returned by image/video providers.
 *
 * Captures the originating provider/model plus the generated artifacts so
 * callers can stream URLs, inline base64, or metadata to downstream clients.
 */
final class MediaResult
{
    /**
     * @var list<array{
     *     url?: string,
     *     base64?: string,
     *     mime_type?: string,
     *     width?: int,
     *     height?: int,
     *     bytes?: int|null,
     *     metadata?: array<string, string|int|float|bool|null>
     * }>
     */
    private array $artifacts;

    /**
     * @param list<array{
     *     url?: string,
     *     base64?: string,
     *     mime_type?: string,
     *     width?: int,
     *     height?: int,
     *     bytes?: int|null,
     *     metadata?: array<string, string|int|float|bool|null>
     * }> $artifacts
     * @param array<string, string|int|float|bool|null> $metadata
     */
    public function __construct(
        private readonly string $provider,
        private readonly string $model,
        array $artifacts,
        private readonly array $metadata = [],
    ) {
        $this->provider = $this->sanitizeString($provider, 'provider');
        $this->model = $this->sanitizeString($model, 'model');
        if ($artifacts === []) {
            throw new InvalidArgumentException('MediaResult requires at least one artifact.');
        }

        $this->artifacts = array_map($this->validateArtifact(...), $artifacts);
        $this->assertMetadata($metadata);
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * @return list<array{
     *     url?: string,
     *     base64?: string,
     *     mime_type?: string,
     *     width?: int,
     *     height?: int,
     *     bytes?: int|null,
     *     metadata?: array<string, string|int|float|bool|null>
     * }>
     */
    public function getArtifacts(): array
    {
        return $this->artifacts;
    }

    /**
     * @return array<string, string|int|float|bool|null>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getFirstUrl(): ?string
    {
        foreach ($this->artifacts as $artifact) {
            if (array_key_exists('url', $artifact)) {
                return $artifact['url'];
            }
        }

        return null;
    }

    /**
     * @return array{
     *     provider: string,
     *     model: string,
     *     artifacts: list<array{
     *         url?: string,
     *         base64?: string,
     *         mime_type?: string,
     *         width?: int,
     *         height?: int,
     *         bytes?: int|null,
     *         metadata?: array<string, string|int|float|bool|null>
     *     }>,
     *     metadata: array<string, string|int|float|bool|null>
     * }
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'artifacts' => $this->artifacts,
            'metadata' => $this->metadata,
        ];
    }

    private function sanitizeString(string $value, string $context): string
    {
        $clean = trim($value);
        if ($clean === '') {
            throw new InvalidArgumentException(sprintf('MediaResult %s cannot be empty.', $context));
        }

        return $clean;
    }

    /**
     * @return array{
     *     url?: string,
     *     base64?: string,
     *     mime_type?: string,
     *     width?: int,
     *     height?: int,
     *     bytes?: int|null,
     *     metadata?: array<string, string|int|float|bool|null>
     * }
     */
    private function validateArtifact(array $artifact): array
    {
        if (!array_key_exists('url', $artifact) && !array_key_exists('base64', $artifact)) {
            throw new InvalidArgumentException('MediaResult artifact requires either a URL or inline data.');
        }

        if (array_key_exists('url', $artifact) && !is_string($artifact['url'])) {
            throw new InvalidArgumentException('MediaResult artifact URL must be a string.');
        }

        if (array_key_exists('base64', $artifact) && !is_string($artifact['base64'])) {
            throw new InvalidArgumentException('MediaResult artifact base64 payload must be a string.');
        }

        if (array_key_exists('width', $artifact) && (!is_int($artifact['width']) || $artifact['width'] <= 0)) {
            throw new InvalidArgumentException('MediaResult artifact width must be positive integer.');
        }

        if (array_key_exists('height', $artifact) && (!is_int($artifact['height']) || $artifact['height'] <= 0)) {
            throw new InvalidArgumentException('MediaResult artifact height must be positive integer.');
        }

        if (array_key_exists('bytes', $artifact) && $artifact['bytes'] !== null && (!is_int($artifact['bytes']) || $artifact['bytes'] < 0)) {
            throw new InvalidArgumentException('MediaResult artifact bytes must be null or non-negative integer.');
        }

        if (array_key_exists('mime_type', $artifact) && !is_string($artifact['mime_type'])) {
            throw new InvalidArgumentException('MediaResult artifact mime_type must be a string.');
        }

        if (array_key_exists('metadata', $artifact)) {
            if (!is_array($artifact['metadata'])) {
                throw new InvalidArgumentException('MediaResult artifact metadata must be an array.');
            }

            array_map(static function ($value, string $key): void {
                if ($value !== null && !is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value)) {
                    throw new InvalidArgumentException(
                        sprintf('MediaResult artifact metadata "%s" must be scalar or null.', $key)
                    );
                }
            }, $artifact['metadata'], array_keys($artifact['metadata']));
        }

        return $artifact;
    }

    /**
     * @param array<string, string|int|float|bool|null> $metadata
     */
    private function assertMetadata(array $metadata): void
    {
        foreach ($metadata as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('MediaResult metadata keys must be strings.');
            }

            if ($value !== null && !is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value)) {
                throw new InvalidArgumentException(
                    sprintf('MediaResult metadata "%s" must be scalar or null.', $key)
                );
            }
        }
    }
}
