<?php

declare(strict_types=1);

// this_file: paragra-php/src/Llm/PromptBuilder.php

namespace ParaGra\Llm;

final class PromptBuilder
{
    public const DEFAULT_TEMPLATE = <<<'PROMPT'
You are a helpful AI assistant. Answer the user's question based on the context provided below.

CONTEXT:
{{context}}

QUESTION:
{{question}}

Provide a clear, concise answer based on the context. If the context doesn't contain relevant information, say so.
PROMPT;

    private string $template;

    public function __construct(?string $template = null)
    {
        $this->template = $template ?? self::DEFAULT_TEMPLATE;
    }

    /**
     * @param string[] $contextChunks
     */
    public function build(string $question, array $contextChunks): string
    {
        $context = trim(implode("\n\n", array_filter($contextChunks, static fn (string $chunk): bool => $chunk !== '')));

        return str_replace(
            ['{{question}}', '{{context}}'],
            [$question, $context],
            $this->template
        );
    }
}
