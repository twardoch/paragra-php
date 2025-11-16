<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Util/ConfigValidatorTest.php

namespace ParaGra\Tests\Util;

use ParaGra\Exception\ConfigurationException;
use ParaGra\Util\ConfigValidator;
use PHPUnit\Framework\TestCase;

final class ConfigValidatorTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        unset($_ENV['TEST_VAR'], $_ENV['TEST_VAR_EMPTY'], $_ENV['TEST_VAR_WHITESPACE']);
    }

    public function test_require_env_returns_value_when_variable_exists(): void
    {
        $_ENV['TEST_VAR'] = 'test_value';

        $result = ConfigValidator::requireEnv('TEST_VAR');

        self::assertSame('test_value', $result);
    }

    public function test_require_env_trims_whitespace(): void
    {
        $_ENV['TEST_VAR'] = '  test_value  ';

        $result = ConfigValidator::requireEnv('TEST_VAR');

        self::assertSame('test_value', $result);
    }

    public function test_require_env_throws_when_variable_missing(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Required environment variable "MISSING_VAR" is not set');

        ConfigValidator::requireEnv('MISSING_VAR');
    }

    public function test_require_env_throws_when_variable_empty(): void
    {
        $_ENV['TEST_VAR_EMPTY'] = '';

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Environment variable "TEST_VAR_EMPTY" is set but empty');

        ConfigValidator::requireEnv('TEST_VAR_EMPTY');
    }

    public function test_require_env_throws_when_variable_whitespace_only(): void
    {
        $_ENV['TEST_VAR_WHITESPACE'] = '   ';

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Environment variable "TEST_VAR_WHITESPACE" is set but empty');

        ConfigValidator::requireEnv('TEST_VAR_WHITESPACE');
    }

    public function test_require_all_validates_multiple_variables(): void
    {
        $_ENV['VAR1'] = 'value1';
        $_ENV['VAR2'] = 'value2';
        $_ENV['VAR3'] = 'value3';

        ConfigValidator::requireAll(['VAR1', 'VAR2', 'VAR3']);

        self::assertTrue(true, 'No exception thrown.');
    }

    public function test_require_all_throws_on_first_missing_variable(): void
    {
        unset($_ENV['VAR1'], $_ENV['VAR2'], $_ENV['VAR3']);

        $_ENV['VAR1'] = 'value1';
        $_ENV['VAR3'] = 'value3';

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Required environment variable "VAR2" is not set');

        ConfigValidator::requireAll(['VAR1', 'VAR2', 'VAR3']);
    }

    public function test_get_env_returns_value_when_exists(): void
    {
        $_ENV['TEST_VAR'] = 'test_value';

        $result = ConfigValidator::getEnv('TEST_VAR', 'default');

        self::assertSame('test_value', $result);
    }

    public function test_get_env_returns_default_when_missing(): void
    {
        $result = ConfigValidator::getEnv('MISSING_VAR', 'default_value');

        self::assertSame('default_value', $result);
    }

    public function test_get_env_returns_default_when_empty(): void
    {
        $_ENV['TEST_VAR_EMPTY'] = '';

        $result = ConfigValidator::getEnv('TEST_VAR_EMPTY', 'default_value');

        self::assertSame('default_value', $result);
    }

    public function test_get_env_returns_empty_string_when_no_default(): void
    {
        $result = ConfigValidator::getEnv('MISSING_VAR');

        self::assertSame('', $result);
    }

    public function test_get_env_trims_whitespace(): void
    {
        $_ENV['TEST_VAR'] = '  value  ';

        $result = ConfigValidator::getEnv('TEST_VAR');

        self::assertSame('value', $result);
    }
}
