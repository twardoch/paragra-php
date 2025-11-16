<?php

declare(strict_types=1);

// this_file: paragra-php/tests/Llm/PromptBuilderTest.php

namespace ParaGra\Tests\Llm;

use ParaGra\Llm\PromptBuilder;
use PHPUnit\Framework\TestCase;

final class PromptBuilderTest extends TestCase
{
    public function test_build_injects_question_and_context(): void
    {
        $builder = new PromptBuilder();

        $prompt = $builder->build('What is Ragie?', ['Answer 1', '', 'Answer 2']);

        self::assertStringContainsString('What is Ragie?', $prompt);
        self::assertStringContainsString('Answer 1', $prompt);
        self::assertStringContainsString('Answer 2', $prompt);
        self::assertStringNotContainsString('  ', $prompt, 'Prompt should trim blank entries.');
    }

    public function test_custom_template_is_applied(): void
    {
        $template = 'Q: {{question}} -- C: {{context}}';
        $builder = new PromptBuilder($template);

        $prompt = $builder->build('Why?', ['Because']);

        self::assertSame('Q: Why? -- C: Because', $prompt);
    }
}
