<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Moderation/OpenAiModeratorTest.php

namespace ParaGra\Tests\Moderation;

use OpenAI\Contracts\Resources\ModerationsContract;
use OpenAI\Responses\Meta\MetaInformation;
use OpenAI\Responses\Moderations\CreateResponse;
use ParaGra\Exception\ConfigurationException;
use ParaGra\Moderation\ModerationException;
use ParaGra\Moderation\ModerationResult;
use ParaGra\Moderation\OpenAiModerator;
use PHPUnit\Framework\TestCase;

final class OpenAiModeratorTest extends TestCase
{
    public function test_moderate_with_clean_content(): void
    {
        $mockModerations = $this->createMock(ModerationsContract::class);

        $responseData = [
            'id' => 'modr-test123',
            'model' => 'omni-moderation-latest',
            'results' => [[
                'flagged' => false,
                'categories' => [
                    'sexual' => false,
                    'hate' => false,
                    'harassment' => false,
                    'self-harm' => false,
                    'violence' => false,
                ],
                'category_scores' => [
                    'sexual' => 0.001,
                    'hate' => 0.002,
                    'harassment' => 0.003,
                    'self-harm' => 0.001,
                    'violence' => 0.002,
                ],
            ]],
        ];

        $mockResponse = CreateResponse::from($responseData, MetaInformation::from([]));

        $mockModerations->expects(self::once())
            ->method('create')
            ->with([
                'model' => 'omni-moderation-latest',
                'input' => 'This is a safe message',
            ])
            ->willReturn($mockResponse);

        $moderator = new OpenAiModerator($mockModerations, 'omni-moderation-latest');
        $result = $moderator->moderate('This is a safe message');

        self::assertInstanceOf(ModerationResult::class, $result);
        self::assertFalse($result->isFlagged());
        self::assertFalse($result->isCategoryFlagged('violence'));
        self::assertEquals(0.002, $result->getCategoryScore('violence'));
    }

    public function test_moderate_with_flagged_content(): void
    {
        $mockModerations = $this->createMock(ModerationsContract::class);

        $responseData = [
            'id' => 'modr-test456',
            'model' => 'omni-moderation-latest',
            'results' => [[
                'flagged' => true,
                'categories' => [
                    'sexual' => false,
                    'hate' => false,
                    'harassment' => true,
                    'self-harm' => false,
                    'violence' => true,
                ],
                'category_scores' => [
                    'sexual' => 0.001,
                    'hate' => 0.002,
                    'harassment' => 0.85,
                    'self-harm' => 0.001,
                    'violence' => 0.92,
                ],
            ]],
        ];

        $mockResponse = CreateResponse::from($responseData, MetaInformation::from([]));

        $mockModerations->expects(self::once())
            ->method('create')
            ->willReturn($mockResponse);

        $moderator = new OpenAiModerator($mockModerations, 'omni-moderation-latest');

        $this->expectException(ModerationException::class);
        $this->expectExceptionMessage('Content flagged by moderation: harassment, violence');

        $moderator->moderate('I want to hurt them');
    }

    public function test_moderation_exception_contains_details(): void
    {
        $mockModerations = $this->createMock(ModerationsContract::class);

        $responseData = [
            'id' => 'modr-test789',
            'model' => 'omni-moderation-latest',
            'results' => [[
                'flagged' => true,
                'categories' => [
                    'sexual' => false,
                    'hate' => true,
                    'harassment' => false,
                    'self-harm' => false,
                    'violence' => false,
                ],
                'category_scores' => [
                    'sexual' => 0.001,
                    'hate' => 0.95,
                    'harassment' => 0.003,
                    'self-harm' => 0.001,
                    'violence' => 0.002,
                ],
            ]],
        ];

        $mockResponse = CreateResponse::from($responseData, MetaInformation::from([]));
        $mockModerations->method('create')->willReturn($mockResponse);

        $moderator = new OpenAiModerator($mockModerations);

        try {
            $moderator->moderate('hateful content');
            self::fail('Expected ModerationException was not thrown');
        } catch (ModerationException $e) {
            self::assertSame('hate', $e->getFlaggedCategoryNames());
            $flaggedCategories = $e->getFlaggedCategories();
            self::assertTrue($flaggedCategories['hate']);
            self::assertFalse($flaggedCategories['sexual']);

            $scores = $e->getCategoryScores();
            self::assertEquals(0.95, $scores['hate']);
        }
    }

    public function test_is_safe_returns_true_for_clean_content(): void
    {
        $mockModerations = $this->createMock(ModerationsContract::class);

        $responseData = [
            'id' => 'modr-safe',
            'model' => 'omni-moderation-latest',
            'results' => [[
                'flagged' => false,
                'categories' => ['violence' => false],
                'category_scores' => ['violence' => 0.001],
            ]],
        ];

        $mockResponse = CreateResponse::from($responseData, MetaInformation::from([]));
        $mockModerations->method('create')->willReturn($mockResponse);

        $moderator = new OpenAiModerator($mockModerations);
        self::assertTrue($moderator->isSafe('Hello world'));
    }

    public function test_is_safe_returns_false_for_flagged_content(): void
    {
        $mockModerations = $this->createMock(ModerationsContract::class);

        $responseData = [
            'id' => 'modr-unsafe',
            'model' => 'omni-moderation-latest',
            'results' => [[
                'flagged' => true,
                'categories' => ['violence' => true],
                'category_scores' => ['violence' => 0.95],
            ]],
        ];

        $mockResponse = CreateResponse::from($responseData, MetaInformation::from([]));
        $mockModerations->method('create')->willReturn($mockResponse);

        $moderator = new OpenAiModerator($mockModerations);
        self::assertFalse($moderator->isSafe('violent content'));
    }

    public function test_moderate_throws_on_empty_results(): void
    {
        $mockModerations = $this->createMock(ModerationsContract::class);

        $responseData = [
            'id' => 'modr-empty',
            'model' => 'omni-moderation-latest',
            'results' => [],
        ];

        $mockResponse = CreateResponse::from($responseData, MetaInformation::from([]));
        $mockModerations->method('create')->willReturn($mockResponse);

        $moderator = new OpenAiModerator($mockModerations);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No moderation results returned');

        $moderator->moderate('test');
    }

    public function test_moderate_wraps_api_errors(): void
    {
        $mockModerations = $this->createMock(ModerationsContract::class);
        $mockModerations->method('create')->willThrowException(new \Exception('API Error'));

        $moderator = new OpenAiModerator($mockModerations);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Moderation API request failed: API Error');

        $moderator->moderate('test');
    }

    public function test_from_env_creates_moderator_with_defaults(): void
    {
        $_ENV['OPENAI_API_KEY'] = 'test-key-123';

        $moderator = OpenAiModerator::fromEnv();

        self::assertInstanceOf(OpenAiModerator::class, $moderator);

        unset($_ENV['OPENAI_API_KEY']);
    }

    public function test_from_env_uses_custom_model(): void
    {
        $_ENV['OPENAI_API_KEY'] = 'test-key-456';
        $_ENV['OPENAI_MODERATION_MODEL'] = 'text-moderation-latest';

        $moderator = OpenAiModerator::fromEnv();

        self::assertInstanceOf(OpenAiModerator::class, $moderator);

        unset($_ENV['OPENAI_API_KEY'], $_ENV['OPENAI_MODERATION_MODEL']);
    }

    public function test_from_env_throws_on_missing_api_key(): void
    {
        unset($_ENV['OPENAI_API_KEY']);
        putenv('OPENAI_API_KEY');

        $this->expectException(ConfigurationException::class);

        OpenAiModerator::fromEnv();
    }
}
