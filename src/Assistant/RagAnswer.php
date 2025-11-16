<?php

declare(strict_types=1);

// this_file: paragra-php/src/Assistant/RagAnswer.php

namespace ParaGra\Assistant;

use ParaGra\Llm\ChatResponse;
use ParaGra\Llm\ChatUsage;
use Ragie\RetrievalResult;

final class RagAnswer
{
    /**
     * @param array<string, mixed> $metadata Optional metadata (e.g., fallback info)
     */
    public function __construct(
        private string $question,
        private string $answer,
        private RetrievalResult $retrievalResult,
        private ChatResponse $chatResponse,
        private string $prompt,
        private int $executionTimeMs,
        private array $metadata = []
    ) {
    }

    /**
     * @api
     */
    public function getQuestion(): string
    {
        return $this->question;
    }

    public function getAnswer(): string
    {
        return $this->answer;
    }

    /**
     * @api
     */
    public function getRetrievalResult(): RetrievalResult
    {
        return $this->retrievalResult;
    }

    /**
     * @return string[]
     */
    public function getContextTexts(): array
    {
        return $this->retrievalResult->getChunkTexts();
    }

    /**
     * @api
     */
    public function getChatResponse(): ChatResponse
    {
        return $this->chatResponse;
    }

    public function getChatUsage(): ChatUsage
    {
        return $this->chatResponse->getUsage();
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    /**
     * @api
     */
    public function getExecutionTimeMs(): int
    {
        return $this->executionTimeMs;
    }

    /**
     * Get metadata (e.g., fallback information)
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Check if this answer used a fallback provider
     */
    public function isFallback(): bool
    {
        return isset($this->metadata['fallback_used']) && $this->metadata['fallback_used'] === true;
    }

    /**
     * Get the fallback provider name if fallback was used
     */
    public function getFallbackProvider(): ?string
    {
        return $this->metadata['fallback_provider'] ?? null;
    }
}
