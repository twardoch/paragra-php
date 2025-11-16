<?php

declare(strict_types=1);

// this_file: paragra-php/src/Media/MediaRequest.php

namespace ParaGra\Media;

use InvalidArgumentException;

use function array_key_exists;
use function array_map;
use function ceil;
use function is_array;
use function is_int;
use function is_scalar;
use function is_string;
use function max;
use function preg_match;
use function round;
use function sprintf;
use function trim;

/**
 * Immutable request payload describing a text-to-image operation.
 *
 * Inspired by the ParaGra provider catalog research we capture prompt text,
 * optional negative prompts, image counts, seeds, aspect ratios, and user
 * metadata so all downstream image providers can share a single contract.
 */
final class MediaRequest
{
    private string $prompt;

    private ?string $negativePrompt;

    private ?int $width;

    private ?int $height;

    /**
     * @var array{w:int,h:int}|null
     */
    private ?array $aspectRatio;

    private int $images;

    private ?int $seed;

    /**
     * @var array<string, string|int|float|bool|list<string|int|float|bool|null>|null>
     */
    private array $metadata;

    /**
     * @param array<string, string|int|float|bool|list<string|int|float|bool|null>|null> $metadata
     */
    public function __construct(
        string $prompt,
        ?string $negativePrompt = null,
        ?int $width = null,
        ?int $height = null,
        ?string $aspectRatio = null,
        int $images = 1,
        ?int $seed = null,
        array $metadata = [],
    ) {
        $this->prompt = $this->sanitizePrompt($prompt, 'prompt');
        $this->negativePrompt = $negativePrompt !== null
            ? $this->sanitizePrompt($negativePrompt, 'negative prompt', allowEmpty: true)
            : null;
        $this->width = $this->sanitizeDimension($width, 'width');
        $this->height = $this->sanitizeDimension($height, 'height');
        $this->aspectRatio = $aspectRatio !== null ? $this->parseAspectRatio($aspectRatio) : null;

        if ($this->width === null && $this->height === null && $this->aspectRatio === null) {
            // Default to square instructions to avoid provider ambiguity.
            $this->aspectRatio = ['w' => 1, 'h' => 1];
        }

        if ($images <= 0) {
            throw new InvalidArgumentException('MediaRequest images must be a positive integer.');
        }

        $this->images = $images;
        $this->seed = $seed;
        $this->metadata = $this->validateMetadata($metadata);
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function getNegativePrompt(): ?string
    {
        return $this->negativePrompt;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function getAspectRatio(): ?string
    {
        if ($this->aspectRatio === null) {
            return null;
        }

        return sprintf('%d:%d', $this->aspectRatio['w'], $this->aspectRatio['h']);
    }

    public function getImageCount(): int
    {
        return $this->images;
    }

    public function getSeed(): ?int
    {
        return $this->seed;
    }

    /**
     * @return array<string, scalar|array<scalar>|null>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Resolve concrete dimensions that respect the request + defaults.
     *
     * @return array{width:int,height:int}
     */
    public function resolveDimensions(int $defaultWidth = 1024, int $defaultHeight = 1024): array
    {
        $width = $this->width ?? $defaultWidth;
        $height = $this->height ?? $defaultHeight;

        if ($this->aspectRatio !== null) {
            if ($this->width !== null && $this->height === null) {
                $height = (int) round($this->width * $this->aspectRatio['h'] / $this->aspectRatio['w']);
            } elseif ($this->height !== null && $this->width === null) {
                $width = (int) round($this->height * $this->aspectRatio['w'] / $this->aspectRatio['h']);
            } elseif ($this->width === null && $this->height === null) {
                $width = $defaultWidth;
                $height = (int) round($defaultWidth * $this->aspectRatio['h'] / $this->aspectRatio['w']);
            }
        }

        return [
            'width' => $this->normalizeDimension($width),
            'height' => $this->normalizeDimension($height),
        ];
    }

    /**
     * @return array{
     *     prompt: string,
     *     negative_prompt: string|null,
     *     width: int|null,
     *     height: int|null,
     *     aspect_ratio: string|null,
     *     images: int,
     *     seed: int|null,
     *     metadata: array<string, string|int|float|bool|list<string|int|float|bool|null>|null>
     * }
     */
    public function toArray(): array
    {
        return [
            'prompt' => $this->prompt,
            'negative_prompt' => $this->negativePrompt,
            'width' => $this->width,
            'height' => $this->height,
            'aspect_ratio' => $this->getAspectRatio(),
            'images' => $this->images,
            'seed' => $this->seed,
            'metadata' => $this->metadata,
        ];
    }

    private function sanitizePrompt(string $prompt, string $context, bool $allowEmpty = false): string
    {
        $clean = trim($prompt);
        if (!$allowEmpty && $clean === '') {
            throw new InvalidArgumentException(sprintf('MediaRequest %s cannot be empty.', $context));
        }

        return $clean;
    }

    private function sanitizeDimension(?int $value, string $context): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value <= 0) {
            throw new InvalidArgumentException(sprintf('MediaRequest %s must be positive.', $context));
        }

        return $value;
    }

    /**
     * @return array{w:int,h:int}
     */
    private function parseAspectRatio(string $ratio): array
    {
        $clean = trim($ratio);
        if ($clean === '') {
            throw new InvalidArgumentException('MediaRequest aspect ratio cannot be empty.');
        }

        if (preg_match('/^(?P<w>\d+)\s*[:x]\s*(?P<h>\d+)$/i', $clean, $matches) !== 1) {
            throw new InvalidArgumentException('Aspect ratio must use the W:H format (e.g. 16:9).');
        }

        $width = (int) $matches['w'];
        $height = (int) $matches['h'];
        if ($width === 0 || $height === 0) {
            throw new InvalidArgumentException('Aspect ratio components must be positive.');
        }

        return ['w' => $width, 'h' => $height];
    }

    /**
     * @param array<string, string|int|float|bool|list<string|int|float|bool|null>|null> $metadata
     *
     * @return array<string, string|int|float|bool|list<string|int|float|bool|null>|null>
     */
    private function validateMetadata(array $metadata): array
    {
        foreach ($metadata as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('MediaRequest metadata keys must be strings.');
            }

            if ($value === null || is_scalar($value)) {
                continue;
            }

            if (!is_array($value)) {
                throw new InvalidArgumentException(
                    sprintf('MediaRequest metadata for "%s" must be scalar, null, or list.', $key)
                );
            }

            array_map(static function ($entry) use ($key): void {
                if ($entry !== null && !is_scalar($entry)) {
                    throw new InvalidArgumentException(
                        sprintf('MediaRequest metadata list "%s" must contain scalar or null entries.', $key)
                    );
                }
            }, $value);
        }

        return $metadata;
    }

    private function normalizeDimension(int $value): int
    {
        $aligned = (int) ceil($value / 8) * 8;

        return max($aligned, 64);
    }
}
