<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Config/ProviderSpecTest.php

namespace ParaGra\Tests\Config;

use InvalidArgumentException;
use ParaGra\Config\ProviderSpec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProviderSpec::class)]
final class ProviderSpecTest extends TestCase
{
    public function test_from_array_when_data_valid_then_returns_instance(): void
    {
        $data = [
            'provider' => ' openai ',
            'model' => ' gpt-4o-mini ',
            'api_key' => ' sk-test ',
            'solution' => [
                'type' => 'ragie',
                'ragie_api_key' => 'ragie-test',
                'ragie_partition' => 'default',
            ],
        ];

        $spec = ProviderSpec::fromArray($data);

        self::assertSame('openai', $spec->provider, 'Provider should be trimmed.');
        self::assertSame('gpt-4o-mini', $spec->model);
        self::assertSame('sk-test', $spec->apiKey);
        self::assertSame('ragie', $spec->solution['type']);

        $expected = [
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'api_key' => 'sk-test',
            'solution' => [
                'type' => 'ragie',
                'ragie_api_key' => 'ragie-test',
                'ragie_partition' => 'default',
            ],
        ];

        self::assertSame($expected, $spec->toArray(), 'Round-trip array payload should stay stable.');
    }

    public function test_from_array_when_required_key_missing_then_throws(): void
    {
        $data = [
            'model' => 'gpt-4o-mini',
            'api_key' => 'sk-test',
            'solution' => ['type' => 'ragie'],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('provider');

        ProviderSpec::fromArray($data);
    }

    public function test_from_array_when_solution_not_array_then_throws(): void
    {
        $data = [
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'api_key' => 'sk-test',
            'solution' => 'ragie',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('solution');

        ProviderSpec::fromArray($data);
    }

    public function test_from_array_when_string_empty_then_throws(): void
    {
        $data = [
            'provider' => '   ',
            'model' => 'gpt-4o-mini',
            'api_key' => 'sk-test',
            'solution' => ['type' => 'ragie'],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('provider');

        ProviderSpec::fromArray($data);
    }
}
