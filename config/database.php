<?php

use Illuminate\Support\Str;

return [
    /*
    |--------------------------------------------------------------------------
    | Database Configuration for Dental Clinic System
    |--------------------------------------------------------------------------
    | Configuración de base de datos optimizada para sistema interno
    | de clínica odontológica con MySQL/MariaDB como driver principal
    */

    'default' => env('DB_CONNECTION', 'mysql'), // MySQL por defecto para clínica

    /*
    |--------------------------------------------------------------------------
    | Database Connections - Optimized for Medical Data
    |--------------------------------------------------------------------------
    | Configuraciones específicas para manejo de datos médicos con
    | mayor seguridad y configuraciones apropiadas para clínica
    */

    'connections' => [

        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'clinica_dental'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB', // InnoDB para datos médicos críticos
            'options' => [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ],
        ],

        // Conexión específica para datos médicos críticos
        'medical' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'clinica_dental'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => 'med_',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
            'options' => [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
            ],
        ],

        // Conexión SQLite para testing y desarrollo local rápido
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => database_path('database.sqlite'),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table - Optimized for Medical System
    |--------------------------------------------------------------------------
    | Tabla de migraciones con configuración apropiada para sistema médico
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Configuration - Simplified for Internal Clinic
    |--------------------------------------------------------------------------
    | Configuración Redis simplificada para sistema interno de clínica
    | (opcional, solo si se requiere cache avanzado)
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => 'redis',
            'prefix' => 'clinic-dental-database-',
            'persistent' => false,
        ],

        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => '0',
        ],

        'cache' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', '6379'),
            'database' => '1',
        ],

    ],

];
