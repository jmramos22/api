<?php

$allowedOrigins = env('CORS_ALLOWED_ORIGINS');

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins
        ? array_values(array_filter(array_map('trim', explode(',', $allowedOrigins))))
        : [
            'http://localhost:8000',
            'http://127.0.0.1:8000',
        ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];