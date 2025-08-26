<?php

/**
 * Configuración de bootstrap personalizada para sistema de turnos odontológicos
 * Sistema interno para clínica odontológica - Solo personal autorizado
 */

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

// Configuración de base de datos para sistemas médicos
Schema::defaultStringLength(191);

// Configuración de timeouts para consultas médicas críticas
DB::listen(function ($query) {
    if ($query->time > 1000) { // Queries > 1 segundo
        Log::warning('Slow query detected in medical system', [
            'sql' => $query->sql,
            'time' => $query->time,
            'bindings' => $query->bindings
        ]);
    }
});

// Configuración de cache para datos médicos sensibles
Cache::extend('medical_secure', function ($app) {
    return Cache::repository(new \Illuminate\Cache\MemcachedStore(
        $app['memcached.connector']->connect(
            config('cache.stores.memcached.servers'),
            config('cache.stores.memcached.persistent_id'),
            config('cache.stores.memcached.options', [])
        ),
        'medical_' . config('app.env') . '_'
    ));
});

// Configuración de logs específicos para auditoría médica
Log::build([
    'driver' => 'daily',
    'path' => storage_path('logs/medical-audit.log'),
    'level' => 'info',
    'days' => 90, // Retener 90 días para cumplimiento
    'permission' => 0644,
]);

// Configuración de memoria para procesamiento de datos médicos
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300); // 5 minutos para reportes

// Configuración de zona horaria para sistema médico argentino
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Constantes del sistema médico
if (!defined('DENTAL_SYSTEM_CONSTANTS')) {
    define('DENTAL_SYSTEM_CONSTANTS', true);
    
    // Tipos de usuario del sistema interno de clínica odontológica
    define('USER_ROLES', [
        'ADMIN' => 'admin',
        'DOCTOR' => 'doctor',
        'RECEPTIONIST' => 'receptionist',
        'OPERATOR' => 'operator',
    ]);
    
    // Estados de turnos
    define('APPOINTMENT_STATES', [
        'PROGRAMADO' => 'programado',
        'CONFIRMADO' => 'confirmado',
        'EN_CURSO' => 'en_curso',
        'COMPLETADO' => 'completado',
        'CANCELADO' => 'cancelado',
        'NO_ASISTIO' => 'no_asistio',
        'REPROGRAMADO' => 'reprogramado',
    ]);
    
    // Prioridades para tratamientos odontológicos
    define('DENTAL_PRIORITIES', [
        'URGENCIA_DOLOR' => 1, // Dolor intenso
        'URGENCIA_TRAUMA' => 1, // Traumatismo dental
        'ALTA' => 2, // Infecciones, endodoncia
        'NORMAL' => 3, // Citas regulares, limpiezas
        'MANTENIMIENTO' => 4, // Ortodoncia, controles
        'ESTETICA' => 5, // Blanqueamientos, estética
    ]);
    
    // Configuración de notificaciones para personal interno
    define('NOTIFICATION_CHANNELS', [
        'EMAIL' => 'email',
        'SMS' => 'sms', // Solo para urgencias
        'IN_APP' => 'in_app',
        'DESKTOP' => 'desktop', // Notificaciones del sistema interno
    ]);
    
    // Configuración de integración
    define('INTEGRATION_TIMEOUTS', [
        'SMS_TIMEOUT' => 30,
        'EMAIL_TIMEOUT' => 45,
        'PAYMENT_TIMEOUT' => 60,
        'CALENDAR_TIMEOUT' => 30,
        'MEDICAL_RECORDS_TIMEOUT' => 90,
    ]);
    
    // Límites del sistema optimizados para clínica odontológica interna
    define('SYSTEM_LIMITS', [
        'MAX_APPOINTMENTS_PER_DAY_PATIENT' => 5, // Más flexible para tratamientos múltiples
        'MAX_APPOINTMENTS_PER_MONTH_PATIENT' => 20, // Ortodoncia requiere citas frecuentes
        'MAX_CONCURRENT_USERS' => 50, // Personal interno ampliado: admin + doctores + recepcionistas + operadores
        'MAX_FILE_UPLOAD_SIZE' => 20971520, // 20MB para radiografías y documentos odontológicos
        'MAX_PATIENT_RECORDS_PER_REQUEST' => 100, // Para búsquedas internas amplias
    ]);
    
    // Configuración de seguridad para sistema interno odontológico
    define('SECURITY_CONFIG', [
        'SESSION_TIMEOUT_MINUTES' => 240, // 4 horas para jornada laboral completa
        'MAX_LOGIN_ATTEMPTS' => 3, // Más estricto para sistema interno
        'LOCKOUT_DURATION_MINUTES' => 15, // Bloqueo más corto para personal interno
        'PASSWORD_MIN_LENGTH' => 6, // Más simple para uso interno
        'REQUIRE_2FA_FOR_DOCTORS' => false, // No necesario para sistema interno
        'AUDIT_LOG_RETENTION_DAYS' => 365, // 1 año es suficiente para clínica pequeña
    ]);
    
    // Configuración de reportes
    define('REPORT_CONFIG', [
        'MAX_REPORT_DAYS' => 365,
        'CACHE_REPORTS_MINUTES' => 60,
        'EXPORT_FORMATS' => ['pdf', 'excel', 'csv'],
        'MAX_EXPORT_RECORDS' => 10000,
    ]);
}

