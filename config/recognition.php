<?php

return [
    // Private service URL and credential. Never expose these through Nuxt public config.
    'url' => env('RECOGNITION_SERVICE_URL', 'http://127.0.0.1:5000'),
    'token' => env('FSL_SERVICE_TOKEN', ''),
];
