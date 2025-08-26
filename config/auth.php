<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Configuration for Dental Clinic System
    |--------------------------------------------------------------------------
    | Configuración de autenticación optimizada para sistema interno
    | de clínica odontológica con roles específicos del personal médico
    */

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards - Optimized for Clinic Staff
    |--------------------------------------------------------------------------
    | Guards configurados para el personal interno de la clínica:
    | - web: Para acceso general del personal (admin, receptionist, operator)
    | - doctor: Guard específico para doctores con permisos médicos
    | - api: Para integraciones y acceso programático
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        
        'doctor' => [
            'driver' => 'session',
            'provider' => 'doctors',
        ],
        
        'api' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers - Optimized for Clinic Staff
    |--------------------------------------------------------------------------
    | Providers configurados para diferentes tipos de usuarios de la clínica:
    | - users: Personal general (admin, receptionist, operator)
    | - doctors: Doctores con acceso a historias clínicas
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
        
        'doctors' => [
            'driver' => 'eloquent',
            'model' => App\Models\Doctor::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Reset Configuration - Optimized for Clinic
    |--------------------------------------------------------------------------
    | Configuración de reset de passwords optimizada para personal interno
    | con tiempos más cortos para mayor seguridad en entorno médico
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 30, // 30 minutos para personal general
            'throttle' => 60,
        ],
        
        'doctors' => [
            'provider' => 'doctors',
            'table' => 'password_reset_tokens',
            'expire' => 15, // 15 minutos para doctores (mayor seguridad)
            'throttle' => 120, // 2 minutos entre intentos
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout - Optimized for Medical Environment
    |--------------------------------------------------------------------------
    | Timeout reducido para confirmación de password en entorno médico
    | donde la seguridad es crítica (1 hora en lugar de 3)
    */

    'password_timeout' => 3600, // 1 hora para entorno médico

];
