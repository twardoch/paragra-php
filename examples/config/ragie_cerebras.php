<?php

declare(strict_types=1);

// this_file: paragra-php/examples/config/ragie_cerebras.php

return [
    'priority_pools' => [
        [
            [
                'provider' => 'cerebras',
                'model' => 'llama-3.3-70b',
                'api_key' => (string) getenv('CEREBRAS_API_KEY_1'),
                'solution' => [
                    'type' => 'ragie',
                    'ragie_api_key' => (string) getenv('RAGIE_API_KEY'),
                    'ragie_partition' => getenv('RAGIE_PARTITION') ?: 'default',
                    'default_options' => [
                        'top_k' => 6,
                        'rerank' => true,
                    ],
                    'metadata' => [
                        'tier' => 'free',
                        'notes' => 'Primary Cerebras key',
                    ],
                ],
            ],
            [
                'provider' => 'cerebras',
                'model' => 'llama-3.3-70b',
                'api_key' => (string) getenv('CEREBRAS_API_KEY_2'),
                'solution' => [
                    'type' => 'ragie',
                    'ragie_api_key' => (string) getenv('RAGIE_API_KEY'),
                    'metadata' => [
                        'tier' => 'free',
                        'notes' => 'Backup Cerebras key for rotation',
                    ],
                ],
            ],
        ],
    ],
];
