<?php

// this_file: paragra-php/src/Support/ExceptionEnhancer.php

declare(strict_types=1);

namespace ParaGra\Support;

use Ragie\Api\ApiException;
use Throwable;

/**
 * Enhances exceptions with additional context for better debugging.
 *
 * Provides utilities to add helpful information to exceptions,
 * especially for API errors.
 *
 * @example
 * ```php
 * try {
 *     $client->retrieve($query);
 * } catch (ApiException $final e) {
 *     $enhanced = ExceptionEnhancer::enhance($e, ['query' => $query]);
 *     error_log($enhanced->getMessage());
 * }
 * ```
 */
class ExceptionEnhancer
{
    /**
     * Enhance an API exception with additional context.
     *
     * Extracts HTTP status, response body, and adds context to the message.
     *
     * @param ApiException $exception The original API exception
     * @param array<string, mixed> $context Additional context (query, params, etc.)
     * @return ApiException Enhanced exception with better message
     */
    public static function enhanceApiException(ApiException $exception, array $context = []): ApiException
    {
        $parts = [];

        // Add HTTP status
        $code = $exception->getCode();
        if ($code > 0) {
            $parts[] = sprintf('[%d]', $code);
        }

        // Add original message
        $parts[] = $exception->getMessage();

        // Add response body if available
        $responseBody = $exception->getResponseBody();
        if ($responseBody !== null && is_string($responseBody)) {
            $decoded = json_decode($responseBody, true);
            if (isset($decoded['detail'])) {
                $parts[] = sprintf('Detail: %s', $decoded['detail']);
            } elseif (isset($decoded['message'])) {
                $parts[] = sprintf('Detail: %s', $decoded['message']);
            }
        }

        // Add context
        if (!empty($context)) {
            $contextStr = [];
            foreach ($context as $key => $value) {
                if (is_scalar($value)) {
                    $contextStr[] = sprintf('%s=%s', $key, $value);
                } elseif (is_array($value)) {
                    $contextStr[] = sprintf('%s=%s', $key, json_encode($value));
                }
            }
            if (!empty($contextStr)) {
                $parts[] = sprintf('Context: %s', implode(', ', $contextStr));
            }
        }

        $enhancedMessage = implode(' | ', $parts);

        // Return new exception with enhanced message
        return new ApiException(
            $enhancedMessage,
            $exception->getCode(),
            $exception->getResponseHeaders(),
            $exception->getResponseBody()
        );
    }

    /**
     * Get a user-friendly error message from any exception.
     *
     * Strips technical details for end-user display.
     *
     * @param Throwable $exception Any exception
     * @return string User-friendly error message
     */
    public static function getUserMessage(Throwable $exception): string
    {
        if ($exception instanceof ApiException) {
            $code = $exception->getCode();

            if ($code === 429) {
                return 'Rate limit exceeded. Please try again in a moment.';
            }

            if ($code === 401 || $code === 403) {
                return 'Authentication failed. Please check your API key.';
            }

            if ($code >= 500) {
                return 'Service temporarily unavailable. Please try again later.';
            }

            if ($code >= 400) {
                return 'Invalid request. Please check your input.';
            }
        }

        return 'An error occurred while processing your request.';
    }
}
