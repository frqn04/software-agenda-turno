<?php

use Monolog\Handler\NullHandler;

return [
    /*
    |--------------------------------------------------------------------------
    | Logging Mínimo para Sistema Interno de Clínica
    |--------------------------------------------------------------------------
    | Configuración básica para errores críticos y auditoría médica esencial
    */

    'default' => 'daily',

    'deprecations' => [
        'channel' => 'null',
        'trace' => false,
    ],

    'channels' => [

        // Log diario básico para errores del sistema
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/clinic.log'),
            'level' => 'error', // Solo errores y críticos
            'days' => 7, // 7 días básicos
            'replace_placeholders' => true,
        ],

        // Para auditoría médica básica (acceso a historias clínicas)
        'medical_audit' => [
            'driver' => 'daily',
            'path' => storage_path('logs/medical_audit.log'),
            'level' => 'info',
            'days' => 30, // 30 días para auditoría básica
            'replace_placeholders' => true,
        ],

        // Para silenciar logs innecesarios
        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        // Para emergencias del sistema
        'emergency' => [
            'path' => storage_path('logs/emergency.log'),
        ],

    ],

];
