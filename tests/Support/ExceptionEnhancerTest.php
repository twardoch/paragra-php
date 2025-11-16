<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Support/ExceptionEnhancerTest.php

namespace ParaGra\Tests\Support;

use ParaGra\Support\ExceptionEnhancer;
use PHPUnit\Framework\TestCase;
use Ragie\Api\ApiException;
use RuntimeException;

final class ExceptionEnhancerTest extends TestCase
{
    public function test_enhance_api_exception_adds_http_status(): void
    {
        $original = new ApiException('Original message', 429);

        $enhanced = ExceptionEnhancer::enhanceApiException($original);

        self::assertStringContainsString('[429]', $enhanced->getMessage());
        self::assertStringContainsString('Original message', $enhanced->getMessage());
    }

    public function test_enhance_api_exception_extracts_detail_from_json_response(): void
    {
        $responseBody = json_encode(['detail' => 'Rate limit exceeded']) ?: '';
        $original = new ApiException('API error', 429, null, $responseBody);

        $enhanced = ExceptionEnhancer::enhanceApiException($original);

        self::assertStringContainsString('Detail: Rate limit exceeded', $enhanced->getMessage());
    }

    public function test_enhance_api_exception_extracts_message_from_json_response(): void
    {
        $responseBody = json_encode(['message' => 'Invalid request']) ?: '';
        $original = new ApiException('API error', 400, null, $responseBody);

        $enhanced = ExceptionEnhancer::enhanceApiException($original);

        self::assertStringContainsString('Detail: Invalid request', $enhanced->getMessage());
    }

    public function test_enhance_api_exception_adds_context(): void
    {
        $original = new ApiException('API error', 500);
        $context = ['query' => 'test query', 'user_id' => 123];

        $enhanced = ExceptionEnhancer::enhanceApiException($original, $context);

        self::assertStringContainsString('Context:', $enhanced->getMessage());
        self::assertStringContainsString('query=test query', $enhanced->getMessage());
        self::assertStringContainsString('user_id=123', $enhanced->getMessage());
    }

    public function test_enhance_api_exception_handles_array_context(): void
    {
        $original = new ApiException('API error', 500);
        $context = ['filters' => ['type' => 'doc', 'status' => 'active']];

        $enhanced = ExceptionEnhancer::enhanceApiException($original, $context);

        self::assertStringContainsString('filters=', $enhanced->getMessage());
    }

    public function test_get_user_message_returns_rate_limit_message_for_429(): void
    {
        $exception = new ApiException('Rate limit error', 429);

        $message = ExceptionEnhancer::getUserMessage($exception);

        self::assertSame('Rate limit exceeded. Please try again in a moment.', $message);
    }

    public function test_get_user_message_returns_auth_message_for_401(): void
    {
        $exception = new ApiException('Unauthorized', 401);

        $message = ExceptionEnhancer::getUserMessage($exception);

        self::assertSame('Authentication failed. Please check your API key.', $message);
    }

    public function test_get_user_message_returns_auth_message_for_403(): void
    {
        $exception = new ApiException('Forbidden', 403);

        $message = ExceptionEnhancer::getUserMessage($exception);

        self::assertSame('Authentication failed. Please check your API key.', $message);
    }

    public function test_get_user_message_returns_server_error_for_500(): void
    {
        $exception = new ApiException('Internal server error', 500);

        $message = ExceptionEnhancer::getUserMessage($exception);

        self::assertSame('Service temporarily unavailable. Please try again later.', $message);
    }

    public function test_get_user_message_returns_client_error_for_400(): void
    {
        $exception = new ApiException('Bad request', 400);

        $message = ExceptionEnhancer::getUserMessage($exception);

        self::assertSame('Invalid request. Please check your input.', $message);
    }

    public function test_get_user_message_returns_generic_message_for_non_api_exception(): void
    {
        $exception = new RuntimeException('Something went wrong');

        $message = ExceptionEnhancer::getUserMessage($exception);

        self::assertSame('An error occurred while processing your request.', $message);
    }

    public function test_get_user_message_returns_generic_message_for_unknown_status(): void
    {
        $exception = new ApiException('Unknown error', 0);

        $message = ExceptionEnhancer::getUserMessage($exception);

        self::assertSame('An error occurred while processing your request.', $message);
    }
}
