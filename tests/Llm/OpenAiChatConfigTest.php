<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Llm/OpenAiChatConfigTest.php

namespace ParaGra\Tests\Llm;

use ParaGra\Exception\ConfigurationException;
use ParaGra\Llm\OpenAiChatConfig;
use PHPUnit\Framework\TestCase;

final class OpenAiChatConfigTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        unset(
            $_ENV['OPENAI_API_KEY'],
            $_ENV['OPENAI_BASE_URL'],
            $_ENV['OPENAI_API_MODEL'],
            $_ENV['OPENAI_API_TEMPERATURE'],
            $_ENV['OPENAI_API_TOP_P'],
            $_ENV['OPENAI_API_MAX_OUT']
        );
    }

    public function test_from_env_with_all_defaults(): void
    {
        $_ENV['OPENAI_API_KEY'] = 'test-api-key';

        $config = OpenAiChatConfig::fromEnv();

        self::assertSame('test-api-key', $config->apiKey);
        self::assertSame('gpt-4o-mini', $config->model);
        self::assertNull($config->baseUrl);
        self::assertSame(0.7, $config->defaultTemperature);
        self::assertSame(1.0, $config->defaultTopP);
        self::assertNull($config->defaultMaxTokens);
    }

    public function test_from_env_with_custom_values(): void
    {
        $_ENV['OPENAI_API_KEY'] = 'custom-key';
        $_ENV['OPENAI_BASE_URL'] = 'https://custom.openai.com';
        $_ENV['OPENAI_API_MODEL'] = 'gpt-4o';
        $_ENV['OPENAI_API_TEMPERATURE'] = '0.9';
        $_ENV['OPENAI_API_TOP_P'] = '0.95';
        $_ENV['OPENAI_API_MAX_OUT'] = '2000';

        $config = OpenAiChatConfig::fromEnv();

        self::assertSame('custom-key', $config->apiKey);
        self::assertSame('gpt-4o', $config->model);
        self::assertSame('https://custom.openai.com', $config->baseUrl);
        self::assertSame(0.9, $config->defaultTemperature);
        self::assertSame(0.95, $config->defaultTopP);
        self::assertSame(2000, $config->defaultMaxTokens);
    }

    public function test_from_env_with_empty_base_url(): void
    {
        $_ENV['OPENAI_API_KEY'] = 'test-key';
        $_ENV['OPENAI_BASE_URL'] = '';

        $config = OpenAiChatConfig::fromEnv();

        self::assertNull($config->baseUrl, 'Empty string should convert to null.');
    }

    public function test_from_env_with_zero_max_tokens(): void
    {
        $_ENV['OPENAI_API_KEY'] = 'test-key';
        $_ENV['OPENAI_API_MAX_OUT'] = '0';

        $config = OpenAiChatConfig::fromEnv();

        self::assertNull($config->defaultMaxTokens, 'Zero max tokens should be treated as null.');
    }

    public function test_from_env_throws_when_api_key_missing(): void
    {
        unset($_ENV['OPENAI_API_KEY']);
        if (getenv('OPENAI_API_KEY') !== false) {
            putenv('OPENAI_API_KEY');
        }

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('OPENAI_API_KEY');

        OpenAiChatConfig::fromEnv();
    }

    public function test_from_env_handles_numeric_string_conversion(): void
    {
        $_ENV['OPENAI_API_KEY'] = 'test-key';
        $_ENV['OPENAI_API_TEMPERATURE'] = '0.5';
        $_ENV['OPENAI_API_TOP_P'] = '0.8';

        $config = OpenAiChatConfig::fromEnv();

        self::assertSame(0.5, $config->defaultTemperature);
        self::assertSame(0.8, $config->defaultTopP);
    }

    public function test_constructor_directly(): void
    {
        $config = new OpenAiChatConfig(
            apiKey: 'direct-key',
            model: 'gpt-4',
            baseUrl: 'https://api.test.com',
            defaultTemperature: 0.8,
            defaultTopP: 0.9,
            defaultMaxTokens: 4000
        );

        self::assertSame('direct-key', $config->apiKey);
        self::assertSame('gpt-4', $config->model);
        self::assertSame('https://api.test.com', $config->baseUrl);
        self::assertSame(0.8, $config->defaultTemperature);
        self::assertSame(0.9, $config->defaultTopP);
        self::assertSame(4000, $config->defaultMaxTokens);
    }
}
