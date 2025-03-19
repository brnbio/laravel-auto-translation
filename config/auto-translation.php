<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default settings
    |--------------------------------------------------------------------------
    |
    | Default configuration for auto-translation service
    |
    */
    'enabled' => env('AUTO_TRANSLATION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Translation Service Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which translation service to use and its settings
    |
    */
    'service' => [
        'default' => env('AUTO_TRANSLATION_SERVICE', 'deepl'),

        'services' => [
            'null' => [
                // No configuration needed for null driver
            ],

            'deepl' => [
                'api_key' => env('DEEPL_API_KEY'),
                'free_api' => env('DEEPL_FREE_API', true), // Set to true for DeepL API Free
                'timeout' => env('DEEPL_TIMEOUT', 10), // Request timeout in seconds
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Locales Configuration
    |--------------------------------------------------------------------------
    |
    | Configure source locale and target locales for translation
    |
    */
    'locales' => [
        'source' => env('AUTO_TRANSLATION_SOURCE_LOCALE', 'en'),
        'target' => explode(',', env('AUTO_TRANSLATION_TARGET_LOCALES', 'de,fr,es')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scan Configuration
    |--------------------------------------------------------------------------
    |
    | Configure directories and file extensions to scan for translations
    |
    */
    'scan' => [
        'directories' => [
            'app',
            'resources/views',
            'resources/js/pages',
        ],
        'extensions' => [
            'php',
            'vue'
        ],
    ],
];
