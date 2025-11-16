<?php

declare(strict_types=1);

// this_file: paragra-php/src/Llm/OpenAiChatConfig.php

namespace ParaGra\Llm;

use ParaGra\Util\ConfigValidator;

final class OpenAiChatConfig
{
    public function __construct(
        public string $apiKey,
        public string $model,
        public ?string $baseUrl = null,
        public float $defaultTemperature = 0.7,
        public float $defaultTopP = 1.0,
        public ?int $defaultMaxTokens = null
    ) {
    }

    /**
     * Create configuration from environment variables
     *
     * Required environment variables:
     * - OPENAI_API_KEY
     *
     * Optional environment variables:
     * - OPENAI_BASE_URL (default: null, uses OpenAI's default)
     * - OPENAI_API_MODEL (default: gpt-4o-mini)
     * - OPENAI_API_TEMPERATURE (default: 0.7)
     * - OPENAI_API_TOP_P (default: 1.0)
     * - OPENAI_API_MAX_OUT (default: null, no limit)
     *
     * @throws \ParaGra\Exception\ConfigurationException if required env vars missing
     */
    public static function fromEnv(): self
    {
        $apiKey = ConfigValidator::requireEnv('OPENAI_API_KEY');
        $model = ConfigValidator::getEnv('OPENAI_API_MODEL', 'gpt-4o-mini');
        $baseUrlStr = ConfigValidator::getEnv('OPENAI_BASE_URL', '');
        $baseUrl = $baseUrlStr !== '' ? $baseUrlStr : null;

        // Parse numeric config with fallbacks
        $temperature = (float) ConfigValidator::getEnv('OPENAI_API_TEMPERATURE', '0.7');
        $topP = (float) ConfigValidator::getEnv('OPENAI_API_TOP_P', '1.0');
        $maxTokensStr = ConfigValidator::getEnv('OPENAI_API_MAX_OUT', '0');
        $maxTokens = ($max = (int) $maxTokensStr) > 0 ? $max : null;

        return new self(
            apiKey: $apiKey,
            model: $model,
            baseUrl: $baseUrl,
            defaultTemperature: $temperature,
            defaultTopP: $topP,
            defaultMaxTokens: $maxTokens,
        );
    }
}
