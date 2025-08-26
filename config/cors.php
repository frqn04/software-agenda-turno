<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CORS Configuration for Internal Dental Clinic System
    |--------------------------------------------------------------------------
    | Configuración optimizada para sistema interno de clínica odontológica
    | con seguridad mejorada para entorno local/interno
    */

    'paths' => [
        'api/*', 
        'sanctum/csrf-cookie',
        'login',
        'logout',
        'admin/*',
        'dashboard/*'
    ],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Solo origins locales para sistema interno
    'allowed_origins' => env('CORS_ALLOWED_ORIGINS') ? 
        explode(',', env('CORS_ALLOWED_ORIGINS')) : 
        [
            'http://localhost:3000', 
            'http://localhost:8080', 
            'http://localhost:8000',
            'http://127.0.0.1:8000',
            'http://192.168.1.100:8000', // IP de red local de la clínica
        ],

    'allowed_origins_patterns' => [
        '/^https?:\/\/localhost(:\d+)?$/',
        '/^https?:\/\/127\.0\.0\.1(:\d+)?$/',
        '/^https?:\/\/192\.168\.1\.\d+(:\d+)?$/', // Red local clínica
    ],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
        'X-Clinic-Token', // Token personalizado para clínica
    ],

    'exposed_headers' => [
        'X-Clinic-Version',
        'X-Request-ID'
    ],

    'max_age' => 86400, // 24 horas para sistema interno

    'supports_credentials' => true,

];
