<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services for Internal Dental Clinic System
    |--------------------------------------------------------------------------
    | Servicios mínimos para sistema interno de clínica odontológica
    | Solo servicios esenciales para funcionamiento básico
    */

    // Servicios de email básicos (opcionales para sistema interno)
    'postmark' => [
        'token' => null, // No necesario para sistema interno
    ],

    'resend' => [
        'key' => null, // No necesario para sistema interno
    ],

    // Servicios de respaldo local (sin dependencias externas)
    'backup' => [
        'local_backup' => [
            'enabled' => true,
            'path' => storage_path('backups'),
            'retention_days' => 30,
        ],
        'network_backup' => [
            'enabled' => false, // Configurar según red local
            'path' => '', // Ruta de red local si se necesita
        ],
    ],

    // Servicios de análisis interno (solo estadísticas locales)
    'analytics' => [
        'enabled' => false, // Sin tracking externo
        'internal_reports' => true, // Solo reportes internos
    ],

];