// Helpers globales para el sistema médico
if (!function_exists('format_dni')) {
    function format_dni(string $dni): string {
        return number_format($dni, 0, '', '.');
    }
}

if (!function_exists('format_phone')) {
    function format_phone(string $phone): string {
        $clean = preg_replace('/[^\d]/', '', $phone);
        if (strlen($clean) === 10) {
            return preg_replace('/(\d{3})(\d{3})(\d{4})/', '($1) $2-$3', $clean);
        }
        return $phone;
    }
}

if (!function_exists('medical_date_format')) {
    function medical_date_format($date): string {
        return \Carbon\Carbon::parse($date)->format('d/m/Y H:i');
    }
}

if (!function_exists('is_urgent_dental_case')) {
    function is_urgent_dental_case(string $symptoms): bool {
        $urgentKeywords = ['dolor', 'sangrado', 'trauma', 'fractura', 'infección', 'hinchazón'];
        $symptoms = strtolower($symptoms);
        
        foreach ($urgentKeywords as $keyword) {
            if (strpos($symptoms, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('calculate_age')) {
    function calculate_age($birthDate): int {
        return \Carbon\Carbon::parse($birthDate)->age;
    }
}

if (!function_exists('is_business_day')) {
    function is_business_day($date): bool {
        $carbon = \Carbon\Carbon::parse($date);
        return !$carbon->isWeekend();
    }
}

if (!function_exists('get_appointment_duration')) {
    function get_appointment_duration(string $treatmentType): int {
        return APPOINTMENT_DURATIONS[$treatmentType] ?? APPOINTMENT_DURATIONS['Consulta General'];
    }
}

if (!function_exists('mask_medical_data')) {
    function mask_medical_data(string $data, int $visibleChars = 3): string {
        $length = strlen($data);
        if ($length <= $visibleChars) {
            return str_repeat('*', $length);
        }
        return substr($data, 0, $visibleChars) . str_repeat('*', $length - $visibleChars);
    }
}

if (!function_exists('generate_patient_id')) {
    function generate_patient_id(string $prefix = 'PAC'): string {
        return $prefix . date('Ymd') . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }
}

// Configuración específica para entorno de producción
if (app()->environment('production')) {
    // Configuraciones adicionales de seguridad para producción
    ini_set('expose_php', 'Off');
    ini_set('display_errors', 'Off');
    ini_set('log_errors', 'On');
    
    // Configurar headers de seguridad adicionales
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Log de inicialización del sistema odontológico
Log::info('Dental clinic system bootstrap completed', [
    'system_type' => 'internal_dental_clinic',
    'environment' => app()->environment(),
    'max_concurrent_users' => SYSTEM_LIMITS['MAX_CONCURRENT_USERS'],
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'timezone' => date_default_timezone_get(),
    'timestamp' => now()->toDateTimeString(),
]);
