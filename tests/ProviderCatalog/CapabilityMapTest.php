<?php

declare(strict_types=1);

// this_file: paragra-php/tests/ProviderCatalog/CapabilityMapTest.php

namespace ParaGra\Tests\ProviderCatalog;

use InvalidArgumentException;
use ParaGra\ProviderCatalog\CapabilityMap;
use PHPUnit\Framework\TestCase;

final class CapabilityMapTest extends TestCase
{
    public function testFromArrayNormalizesFlags(): void
    {
        $map = CapabilityMap::fromArray([
            'llm_chat' => 1,
            'embeddings' => true,
            'vector_store' => false,
        ]);

        self::assertTrue($map->llmChat());
        self::assertTrue($map->embeddings());
        self::assertFalse($map->vectorStore());
        self::assertFalse($map->imageGeneration());
        self::assertSame(
            [
                'llm_chat' => true,
                'embeddings' => true,
                'vector_store' => false,
                'moderation' => false,
                'image_generation' => false,
                'byok' => false,
            ],
            $map->toArray()
        );
    }

    public function testUnknownCapabilityThrows(): void
    {
        $map = CapabilityMap::fromArray([]);

        $this->expectException(InvalidArgumentException::class);
        $map->supports('non_existent');
    }

    public function testRejectsUnexpectedKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CapabilityMap::fromArray([
            'llm_chat' => true,
            'foo' => true,
        ]);
    }
}
