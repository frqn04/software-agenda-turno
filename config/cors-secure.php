<?php

return [
    'paths' => [
        'api/*', 
        'sanctum/csrf-cookie',
        'login',
        'logout',
        'register'
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => env('CORS_ALLOWED_ORIGINS') ? 
        explode(',', env('CORS_ALLOWED_ORIGINS')) : 
        ['http://localhost:3000', 'http://localhost:8080', 'http://127.0.0.1:8000'],

    'allowed_origins_patterns' => [
        env('CORS_ALLOWED_PATTERNS', '/^https?:\/\/localhost(:\d+)?$/'),
    ],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
    ],

    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
    ],

    'max_age' => env('CORS_MAX_AGE', 86400),

    'supports_credentials' => true,
];
