<?php

declare(strict_types=1);

// this_file: paragra-php/src/Moderation/NullModerator.php

namespace ParaGra\Moderation;

/**
 * No-op moderator used to explicitly disable moderation.
 */
final class NullModerator implements ModeratorInterface
{
    public function moderate(string $text): ModerationResult
    {
        return new ModerationResult(
            flagged: false,
            categories: [],
            categoryScores: []
        );
    }

    public function isSafe(string $text): bool
    {
        return true;
    }
}

