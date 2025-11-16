<?php

declare(strict_types=1);

// this_file: paragra-php/src/Moderation/ModerationResult.php

namespace ParaGra\Moderation;

/**
 * Result from content moderation when content is not flagged
 */
class ModerationResult
{
    /**
     * @param array<string, bool> $categories
     * @param array<string, float> $categoryScores
     */
    public function __construct(
        private readonly bool $flagged,
        private readonly array $categories = [],
        private readonly array $categoryScores = []
    ) {
    }

    /**
     * Check if content was flagged as harmful
     */
    public function isFlagged(): bool
    {
        return $this->flagged;
    }

    /**
     * Get all categories
     *
     * @return array<string, bool>
     */
    public function getCategories(): array
    {
        return $this->categories;
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
     * Get score for specific category
     */
    public function getCategoryScore(string $category): ?float
    {
        return $this->categoryScores[$category] ?? null;
    }

    /**
     * Check if specific category was flagged
     */
    public function isCategoryFlagged(string $category): bool
    {
        return $this->categories[$category] ?? false;
    }
}
