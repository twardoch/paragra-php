<?php

declare(strict_types=1);

// this_file: paragra-php/src/Moderation/ModeratorInterface.php

namespace ParaGra\Moderation;

/**
 * Generic moderation adapter contract.
 *
 * Implementations should throw ModerationException when content violates policy.
 */
interface ModeratorInterface
{
    /**
     * @throws ModerationException when the supplied text is flagged.
     */
    public function moderate(string $text): ModerationResult;

    /**
     * Lightweight safety probe that does not bubble exceptions.
     */
    public function isSafe(string $text): bool;
}

