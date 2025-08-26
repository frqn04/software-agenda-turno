<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Filesystem Configuration for Dental Clinic System
    |--------------------------------------------------------------------------
    | Configuración de sistemas de archivos optimizada para clínica odontológica
    | con manejo seguro de historias clínicas, radiografías y documentos médicos
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks - Optimized for Medical Files
    |--------------------------------------------------------------------------
    | Discos configurados específicamente para diferentes tipos de archivos
    | médicos con seguridad y organización apropiada para clínica
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Disco específico para historias clínicas (privado y seguro)
        'medical_records' => [
            'driver' => 'local',
            'root' => storage_path('app/private/medical_records'),
            'serve' => false, // No servir directamente por seguridad
            'throw' => false,
            'report' => false,
            'visibility' => 'private',
        ],

        // Disco para radiografías e imágenes médicas
        'xrays' => [
            'driver' => 'local',
            'root' => storage_path('app/private/xrays'),
            'serve' => false,
            'throw' => false,
            'report' => false,
            'visibility' => 'private',
        ],

        // Disco para documentos de pacientes (consentimientos, etc.)
        'patient_documents' => [
            'driver' => 'local',
            'root' => storage_path('app/private/patient_docs'),
            'serve' => false,
            'throw' => false,
            'report' => false,
            'visibility' => 'private',
        ],

        // Disco para respaldos de la clínica
        'backups' => [
            'driver' => 'local',
            'root' => storage_path('app/private/backups'),
            'serve' => false,
            'throw' => false,
            'report' => false,
            'visibility' => 'private',
        ],

        // Disco para reportes generados
        'reports' => [
            'driver' => 'local',
            'root' => storage_path('app/private/reports'),
            'serve' => false,
            'throw' => false,
            'report' => false,
            'visibility' => 'private',
        ],

        // Disco para archivos temporales de la clínica
        'temp' => [
            'driver' => 'local',
            'root' => storage_path('app/temp'),
            'serve' => false,
            'throw' => false,
            'report' => false,
            'visibility' => 'private',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links - Optimized for Clinic
    |--------------------------------------------------------------------------
    | Links simbólicos solo para archivos públicos seguros
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
