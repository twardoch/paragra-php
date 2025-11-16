<?php

declare(strict_types=1);

// this_file: paragra-php/src/Llm/ChatResponse.php

namespace ParaGra\Llm;

final class ChatResponse
{
    public function __construct(
        private string $text,
        private ChatUsage $usage,
        private mixed $rawResponse
    ) {
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getUsage(): ChatUsage
    {
        return $this->usage;
    }

    /**
     * @api
     */
    public function getRawResponse(): mixed
    {
        return $this->rawResponse;
    }
}
