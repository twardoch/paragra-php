<?php

declare(strict_types=1);

// this_file: paragra-php/src/Moderation/OpenAiModerator.php

namespace ParaGra\Moderation;

use OpenAI\Contracts\Resources\ModerationsContract;
use ParaGra\Util\ConfigValidator;

/**
 * OpenAI Content Moderation
 *
 * Uses OpenAI's moderation API to check if content is potentially harmful.
 */
class OpenAiModerator implements ModeratorInterface
{
    private const DEFAULT_MODEL = 'omni-moderation-latest';

    public function __construct(
        private readonly ModerationsContract $moderations,
        private readonly string $model = self::DEFAULT_MODEL
    ) {
    }

    /**
     * Create moderator from environment variables
     *
     * Required environment variables:
     * - OPENAI_API_KEY
     *
     * Optional environment variables:
     * - OPENAI_MODERATION_MODEL (default: omni-moderation-latest)
     *
     * @throws \ParaGra\Exception\ConfigurationException if OPENAI_API_KEY missing
     */
    public static function fromEnv(): self
    {
        $apiKey = ConfigValidator::requireEnv('OPENAI_API_KEY');
        $model = ConfigValidator::getEnv('OPENAI_MODERATION_MODEL', self::DEFAULT_MODEL);

        $client = \OpenAI::client($apiKey);
        $moderations = $client->moderations();

        return new self($moderations, $model);
    }

    /**
     * Moderate text content
     *
     * @throws ModerationException if content is flagged as harmful
     */
    public function moderate(string $text): ModerationResult
    {
        try {
            $response = $this->moderations->create([
                'model' => $this->model,
                'input' => $text,
            ]);

            // Get first result (single text input)
            $result = $response->results[0] ?? null;

            if ($result === null) {
                throw new \RuntimeException('No moderation results returned');
            }

            // Extract data from OpenAI response objects
            $flagged = $result->flagged;

            // Convert CreateResponseCategory objects to arrays
            $categories = [];
            $categoryScores = [];

            foreach ($result->categories as $categoryName => $categoryObj) {
                $categories[$categoryName] = $categoryObj->violated;
                $categoryScores[$categoryName] = $categoryObj->score;
            }

            // If flagged, throw exception
            if ($flagged) {
                $flaggedCategories = array_filter($categories, fn ($v) => $v === true);
                $categoryNames = implode(', ', array_keys($flaggedCategories));

                throw new ModerationException(
                    "Content flagged by moderation: {$categoryNames}",
                    $categories,
                    $categoryScores
                );
            }

            return new ModerationResult(
                flagged: false,
                categories: $categories,
                categoryScores: $categoryScores
            );
        } catch (ModerationException $e) {
            // Re-throw moderation exceptions
            throw $e;
        } catch (\Throwable $e) {
            // Wrap other errors
            throw new \RuntimeException(
                'Moderation API request failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Check if text is safe (doesn't throw on flagged content)
     */
    public function isSafe(string $text): bool
    {
        try {
            $this->moderate($text);
            return true;
        } catch (ModerationException $e) {
            return false;
        }
    }
}
