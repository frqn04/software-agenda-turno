<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dental Clinic Internal System Configuration
    |--------------------------------------------------------------------------
    | Configuración consolidada para el sistema interno de clínica odontológica
    | que combina configuraciones específicas del dominio médico
    */

    // Configuración de la clínica
    'clinic' => [
        'name' => 'Clínica Odontológica Interna',
        'address' => '',
        'phone' => '',
        'email' => '',
        'license' => '',
        'timezone' => 'America/Argentina/Buenos_Aires',
    ],

    // Horarios de trabajo
    'working_hours' => [
        'start' => '08:00',
        'end' => '20:00',
        'lunch_start' => '12:00',
        'lunch_end' => '14:00',
        'appointment_duration' => 30,
        'buffer_time' => 15,
    ],

    // Límites del sistema interno
    'limits' => [
        'max_concurrent_users' => 50,
        'max_appointments_per_day' => 100,
        'max_patients_per_doctor' => 500,
        'max_file_size' => 20971520, // 20MB
        'session_timeout' => 240, // 4 horas
    ],

    // Roles de usuario internos
    'user_roles' => [
        'admin' => [
            'name' => 'Administrador',
            'permissions' => ['*'],
            'dashboard' => 'admin.dashboard',
        ],
        'doctor' => [
            'name' => 'Doctor',
            'permissions' => [
                'patients.view',
                'patients.edit',
                'appointments.manage',
                'medical_records.manage',
                'treatments.manage',
            ],
            'dashboard' => 'doctor.dashboard',
        ],
        'receptionist' => [
            'name' => 'Recepcionista',
            'permissions' => [
                'patients.view',
                'patients.create',
                'appointments.create',
                'appointments.view',
                'appointments.edit',
                'payments.manage',
            ],
            'dashboard' => 'receptionist.dashboard',
        ],
        'operator' => [
            'name' => 'Operador',
            'permissions' => [
                'patients.view',
                'appointments.view',
                'reports.view',
                'system.backup',
            ],
            'dashboard' => 'operator.dashboard',
        ],
    ],

    // Configuración de seguridad interna
    'security' => [
        'require_2fa' => false, // No necesario para sistema interno
        'password_min_length' => 6,
        'max_login_attempts' => 3,
        'lockout_duration' => 15, // minutos
        'audit_actions' => true,
        'ip_whitelist' => '127.0.0.1,192.168.1.0/24',
    ],

    // Configuración de notificaciones
    'notifications' => [
        'appointment_reminders' => true,
        'reminder_hours_before' => 24,
        'email_notifications' => true,
        'sms_notifications' => false,
        'internal_alerts' => true,
    ],

    // Configuración de reportes
    'reports' => [
        'enabled' => true,
        'retention_months' => 24,
        'export_formats' => ['pdf', 'excel', 'csv'],
        'scheduled_reports' => [
            'daily_appointments' => true,
            'weekly_summary' => true,
            'monthly_revenue' => true,
        ],
    ],

    // Configuración de respaldos
    'backup' => [
        'enabled' => true,
        'frequency' => 'daily',
        'retention_days' => 30,
        'include_files' => true,
        'compress' => true,
        'verify_integrity' => true,
    ],

    // Configuración de integración
    'integrations' => [
        'email_provider' => 'smtp',
        'sms_provider' => null,
        'payment_gateway' => null,
        'calendar_sync' => false,
    ],

    // Configuración de cache específica para clínica
    'cache' => [
        'patient_records_ttl' => 3600, // 1 hora
        'appointments_ttl' => 1800, // 30 minutos
        'doctor_schedules_ttl' => 7200, // 2 horas
        'reports_ttl' => 86400, // 24 horas
    ],

    // Configuración de logging para clínica
    'logging' => [
        'audit_level' => 'info',
        'patient_access_log' => true,
        'appointment_changes_log' => true,
        'user_actions_log' => true,
        'system_events_log' => true,
    ],

];
