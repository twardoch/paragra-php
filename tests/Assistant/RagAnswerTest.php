<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Assistant/RagAnswerTest.php

namespace ParaGra\Tests\Assistant;

use ParaGra\Assistant\RagAnswer;
use ParaGra\Llm\ChatResponse;
use ParaGra\Llm\ChatUsage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Ragie\RetrievalResult;

#[CoversClass(RagAnswer::class)]
#[UsesClass(ChatResponse::class)]
#[UsesClass(ChatUsage::class)]
final class RagAnswerTest extends TestCase
{
    public function test_accessors_expose_context_usage_and_metadata(): void
    {
        $retrieval = $this->createStub(RetrievalResult::class);
        $retrieval->method('getChunkTexts')->willReturn(['chunk-1', 'chunk-2']);

        $usage = new ChatUsage(promptTokens: 42, completionTokens: 17, totalTokens: 59);
        $chatResponse = new ChatResponse(
            text: 'final answer',
            usage: $usage,
            rawResponse: ['id' => 'chat-123']
        );

        $answer = new RagAnswer(
            question: 'What is ParaGra?',
            answer: 'A multi-provider RAG orchestrator.',
            retrievalResult: $retrieval,
            chatResponse: $chatResponse,
            prompt: 'prompt text',
            executionTimeMs: 1200,
            metadata: [
                'fallback_used' => true,
                'fallback_provider' => 'askyoda',
                'notes' => 'rate-limit fallback',
            ]
        );

        self::assertSame('What is ParaGra?', $answer->getQuestion());
        self::assertSame('A multi-provider RAG orchestrator.', $answer->getAnswer());
        self::assertSame(['chunk-1', 'chunk-2'], $answer->getContextTexts());
        self::assertSame($usage, $answer->getChatUsage());
        self::assertSame($chatResponse, $answer->getChatResponse());
        self::assertSame('prompt text', $answer->getPrompt());
        self::assertSame(1200, $answer->getExecutionTimeMs());
        self::assertTrue($answer->isFallback());
        self::assertSame('askyoda', $answer->getFallbackProvider());
        self::assertSame('rate-limit fallback', $answer->getMetadata()['notes']);
    }

    public function test_fallback_helpers_when_metadata_missing(): void
    {
        $retrieval = $this->createStub(RetrievalResult::class);
        $retrieval->method('getChunkTexts')->willReturn([]);

        $chatResponse = new ChatResponse(
            text: 'content',
            usage: new ChatUsage(1, 1, 2),
            rawResponse: null
        );

        $answer = new RagAnswer(
            question: 'Q',
            answer: 'A',
            retrievalResult: $retrieval,
            chatResponse: $chatResponse,
            prompt: 'prompt',
            executionTimeMs: 5,
            metadata: ['source' => 'primary']
        );

        self::assertFalse($answer->isFallback());
        self::assertNull($answer->getFallbackProvider());
        self::assertSame(['source' => 'primary'], $answer->getMetadata());
    }
}
