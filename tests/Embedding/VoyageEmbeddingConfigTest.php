<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Embedding/VoyageEmbeddingConfigTest.php

namespace ParaGra\Tests\Embedding;

use InvalidArgumentException;
use ParaGra\Embedding\VoyageEmbeddingConfig;
use ParaGra\Exception\ConfigurationException;
use PHPUnit\Framework\TestCase;

final class VoyageEmbeddingConfigTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        $vars = [
            'VOYAGE_API_KEY',
            'VOYAGE_EMBED_MODEL',
            'VOYAGE_EMBED_BASE_URL',
            'VOYAGE_EMBED_ENDPOINT',
            'VOYAGE_EMBED_MAX_BATCH',
            'VOYAGE_EMBED_TIMEOUT',
            'VOYAGE_EMBED_INPUT_TYPE',
            'VOYAGE_EMBED_TRUNCATE',
            'VOYAGE_EMBED_ENCODING',
            'VOYAGE_EMBED_DIMENSIONS',
        ];

        foreach ($vars as $var) {
            unset($_ENV[$var]);
            if (getenv($var) !== false) {
                putenv($var);
            }
        }
    }

    public function test_from_env_with_defaults(): void
    {
        $_ENV['VOYAGE_API_KEY'] = 'vk';

        $config = VoyageEmbeddingConfig::fromEnv();

        self::assertSame('vk', $config->apiKey);
        self::assertSame('voyage-3', $config->model);
        self::assertSame('https://api.voyageai.com', $config->baseUri);
        self::assertSame('/v1/embeddings', $config->endpoint);
        self::assertSame(128, $config->maxBatchSize);
        self::assertSame(30, $config->timeout);
        self::assertSame('document', $config->inputType);
        self::assertTrue($config->truncate);
        self::assertSame('float', $config->encodingFormat);
        self::assertSame(1024, $config->defaultDimensions);
    }

    public function test_from_env_with_custom_values(): void
    {
        $_ENV['VOYAGE_API_KEY'] = 'custom-key';
        $_ENV['VOYAGE_EMBED_MODEL'] = 'voyage-3-large';
        $_ENV['VOYAGE_EMBED_BASE_URL'] = 'https://proxy.example';
        $_ENV['VOYAGE_EMBED_ENDPOINT'] = '/embeddings';
        $_ENV['VOYAGE_EMBED_MAX_BATCH'] = '16';
        $_ENV['VOYAGE_EMBED_TIMEOUT'] = '5';
        $_ENV['VOYAGE_EMBED_INPUT_TYPE'] = 'query';
        $_ENV['VOYAGE_EMBED_TRUNCATE'] = 'false';
        $_ENV['VOYAGE_EMBED_ENCODING'] = 'FLOAT';
        $_ENV['VOYAGE_EMBED_DIMENSIONS'] = '2048';

        $config = VoyageEmbeddingConfig::fromEnv();

        self::assertSame('custom-key', $config->apiKey);
        self::assertSame('voyage-3-large', $config->model);
        self::assertSame('https://proxy.example', $config->baseUri);
        self::assertSame('/embeddings', $config->endpoint);
        self::assertSame(16, $config->maxBatchSize);
        self::assertSame(5, $config->timeout);
        self::assertSame('query', $config->inputType);
        self::assertFalse($config->truncate);
        self::assertSame('float', $config->encodingFormat);
        self::assertSame(2048, $config->defaultDimensions);
    }

    public function test_from_env_requires_api_key(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('VOYAGE_API_KEY');

        VoyageEmbeddingConfig::fromEnv();
    }

    public function test_from_env_rejects_invalid_input_type(): void
    {
        $_ENV['VOYAGE_API_KEY'] = 'vk';
        $_ENV['VOYAGE_EMBED_INPUT_TYPE'] = 'unsupported';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('VOYAGE_EMBED_INPUT_TYPE');

        VoyageEmbeddingConfig::fromEnv();
    }

    public function test_from_env_rejects_invalid_encoding(): void
    {
        $_ENV['VOYAGE_API_KEY'] = 'vk';
        $_ENV['VOYAGE_EMBED_ENCODING'] = '???';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('VOYAGE_EMBED_ENCODING');

        VoyageEmbeddingConfig::fromEnv();
    }

    public function test_from_env_rejects_invalid_dimensions(): void
    {
        $_ENV['VOYAGE_API_KEY'] = 'vk';
        $_ENV['VOYAGE_EMBED_DIMENSIONS'] = '-100';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('VOYAGE_EMBED_DIMENSIONS');

        VoyageEmbeddingConfig::fromEnv();
    }
}
