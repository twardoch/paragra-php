<?php

declare(strict_types=1);

// this_file: paragra-php/config/paragra.example.php

/**
 * Example priority pool configuration for ParaGra. Copy this file to
 * config/paragra.php and replace the placeholder getenv() values with your
 * actual API keys + metadata.
 */
return [
    'provider_catalog' => __DIR__ . '/providers/catalog.php',
    'priority_pools' => [
        // Pool 1: free-tier Cerebras keys rotating every second
        [
            [
                'catalog' => [
                    'slug' => 'cerebras',
                    'model_type' => 'generation',
                    'overrides' => [
                        'api_key' => (string) getenv('CEREBRAS_API_KEY_1'),
                        'model' => 'llama-3.3-70b',
                        'solution' => [
                            'ragie_api_key' => (string) getenv('RAGIE_API_KEY'),
                            'ragie_partition' => getenv('RAGIE_PARTITION') ?: 'default',
                        ],
                    ],
                ],
                'latency_tier' => 'low',
                'cost_ceiling' => 0.00,
                'compliance' => ['internal'],
                'metadata_overrides' => [
                    'notes' => 'Preferred pool for low-cost traffic',
                ],
            ],
            [
                'catalog_slug' => 'cerebras',
                'catalog_overrides' => [
                    'api_key' => (string) getenv('CEREBRAS_API_KEY_2'),
                    'model' => 'llama-3.3-70b',
                    'solution' => [
                        'ragie_api_key' => (string) getenv('RAGIE_API_KEY'),
                    ],
                ],
                'latency_tier' => 'low',
                'cost_ceiling' => 0.00,
                'compliance' => ['internal'],
                'metadata_overrides' => [
                    'notes' => 'Companion key for rotation',
                ],
            ],
        ],

        // Pool 2: paid OpenAI fallback
        [
            [
                'catalog' => [
                    'slug' => 'openai',
                    'overrides' => [
                        'api_key' => (string) getenv('OPENAI_API_KEY'),
                        'model' => 'gpt-4o-mini',
                        'solution' => [
                            'ragie_api_key' => (string) getenv('RAGIE_API_KEY'),
                            'default_options' => [
                                'top_k' => 8,
                                'rerank' => true,
                            ],
                        ],
                    ],
                ],
                'latency_tier' => 'medium',
                'cost_ceiling' => 0.12,
                'compliance' => ['soc2', 'gdpr'],
                'metadata_overrides' => [
                    'notes' => 'Highly reliable fallback',
                ],
            ],
        ],

        // Pool 3: Gemini File Search (native Google retrieval)
        [
            [
                'catalog' => [
                    'slug' => 'gemini',
                    'model_type' => 'generation',
                    'overrides' => [
                        'api_key' => (string) getenv('GOOGLE_API_KEY'),
                        'solution' => [
                        'vector_store' => [
                            'datastore' => (string) (getenv('GEMINI_DATASTORE_ID') ?: getenv('GEMINI_CORPUS_ID')),
                        ],
                            'generation' => [
                                'safety_settings' => 'block_few',
                                'temperature' => 0.4,
                            ],
                            'metadata' => [
                                'notes' => 'Use when Ragie corpora miss',
                            ],
                        ],
                    ],
                ],
                'latency_tier' => 'paid',
                'cost_ceiling' => 0.20,
                'compliance' => ['iso27001'],
            ],
        ],
    ],
];
