<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Embedding/OpenAiEmbeddingConfigTest.php

namespace ParaGra\Tests\Embedding;

use ParaGra\Embedding\OpenAiEmbeddingConfig;
use ParaGra\Exception\ConfigurationException;
use PHPUnit\Framework\TestCase;

final class OpenAiEmbeddingConfigTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        unset(
            $_ENV['OPENAI_API_KEY'],
            $_ENV['OPENAI_EMBED_MODEL'],
            $_ENV['OPENAI_EMBED_BASE_URL'],
            $_ENV['OPENAI_EMBED_MAX_BATCH'],
            $_ENV['OPENAI_EMBED_DIMENSIONS']
        );
    }

    public function test_from_env_with_defaults(): void
    {
        $_ENV['OPENAI_API_KEY'] = 'embed-key';

        $config = OpenAiEmbeddingConfig::fromEnv();

        self::assertSame('embed-key', $config->apiKey);
        self::assertSame('text-embedding-3-small', $config->model);
        self::assertNull($config->baseUrl);
        self::assertSame(2048, $config->maxBatchSize);
        self::assertSame(1536, $config->defaultDimensions);
    }

    public function test_from_env_with_custom_values(): void
    {
        $_ENV['OPENAI_API_KEY'] = 'override-key';
        $_ENV['OPENAI_EMBED_MODEL'] = 'text-embedding-3-large';
        $_ENV['OPENAI_EMBED_BASE_URL'] = 'https://proxy.example/v1';
        $_ENV['OPENAI_EMBED_MAX_BATCH'] = '512';
        $_ENV['OPENAI_EMBED_DIMENSIONS'] = '256';

        $config = OpenAiEmbeddingConfig::fromEnv();

        self::assertSame('override-key', $config->apiKey);
        self::assertSame('text-embedding-3-large', $config->model);
        self::assertSame('https://proxy.example/v1', $config->baseUrl);
        self::assertSame(512, $config->maxBatchSize);
        self::assertSame(256, $config->defaultDimensions);
    }

    public function test_from_env_treats_zero_dimension_and_batch_as_null_and_default(): void
    {
        $_ENV['OPENAI_API_KEY'] = 'test-key';
        $_ENV['OPENAI_EMBED_DIMENSIONS'] = '0';
        $_ENV['OPENAI_EMBED_MAX_BATCH'] = '0';

        $config = OpenAiEmbeddingConfig::fromEnv();

        self::assertSame(2048, $config->maxBatchSize, 'Zero batch should reset to default.');
        self::assertNull($config->defaultDimensions, 'Zero dimension string should become null.');
    }

    public function test_from_env_requires_api_key(): void
    {
        unset($_ENV['OPENAI_API_KEY']);
        if (getenv('OPENAI_API_KEY') !== false) {
            putenv('OPENAI_API_KEY');
        }

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('OPENAI_API_KEY');

        OpenAiEmbeddingConfig::fromEnv();
    }
}
