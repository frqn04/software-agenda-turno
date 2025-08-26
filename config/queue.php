<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Queue Configuration for Dental Clinic System
    |--------------------------------------------------------------------------
    | Configuración de colas optimizada para sistema interno de clínica
    | odontológica con trabajos de auditoría y respaldos médicos
    */

    'default' => 'database', // Database queue para sistema interno

    /*
    |--------------------------------------------------------------------------
    | Queue Connections - Optimized for Medical Tasks
    |--------------------------------------------------------------------------
    | Conexiones configuradas para tareas médicas específicas como
    | recordatorios de citas, respaldos y auditoría
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => null, // Usar conexión por defecto
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90, // 90 segundos para reintentos
            'after_commit' => false,
        ],

        // Cola específica para tareas médicas críticas
        'medical' => [
            'driver' => 'database',
            'connection' => null,
            'table' => 'jobs',
            'queue' => 'medical_tasks',
            'retry_after' => 60, // Menor tiempo para tareas médicas críticas
            'after_commit' => true, // Confirmar transacciones para datos médicos
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching - Simplified for Clinic
    |--------------------------------------------------------------------------
    | Configuración de batching para tareas médicas en lote
    */

    'batching' => [
        'database' => 'mysql', // Usar MySQL para clínica
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs - Medical Tasks Focus
    |--------------------------------------------------------------------------
    | Configuración para jobs fallidos con énfasis en tareas médicas
    | críticas que requieren seguimiento especial
    */

    'failed' => [
        'driver' => 'database-uuids',
        'database' => 'mysql', // Usar MySQL para clínica
        'table' => 'failed_jobs',
    ],

];
