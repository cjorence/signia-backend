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
        'auth/*'
    ],

    'allowed_methods' => ['*'],

    // Explicitly listing origins is required when 'supports_credentials' is true
    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // This MUST be true for Laravel Sanctum's cookie-based auth to work
    'supports_credentials' => true,

];