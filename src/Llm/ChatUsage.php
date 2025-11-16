<?php

declare(strict_types=1);

// this_file: paragra-php/src/Llm/ChatUsage.php

namespace ParaGra\Llm;

final class ChatUsage
{
    public function __construct(
        public int $promptTokens,
        public int $completionTokens,
        public int $totalTokens
    ) {
    }
}
