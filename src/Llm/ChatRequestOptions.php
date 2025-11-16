<?php

declare(strict_types=1);

// this_file: paragra-php/src/Llm/ChatRequestOptions.php

namespace ParaGra\Llm;

final class ChatRequestOptions
{
    public function __construct(
        public ?string $model = null,
        public ?float $temperature = null,
        public ?float $topP = null,
        public ?int $maxTokens = null
    ) {
    }
}
