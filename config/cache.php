<?php

use Illuminate\Support\Str;

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Configuration for Dental Clinic System
    |--------------------------------------------------------------------------
    | Configuración de cache optimizada para sistema interno de clínica
    | odontológica con estrategias específicas para datos médicos
    */

    'default' => 'file', // File cache para sistema interno

    /*
    |--------------------------------------------------------------------------
    | Cache Stores - Optimized for Medical Data
    |--------------------------------------------------------------------------
    | Stores configurados para diferentes tipos de datos de la clínica:
    | - file: Para datos generales (mejor para sistema interno)
    | - database: Para datos críticos que requieren persistencia
    | - array: Para testing y datos temporales
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => null,
            'table' => 'cache',
            'lock_connection' => null,
            'lock_table' => null,
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        // Cache específico para datos médicos críticos
        'medical_records' => [
            'driver' => 'database',
            'connection' => null,
            'table' => 'medical_cache',
            'lock_connection' => null,
            'lock_table' => 'medical_cache_locks',
        ],

        // Cache para citas y horarios (datos que cambian frecuentemente)
        'appointments' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/appointments'),
            'lock_path' => storage_path('framework/cache/appointments'),
        ],

        // Cache para reportes (datos pesados)
        'reports' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/reports'),
            'lock_path' => storage_path('framework/cache/reports'),
        ],

        // Mantenemos Redis y Memcached para escalabilidad futura
        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix - Optimized for Clinic
    |--------------------------------------------------------------------------
    | Prefix específico para evitar colisiones con otros sistemas
    | en la red local de la clínica
    */

    'prefix' => 'clinic-dental-cache-',

    /*
    |--------------------------------------------------------------------------
    | Cache TTL Configuration for Medical Data
    |--------------------------------------------------------------------------
    | Tiempos de vida específicos para diferentes tipos de datos médicos
    */

    'ttl' => [
        'patient_records' => 3600,      // 1 hora - datos que cambian poco
        'appointments' => 1800,         // 30 minutos - datos que cambian frecuentemente
        'doctor_schedules' => 7200,     // 2 horas - horarios relativamente estables
        'reports' => 86400,             // 24 horas - reportes pesados
        'user_sessions' => 14400,       // 4 horas - sesiones de usuario
        'medical_history' => 21600,     // 6 horas - historias clínicas
        'system_config' => 43200,       // 12 horas - configuración del sistema
    ],

];
