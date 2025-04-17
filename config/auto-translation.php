<?php

declare(strict_types=1);

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
            'deepl' => [
                'api_key'  => env('DEEPL_API_KEY'),
                'free_api' => env('DEEPL_FREE_API', true),
                'timeout'  => env('DEEPL_TIMEOUT', 10),
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
        'target' => explode(',', env('AUTO_TRANSLATION_TARGET_LOCALES', '')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scan Configuration
    |--------------------------------------------------------------------------
    |
    | Configure directories and file extensions to scan for translations
    |
    */
    'scan'    => [
        'directories' => [
            'app',
            'resources/views',
            'resources/js/pages',
        ],
        'extensions'  => [
            '.blade.php',
            '.vue',
        ],
    ],
];
