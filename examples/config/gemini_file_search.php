<?php

declare(strict_types=1);

// this_file: paragra-php/examples/config/gemini_file_search.php

return [
    'priority_pools' => [
        [
            [
                'provider' => 'gemini',
                'model' => 'gemini-2.0-flash-exp',
                'api_key' => (string) getenv('GOOGLE_API_KEY'),
                'solution' => [
                    'type' => 'gemini-file-search',
                    'vector_store' => [
                        'datastore' => (string) (getenv('GEMINI_DATASTORE_ID') ?: getenv('GEMINI_CORPUS_ID')),
                    ],
                    'generation' => [
                        'temperature' => 0.4,
                        'maxOutputTokens' => 2048,
                    ],
                    'safety' => [
                        'confidence' => 'block_low_and_above',
                    ],
                    'metadata' => [
                        'tier' => 'paid',
                        'notes' => 'Native Gemini File Search fallback',
                    ],
                ],
            ],
        ],
    ],
];
