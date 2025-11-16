<?php

declare(strict_types=1);

// this_file: paragra-php/src/Moderation/ModerationException.php

namespace ParaGra\Moderation;

/**
 * Exception thrown when content is flagged by moderation
 */
class ModerationException extends \RuntimeException
{
    /**
     * @param array<string, bool> $flaggedCategories
     * @param array<string, float> $categoryScores
     */
    public function __construct(
        string $message,
        private readonly array $flaggedCategories = [],
        private readonly array $categoryScores = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get flagged categories
     *
     * @return array<string, bool>
     */
    public function getFlaggedCategories(): array
    {
        return $this->flaggedCategories;
    }

    /**
     * Get category scores
     *
     * @return array<string, float>
     */
    public function getCategoryScores(): array
    {
        return $this->categoryScores;
    }

    /**
     * Get comma-separated list of flagged category names
     */
    public function getFlaggedCategoryNames(): string
    {
        $names = array_keys(array_filter($this->flaggedCategories, fn ($v) => $v === true));
        return implode(', ', $names);
    }
}
