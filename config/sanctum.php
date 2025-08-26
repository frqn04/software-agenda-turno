<?php

use Laravel\Sanctum\Sanctum;

return [
    /*
    |--------------------------------------------------------------------------
    | Sanctum Configuration for Internal Dental Clinic System
    |--------------------------------------------------------------------------
    | Configuración de autenticación optimizada para sistema interno
    | de clínica odontológica con seguridad mejorada
    */

    'stateful' => explode(',', 
        'localhost,localhost:3000,localhost:8080,127.0.0.1,127.0.0.1:8000,::1'
    ),

    'guard' => ['web'],

    // Token expiration optimizado para jornada laboral (8 horas = 480 minutos)
    'expiration' => 480,

    'token_prefix' => 'clinic_',

    // Middleware optimizado para clínica interna
    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
        'validate_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
    ],

    // Configuración adicional para sistema interno
    'personal_access_tokens' => [
        'enabled' => true,
        'table' => 'personal_access_tokens',
        'expires_at' => 'expires_at',
    ],

    // Configuración de API para personal de clínica
    'api' => [
        'prefix' => 'api',
        'middleware' => ['api'],
        'rate_limiting' => [
            'enabled' => true,
            'max_attempts' => 120, // 120 requests por minuto para personal interno
            'decay_minutes' => 1,
        ],
    ],

    // Configuración de seguridad para clínica
    'security' => [
        'require_confirmation' => false, // No necesario para sistema interno
        'hash_ids' => true, // Hash IDs para mayor seguridad
        'audit_tokens' => true, // Auditar uso de tokens
        'auto_logout_inactive' => 240, // Auto logout después de 4 horas inactivo
    ],

];
