<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // Added 'sanctum/csrf-cookie' and 'login' to ensure these paths are not blocked
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'login',
        'logout',
        'auth/*',
    ],

    'allowed_methods' => ['*'],

    // Set the deployed frontend origins explicitly; separate multiple origins with commas.
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',',
        env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://127.0.0.1:3000')
    )))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // This application authenticates with explicit Bearer tokens, not cross-origin cookies.
    'supports_credentials' => false,

];
