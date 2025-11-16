<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Embedding/CohereEmbeddingConfigTest.php

namespace ParaGra\Tests\Embedding;

use InvalidArgumentException;
use ParaGra\Embedding\CohereEmbeddingConfig;
use ParaGra\Exception\ConfigurationException;
use PHPUnit\Framework\TestCase;

final class CohereEmbeddingConfigTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        unset(
            $_ENV['COHERE_API_KEY'],
            $_ENV['COHERE_EMBED_MODEL'],
            $_ENV['COHERE_EMBED_INPUT_TYPE'],
            $_ENV['COHERE_EMBED_TRUNCATE'],
            $_ENV['COHERE_EMBED_TYPES'],
            $_ENV['COHERE_EMBED_BASE_URL'],
            $_ENV['COHERE_EMBED_ENDPOINT'],
            $_ENV['COHERE_EMBED_MAX_BATCH']
        );
        putenv('COHERE_API_KEY');
        putenv('COHERE_EMBED_MODEL');
        putenv('COHERE_EMBED_INPUT_TYPE');
        putenv('COHERE_EMBED_TRUNCATE');
        putenv('COHERE_EMBED_TYPES');
        putenv('COHERE_EMBED_BASE_URL');
        putenv('COHERE_EMBED_ENDPOINT');
        putenv('COHERE_EMBED_MAX_BATCH');
    }

    public function test_from_env_uses_defaults(): void
    {
        $_ENV['COHERE_API_KEY'] = 'ckey';

        $config = CohereEmbeddingConfig::fromEnv();

        self::assertSame('ckey', $config->apiKey);
        self::assertSame('embed-english-v3.0', $config->model);
        self::assertSame('search_document', $config->inputType);
        self::assertNull($config->truncate);
        self::assertSame(['float'], $config->embeddingTypes);
        self::assertSame(96, $config->maxBatchSize);
        self::assertSame('https://api.cohere.ai', $config->baseUri);
        self::assertSame('/v1/embed', $config->endpoint);
        self::assertSame(1024, $config->defaultDimensions);
    }

    public function test_from_env_honors_custom_values(): void
    {
        $_ENV['COHERE_API_KEY'] = 'custom';
        $_ENV['COHERE_EMBED_MODEL'] = 'embed-english-light-v3.0';
        $_ENV['COHERE_EMBED_INPUT_TYPE'] = 'search_query';
        $_ENV['COHERE_EMBED_TRUNCATE'] = 'END';
        $_ENV['COHERE_EMBED_TYPES'] = 'float,int8';
        $_ENV['COHERE_EMBED_BASE_URL'] = 'https://proxy.example/api';
        $_ENV['COHERE_EMBED_ENDPOINT'] = '/v2024/embed';
        $_ENV['COHERE_EMBED_MAX_BATCH'] = '24';

        $config = CohereEmbeddingConfig::fromEnv();

        self::assertSame('custom', $config->apiKey);
        self::assertSame('embed-english-light-v3.0', $config->model);
        self::assertSame('search_query', $config->inputType);
        self::assertSame('END', $config->truncate);
        self::assertSame(['float', 'int8'], $config->embeddingTypes);
        self::assertSame('https://proxy.example/api', $config->baseUri);
        self::assertSame('/v2024/embed', $config->endpoint);
        self::assertSame(24, $config->maxBatchSize);
        self::assertSame(384, $config->defaultDimensions);
    }

    public function test_from_env_rejects_invalid_embedding_type(): void
    {
        $_ENV['COHERE_API_KEY'] = 'ckey';
        $_ENV['COHERE_EMBED_TYPES'] = 'float,weird';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('COHERE_EMBED_TYPES');

        CohereEmbeddingConfig::fromEnv();
    }

    public function test_from_env_requires_api_key(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('COHERE_API_KEY');

        CohereEmbeddingConfig::fromEnv();
    }
}
