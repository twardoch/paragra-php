<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Embedding/GeminiEmbeddingConfigTest.php

namespace ParaGra\Tests\Embedding;

use Gemini\Enums\TaskType;
use ParaGra\Embedding\GeminiEmbeddingConfig;
use ParaGra\Exception\ConfigurationException;
use PHPUnit\Framework\TestCase;

final class GeminiEmbeddingConfigTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        unset(
            $_ENV['GEMINI_EMBED_API_KEY'],
            $_ENV['GEMINI_EMBED_MODEL'],
            $_ENV['GEMINI_EMBED_MAX_BATCH'],
            $_ENV['GEMINI_EMBED_DIMENSIONS'],
            $_ENV['GEMINI_EMBED_BASE_URL'],
            $_ENV['GEMINI_EMBED_TASK_TYPE'],
            $_ENV['GEMINI_EMBED_TITLE'],
            $_ENV['GOOGLE_API_KEY']
        );

        putenv('GEMINI_EMBED_API_KEY');
        putenv('GEMINI_EMBED_MODEL');
        putenv('GEMINI_EMBED_MAX_BATCH');
        putenv('GEMINI_EMBED_DIMENSIONS');
        putenv('GEMINI_EMBED_BASE_URL');
        putenv('GEMINI_EMBED_TASK_TYPE');
        putenv('GEMINI_EMBED_TITLE');
        putenv('GOOGLE_API_KEY');
    }

    public function test_from_env_with_google_fallback_and_overrides(): void
    {
        $_ENV['GOOGLE_API_KEY'] = 'google-key';
        $_ENV['GEMINI_EMBED_MODEL'] = 'embedding-001';
        $_ENV['GEMINI_EMBED_MAX_BATCH'] = '120';
        $_ENV['GEMINI_EMBED_DIMENSIONS'] = '3072';
        $_ENV['GEMINI_EMBED_BASE_URL'] = 'https://custom.example/v1beta/';
        $_ENV['GEMINI_EMBED_TASK_TYPE'] = 'retrieval_document';
        $_ENV['GEMINI_EMBED_TITLE'] = 'ParaGra batch';

        $config = GeminiEmbeddingConfig::fromEnv();

        self::assertSame('google-key', $config->apiKey);
        self::assertSame('embedding-001', $config->model);
        self::assertSame(120, $config->maxBatchSize);
        self::assertSame('https://custom.example/v1beta/', $config->baseUrl);
        self::assertSame(TaskType::RETRIEVAL_DOCUMENT, $config->taskType);
        self::assertSame('ParaGra batch', $config->title);
        self::assertSame(3072, $config->defaultDimensions);
    }

    public function test_from_env_requires_some_api_key(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('GEMINI_EMBED_API_KEY');

        GeminiEmbeddingConfig::fromEnv();
    }

    public function test_from_env_rejects_invalid_dimension_override(): void
    {
        $_ENV['GEMINI_EMBED_API_KEY'] = 'key';
        $_ENV['GEMINI_EMBED_MODEL'] = 'embedding-001';
        $_ENV['GEMINI_EMBED_DIMENSIONS'] = '1024';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not support overriding dimensions');

        GeminiEmbeddingConfig::fromEnv();
    }

    public function test_from_env_rejects_unknown_task_type(): void
    {
        $_ENV['GEMINI_EMBED_API_KEY'] = 'key';
        $_ENV['GEMINI_EMBED_TASK_TYPE'] = 'not-a-task';

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Unsupported task type');

        GeminiEmbeddingConfig::fromEnv();
    }
}
