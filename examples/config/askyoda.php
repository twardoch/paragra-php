<?php

declare(strict_types=1);

// this_file: paragra-php/examples/config/askyoda.php

return [
    'priority_pools' => [
        [
            [
                'provider' => 'edenai',
                'model' => 'askyoda',
                'api_key' => (string) getenv('EDENAI_API_KEY'),
                'solution' => [
                    'type' => 'askyoda',
                    'askyoda_api_key' => (string) getenv('EDENAI_API_KEY'),
                    'project_id' => (string) getenv('EDENAI_ASKYODA_PROJECT'),
                    'default_options' => [
                        'k' => 10,
                        'min_score' => 0.35,
                        'temperature' => 0.9,
                        'max_tokens' => 6000,
                    ],
                    'llm' => [
                        'provider' => 'google',
                        'model' => 'gemini-1.5-flash',
                    ],
                    'metadata' => [
                        'tier' => 'paid',
                        'notes' => 'EdenAI AskYoda fallback when Ragie rate-limits',
                    ],
                ],
            ],
        ],
    ],
];
