<?php

// this_file: paragra-php/src/Util/ConfigValidator.php

declare(strict_types=1);

namespace ParaGra\Util;

use ParaGra\Exception\ConfigurationException;

/**
 * Helper for validating configuration and environment variables.
 *
 * Provides static methods for checking required configuration values
 * with clear error messages.
 *
 * @example
 * ```php
 * // Validate single required env var
 * $apiKey = ConfigValidator::requireEnv('RAGIE_API_KEY');
 *
 * // Validate multiple env vars at once
 * ConfigValidator::requireAll(['RAGIE_API_KEY', 'OPENAI_API_KEY']);
 * ```
 */
class ConfigValidator
{
    /**
     * Require that an environment variable exists and is not empty.
     *
     * @param string $varName Name of the environment variable
     *
     * @throws ConfigurationException If variable is missing or empty
     * @return string The value of the environment variable
     */
    public static function requireEnv(string $varName): string
    {
        $value = $_ENV[$varName] ?? getenv($varName);

        if ($value === false) {
            throw ConfigurationException::missingEnv($varName);
        }

        $trimmed = trim((string)$value);
        if ($trimmed === '') {
            throw ConfigurationException::emptyEnv($varName);
        }

        return $trimmed;
    }

    /**
     * Require multiple environment variables at once.
     *
     * @param array<int, string> $varNames List of required variable names
     *
     * @throws ConfigurationException If any variable is missing or empty
     */
    public static function requireAll(array $varNames): void
    {
        foreach ($varNames as $varName) {
            self::requireEnv($varName);
        }
    }

    /**
     * Get environment variable with fallback default.
     *
     * Unlike requireEnv(), this allows missing variables.
     *
     * @param string $varName Name of the environment variable
     * @param string $default Default value if variable is missing or empty
     * @return string The value or default
     */
    public static function getEnv(string $varName, string $default = ''): string
    {
        $value = $_ENV[$varName] ?? getenv($varName);

        if ($value === false) {
            return $default;
        }

        $trimmed = trim((string)$value);
        return $trimmed === '' ? $default : $trimmed;
    }
}
