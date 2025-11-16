<?php

// this_file: paragra-php/src/Llm/AskYodaResponse.php

declare(strict_types=1);

namespace ParaGra\Llm;

/**
 * Response from EdenAI AskYoda API
 */
class AskYodaResponse
{
    private float $cost;
    private string $result;
    private string $llmProvider;
    private string $llmModel;
    /** @var array<string, mixed> */
    private array $usage;
    /** @var array<int, string> */
    private array $chunkIds;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->cost = (float) ($data['cost'] ?? 0.0);
        $this->result = (string) ($data['result'] ?? '');
        $this->llmProvider = (string) ($data['llm_provider'] ?? '');
        $this->llmModel = (string) ($data['llm_model'] ?? '');
        $this->usage = $data['usage'] ?? [];
        $this->chunkIds = $data['chunks_ids'] ?? [];
    }

    public function getCost(): float
    {
        return $this->cost;
    }

    public function getResult(): string
    {
        return $this->result;
    }

    public function getLlmProvider(): string
    {
        return $this->llmProvider;
    }

    public function getLlmModel(): string
    {
        return $this->llmModel;
    }

    /**
     * @return array<string, mixed>
     */
    public function getUsage(): array
    {
        return $this->usage;
    }

    public function getInputTokens(): int
    {
        return (int) ($this->usage['input_tokens'] ?? 0);
    }

    public function getOutputTokens(): int
    {
        return (int) ($this->usage['output_tokens'] ?? 0);
    }

    public function getTotalTokens(): int
    {
        return (int) ($this->usage['total_tokens'] ?? 0);
    }

    /**
     * @return array<int, string>
     */
    public function getChunkIds(): array
    {
        return $this->chunkIds;
    }

    public function getChunkCount(): int
    {
        return count($this->chunkIds);
    }

    /**
     * Convert to array format compatible with RAG endpoint response
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'answer' => $this->result,
            'cost' => $this->cost,
            'llm_provider' => $this->llmProvider,
            'llm_model' => $this->llmModel,
            'usage' => $this->usage,
            'chunks_count' => $this->getChunkCount(),
            'chunk_ids' => $this->chunkIds,
        ];
    }
}
