<?php

declare(strict_types=1);

// this_file: paragra-php/examples/config/ragie_openai.php

return [
    'priority_pools' => [
        [
            [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'api_key' => (string) getenv('OPENAI_API_KEY'),
                'solution' => [
                    'type' => 'ragie',
                    'ragie_api_key' => (string) getenv('RAGIE_API_KEY'),
                    'default_options' => [
                        'top_k' => 8,
                        'rerank' => true,
                        'partition' => getenv('RAGIE_PARTITION') ?: null,
                    ],
                    'metadata' => [
                        'tier' => 'paid',
                        'notes' => 'High-reliability Ragie + OpenAI fallback',
                    ],
                ],
            ],
        ],
    ],
];
