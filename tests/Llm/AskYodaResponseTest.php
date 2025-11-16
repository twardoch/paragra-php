<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Llm/AskYodaResponseTest.php

namespace ParaGra\Tests\Llm;

use ParaGra\Llm\AskYodaResponse;
use PHPUnit\Framework\TestCase;

final class AskYodaResponseTest extends TestCase
{
    public function test_constructs_from_array(): void
    {
        $data = [
            'cost' => 0.001611,
            'result' => 'This is the answer',
            'llm_provider' => 'google',
            'llm_model' => 'gemini-2.0-flash-exp',
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
                'total_tokens' => 150,
            ],
            'chunks_ids' => ['id1', 'id2', 'id3'],
        ];

        $response = new AskYodaResponse($data);

        self::assertSame(0.001611, $response->getCost());
        self::assertSame('This is the answer', $response->getResult());
        self::assertSame('google', $response->getLlmProvider());
        self::assertSame('gemini-2.0-flash-exp', $response->getLlmModel());
        self::assertSame(100, $response->getInputTokens());
        self::assertSame(50, $response->getOutputTokens());
        self::assertSame(150, $response->getTotalTokens());
        self::assertCount(3, $response->getChunkIds());
        self::assertSame(3, $response->getChunkCount());
    }

    public function test_handles_missing_fields(): void
    {
        $response = new AskYodaResponse([]);

        self::assertSame(0.0, $response->getCost());
        self::assertSame('', $response->getResult());
        self::assertSame('', $response->getLlmProvider());
        self::assertSame('', $response->getLlmModel());
        self::assertSame(0, $response->getInputTokens());
        self::assertSame(0, $response->getOutputTokens());
        self::assertSame(0, $response->getTotalTokens());
        self::assertCount(0, $response->getChunkIds());
        self::assertSame(0, $response->getChunkCount());
    }

    public function test_to_array_returns_expected_structure(): void
    {
        $data = [
            'cost' => 0.001,
            'result' => 'Answer text',
            'llm_provider' => 'openai',
            'llm_model' => 'gpt-4',
            'usage' => [
                'input_tokens' => 200,
                'output_tokens' => 100,
                'total_tokens' => 300,
            ],
            'chunks_ids' => ['id1', 'id2'],
        ];

        $response = new AskYodaResponse($data);
        $array = $response->toArray();

        self::assertArrayHasKey('answer', $array);
        self::assertArrayHasKey('cost', $array);
        self::assertArrayHasKey('llm_provider', $array);
        self::assertArrayHasKey('llm_model', $array);
        self::assertArrayHasKey('usage', $array);
        self::assertArrayHasKey('chunks_count', $array);
        self::assertArrayHasKey('chunk_ids', $array);

        self::assertSame('Answer text', $array['answer']);
        self::assertSame(0.001, $array['cost']);
        self::assertSame(2, $array['chunks_count']);
    }

    public function test_get_usage_returns_full_usage_array(): void
    {
        $usageData = [
            'input_tokens' => 500,
            'output_tokens' => 250,
            'total_tokens' => 750,
            'custom_field' => 'value',
        ];

        $response = new AskYodaResponse(['usage' => $usageData]);

        self::assertSame($usageData, $response->getUsage());
    }
}
