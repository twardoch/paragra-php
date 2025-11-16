<?php

declare(strict_types=1);

// this_file: paragra-php/tests/VectorStore/VectorNamespaceTest.php

namespace ParaGra\Tests\VectorStore;

use InvalidArgumentException;
use ParaGra\VectorStore\VectorNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VectorNamespace::class)]
final class VectorNamespaceTest extends TestCase
{
    public function test_it_normalizes_name_collection_and_metadata(): void
    {
        $namespace = new VectorNamespace(
            name: ' Customer Docs ',
            collection: ' Primary ',
            eventuallyConsistent: true,
            metadata: ['region' => 'us-west-2', 'tier' => 'hot'],
        );

        self::assertSame('customer-docs', $namespace->getName());
        self::assertSame('Primary', $namespace->getCollection());
        self::assertTrue($namespace->isEventuallyConsistent());
        self::assertSame(['region' => 'us-west-2', 'tier' => 'hot'], $namespace->getMetadata());
        self::assertSame(
            [
                'name' => 'customer-docs',
                'collection' => 'Primary',
                'eventual_consistency' => true,
                'metadata' => ['region' => 'us-west-2', 'tier' => 'hot'],
            ],
            $namespace->toArray(),
        );
    }

    public function test_it_rejects_invalid_namespace_names(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Vector namespace name');

        new VectorNamespace('!!!');
    }

    public function test_it_rejects_invalid_metadata_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Metadata lists must be indexed sequentially');

        new VectorNamespace('docs', metadata: ['invalid' => ['nested' => ['oops']]]);
    }

    public function test_it_nulls_out_blank_collection_values(): void
    {
        $namespace = new VectorNamespace('docs', collection: '  ');
        self::assertNull($namespace->getCollection());
    }

    public function test_it_rejects_overly_long_names(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new VectorNamespace(str_repeat('segment', 11));
    }

    public function test_it_rejects_empty_metadata_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new VectorNamespace('docs', metadata: [' ' => 'value']);
    }

    public function test_it_rejects_metadata_lists_with_non_scalars(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new VectorNamespace('docs', metadata: ['tags' => ['primary', ['bad']]]);
    }

    public function test_it_accepts_scalar_metadata_lists(): void
    {
        $namespace = new VectorNamespace('docs', metadata: ['tags' => ['primary', 'beta']]);
        self::assertSame(['tags' => ['primary', 'beta']], $namespace->getMetadata());
    }
}
