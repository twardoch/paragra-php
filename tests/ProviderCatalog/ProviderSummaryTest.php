<?php

declare(strict_types=1);

// this_file: paragra-php/tests/ProviderCatalog/ProviderSummaryTest.php

namespace ParaGra\Tests\ProviderCatalog;

use InvalidArgumentException;
use ParaGra\ProviderCatalog\CapabilityMap;
use ParaGra\ProviderCatalog\ProviderSummary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProviderSummary::class)]
#[UsesClass(CapabilityMap::class)]
final class ProviderSummaryTest extends TestCase
{
    public function test_from_array_normalizes_optional_fields(): void
    {
        $data = [
            'slug' => '  voyage  ',
            'display_name' => ' Voyage AI ',
            'description' => '  Provider description ',
            'api_key_env' => '',
            'base_url' => '',
            'capabilities' => [
                'llm_chat' => true,
                'embeddings' => true,
                'vector_store' => false,
                'moderation' => false,
                'image_generation' => false,
                'byok' => false,
            ],
            'models' => ['voyage-large', '', 42],
            'embedding_dimensions' => [
                'voyage-large' => 1536,
                'voyage-lite' => '512',
                123 => 1024,
                'invalid' => 0,
            ],
            'preferred_vector_store' => '',
            'default_models' => [
                'chat' => 'voyage-large',
                'image' => '',
            ],
            'default_solution' => [
                'type' => 'rag',
                'metadata' => ['tier' => 'free'],
            ],
            'metadata' => [
                'tier' => 'free',
                'region' => 'us',
            ],
        ];

        $summary = ProviderSummary::fromArray($data);

        self::assertSame('voyage', $summary->slug());
        self::assertSame('Voyage AI', $summary->displayName());
        self::assertSame('Provider description', $summary->description());
        self::assertNull($summary->apiKeyEnv());
        self::assertNull($summary->baseUrl());
        self::assertTrue($summary->capabilities()->llmChat());
        self::assertTrue($summary->capabilities()->embeddings());
        self::assertFalse($summary->capabilities()->vectorStore());
        self::assertSame(2, $summary->modelCount());
        self::assertSame(['voyage-large', '42'], $summary->models());
        self::assertSame(
            [
                'voyage-large' => 1536,
                'voyage-lite' => 512,
            ],
            $summary->embeddingDimensions()
        );
        self::assertNull($summary->preferredVectorStore());
        self::assertSame(['chat' => 'voyage-large'], $summary->defaultModels());
        self::assertSame(['type' => 'rag', 'metadata' => ['tier' => 'free']], $summary->defaultSolution());
        self::assertSame(['tier' => 'free', 'region' => 'us'], $summary->metadata());
    }

    public function test_from_array_when_required_fields_missing_or_invalid_then_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ProviderSummary::fromArray([
            'display_name' => 'Missing slug',
            'capabilities' => [],
        ]);
    }

    public function test_from_array_when_slug_empty_then_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ProviderSummary::fromArray([
            'slug' => '   ',
            'display_name' => 'Voyage',
            'capabilities' => [
                'llm_chat' => true,
                'embeddings' => true,
                'vector_store' => false,
                'moderation' => false,
                'image_generation' => false,
                'byok' => false,
            ],
        ]);
    }
}
