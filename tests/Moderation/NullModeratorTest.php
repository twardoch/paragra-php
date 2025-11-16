<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Moderation/NullModeratorTest.php

namespace ParaGra\Tests\Moderation;

use ParaGra\Moderation\NullModerator;
use PHPUnit\Framework\TestCase;

final class NullModeratorTest extends TestCase
{
    public function testModerateAlwaysReturnsSafeResult(): void
    {
        $moderator = new NullModerator();

        $result = $moderator->moderate('any content');

        self::assertFalse($result->isFlagged(), 'Null moderator never flags content');
        self::assertSame([], $result->getCategories(), 'No categories should be returned');
        self::assertSame([], $result->getCategoryScores(), 'No scores should be returned');
    }

    public function testIsSafeAlwaysTrue(): void
    {
        $moderator = new NullModerator();

        self::assertTrue($moderator->isSafe('dangerous content still returns true'));
    }
}

