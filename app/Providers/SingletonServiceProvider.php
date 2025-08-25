<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Paciente;
use App\Models\Turno;
use App\Models\LogAuditoria;
use App\Observers\AuditObserver;

/**
 * Service de configuración empresarial para sistema médico
 * Implementa patrón Singleton para configuraciones globales del sistema
 */
class MedicalConfigurationService
{
    private static ?MedicalConfigurationService $instance = null;
    private array $config = [];
    private array $medicalSettings = [];
    private array $securitySettings = [];

    private function __construct()
    {
        $this->loadDefaultConfiguration();
        $this->loadMedicalConfiguration();
        $this->loadSecurityConfiguration();
    }

    public static function getInstance(): MedicalConfigurationService
    {
        if (self::$instance === null) {
            self::$instance = new self();
            Log::info('MedicalConfigurationService singleton instance created');
        }

        return self::$instance;
    }

    /**
     * Cargar configuración general por defecto
     */
    private function loadDefaultConfiguration(): void
    {
        $this->config = [
            'app' => [
                'name' => 'Sistema Médico Empresarial',
                'version' => '2.0.0',
                'timezone' => 'America/Argentina/Buenos_Aires',
                'locale' => 'es_AR',
                'environment' => app()->environment(),
            ],
            'pagination' => [
                'default_per_page' => 25,
                'max_per_page' => 100,
                'turnos_per_page' => 50,
                'pacientes_per_page' => 30,
                'doctores_per_page' => 20,
                'auditorias_per_page' => 100,
            ],
            'cache' => [
                'default_ttl' => 3600, // 1 hora
                'user_cache_ttl' => 1800, // 30 minutos
                'medical_data_ttl' => 900, // 15 minutos
                'reports_ttl' => 7200, // 2 horas
            ],
            'files' => [
                'max_upload_size' => 10240, // 10MB en KB
                'allowed_medical_formats' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'],
                'storage_path' => 'medical_documents',
                'backup_retention_days' => 90,
            ]
        ];
    }

    /**
     * Cargar configuración específica médica
     */
    private function loadMedicalConfiguration(): void
    {
        $this->medicalSettings = [
            'appointment' => [
                'default_duration' => 30, // minutos
                'min_duration' => 15,
                'max_duration' => 120,
                'min_advance_booking' => 2, // horas
                'max_advance_booking' => 90, // días
                'allow_same_day_booking' => true,
                'allow_weekend_booking' => false,
                'cancellation_deadline_hours' => 2,
                'no_show_tolerance_minutes' => 15,
                'working_hours' => [
                    'monday' => ['start' => '08:00', 'end' => '18:00'],
                    'tuesday' => ['start' => '08:00', 'end' => '18:00'],
                    'wednesday' => ['start' => '08:00', 'end' => '18:00'],
                    'thursday' => ['start' => '08:00', 'end' => '18:00'],
                    'friday' => ['start' => '08:00', 'end' => '18:00'],
                    'saturday' => ['start' => '08:00', 'end' => '13:00'],
                    'sunday' => ['start' => null, 'end' => null], // Cerrado
                ],
                'break_times' => [
                    'lunch' => ['start' => '12:00', 'end' => '13:00'],
                ],
                'slot_intervals' => [15, 30, 45, 60, 90, 120], // minutos disponibles
                'emergency_slots_per_day' => 3,
                'reminder_notifications' => [
                    'email_hours_before' => 24,
                    'sms_hours_before' => 2,
                ],
            ],
            'medical_records' => [
                'auto_save_interval' => 30, // segundos
                'history_retention_years' => 10,
                'evolution_edit_window_hours' => 24,
                'require_digital_signature' => true,
                'backup_frequency_hours' => 6,
                'encryption_enabled' => true,
                'access_log_retention_days' => 365,
            ],
            'doctor' => [
                'max_daily_appointments' => 20,
                'min_break_between_appointments' => 5, // minutos
                'specialties_limit' => 3,
                'license_validation_required' => true,
                'contract_renewal_notice_days' => 30,
                'performance_review_months' => 6,
            ],
            'patient' => [
                'max_concurrent_appointments' => 1,
                'history_access_levels' => ['full', 'limited', 'emergency_only'],
                'data_retention_years' => 10,
                'consent_required_for_data_sharing' => true,
                'minor_age_limit' => 18,
                'emergency_contact_required' => true,
            ],
            'compliance' => [
                'gdpr_enabled' => true,
                'hipaa_compliance' => true,
                'data_anonymization_years' => 7,
                'audit_trail_mandatory' => true,
                'patient_consent_tracking' => true,
                'data_breach_notification_hours' => 72,
            ]
        ];
    }

    /**
     * Cargar configuración de seguridad
     */
    private function loadSecurityConfiguration(): void
    {
        $this->securitySettings = [
            'authentication' => [
                'max_login_attempts' => 5,
                'lockout_duration_minutes' => 15,
                'session_timeout_minutes' => 120,
                'password_min_length' => 8,
                'password_require_special_chars' => true,
                'password_require_numbers' => true,
                'password_require_uppercase' => true,
                'password_expiry_days' => 90,
                'require_2fa' => false,
                'remember_me_duration_days' => 30,
            ],
            'audit' => [
                'enabled' => true,
                'log_all_database_changes' => true,
                'log_failed_access_attempts' => true,
                'log_data_exports' => true,
                'log_medical_record_access' => true,
                'retention_days' => 2555, // 7 años
                'real_time_alerts' => true,
                'critical_actions_require_confirmation' => true,
            ],
            'encryption' => [
                'medical_data_encryption' => true,
                'file_encryption' => true,
                'database_encryption' => true,
                'backup_encryption' => true,
                'key_rotation_days' => 90,
            ],
            'access_control' => [
                'role_based_access' => true,
                'ip_whitelist_enabled' => false,
                'geolocation_restrictions' => false,
                'device_registration_required' => false,
                'concurrent_sessions_limit' => 3,
            ],
            'data_protection' => [
                'anonymize_exports' => true,
                'watermark_sensitive_documents' => true,
                'prevent_screen_capture' => false,
                'secure_file_deletion' => true,
                'data_loss_prevention' => true,
            ]
        ];
    }

    /**
     * Obtener configuración por clave
     */
    public function get(string $key, $default = null)
    {
        return data_get($this->config, $key, $default);
    }

    /**
     * Obtener configuración médica por clave
     */
    public function getMedical(string $key, $default = null)
    {
        return data_get($this->medicalSettings, $key, $default);
    }

    /**
     * Obtener configuración de seguridad por clave
     */
    public function getSecurity(string $key, $default = null)
    {
        return data_get($this->securitySettings, $key, $default);
    }

    /**
     * Establecer configuración
     */
    public function set(string $key, $value): void
    {
        data_set($this->config, $key, $value);
        Log::info("Configuration updated: {$key}", ['value' => $value]);
    }

    /**
     * Establecer configuración médica
     */
    public function setMedical(string $key, $value): void
    {
        data_set($this->medicalSettings, $key, $value);
        Log::info("Medical configuration updated: {$key}", ['value' => $value]);
    }

    /**
     * Establecer configuración de seguridad
     */
    public function setSecurity(string $key, $value): void
    {
        data_set($this->securitySettings, $key, $value);
        Log::warning("Security configuration updated: {$key}");
    }

    /**
     * Obtener toda la configuración
     */
    public function getAll(): array
    {
        return [
            'general' => $this->config,
            'medical' => $this->medicalSettings,
            'security' => $this->securitySettings,
        ];
    }

    /**
     * Obtener configuración de horarios de trabajo
     */
    public function getWorkingHours(string $day = null): array
    {
        $workingHours = $this->getMedical('appointment.working_hours', []);
        
        if ($day) {
            return $workingHours[strtolower($day)] ?? ['start' => null, 'end' => null];
        }
        
        return $workingHours;
    }

    /**
     * Verificar si un día es laborable
     */
    public function isWorkingDay(string $day): bool
    {
        $hours = $this->getWorkingHours($day);
        return !empty($hours['start']) && !empty($hours['end']);
    }

    /**
     * Obtener duraciones válidas de turnos
     */
    public function getValidAppointmentDurations(): array
    {
        return $this->getMedical('appointment.slot_intervals', [15, 30, 45, 60, 90, 120]);
    }

    /**
     * Verificar si una duración de turno es válida
     */
    public function isValidAppointmentDuration(int $minutes): bool
    {
        return in_array($minutes, $this->getValidAppointmentDurations());
    }

    /**
     * Obtener configuración de notificaciones
     */
    public function getNotificationSettings(): array
    {
        return $this->getMedical('appointment.reminder_notifications', []);
    }

    /**
     * Obtener límites de carga de archivos
     */
    public function getFileUploadLimits(): array
    {
        return [
            'max_size' => $this->get('files.max_upload_size', 10240),
            'allowed_formats' => $this->get('files.allowed_medical_formats', []),
            'storage_path' => $this->get('files.storage_path', 'medical_documents'),
        ];
    }

    /**
     * Actualizar configuración desde base de datos o archivo
     */
    public function refresh(): void
    {
        $this->loadDefaultConfiguration();
        $this->loadMedicalConfiguration();
        $this->loadSecurityConfiguration();
        
        Log::info('Configuration refreshed from source');
    }

    /**
     * Validar configuración actual
     */
    public function validate(): array
    {
        $errors = [];

        // Validar horarios de trabajo
        foreach ($this->getWorkingHours() as $day => $hours) {
            if ($hours['start'] && $hours['end']) {
                if (strtotime($hours['start']) >= strtotime($hours['end'])) {
                    $errors[] = "Invalid working hours for {$day}: start time must be before end time";
                }
            }
        }

        // Validar duraciones de turnos
        $durations = $this->getValidAppointmentDurations();
        if (empty($durations) || !is_array($durations)) {
            $errors[] = "Invalid appointment durations configuration";
        }

        // Validar configuración de seguridad
        $maxAttempts = $this->getSecurity('authentication.max_login_attempts');
        if (!is_numeric($maxAttempts) || $maxAttempts < 1) {
            $errors[] = "Invalid max login attempts configuration";
        }

        return $errors;
    }

    /**
     * Exportar configuración a array
     */
    public function export(): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'version' => $this->get('app.version'),
            'environment' => $this->get('app.environment'),
            'configuration' => $this->getAll(),
        ];
    }

    /**
     * Importar configuración desde array
     */
    public function import(array $configData): bool
    {
        try {
            if (isset($configData['configuration']['general'])) {
                $this->config = $configData['configuration']['general'];
            }
            
            if (isset($configData['configuration']['medical'])) {
                $this->medicalSettings = $configData['configuration']['medical'];
            }
            
            if (isset($configData['configuration']['security'])) {
                $this->securitySettings = $configData['configuration']['security'];
            }
            
            Log::info('Configuration imported successfully');
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to import configuration', ['error' => $e->getMessage()]);
            return false;
        }
    }
}

/**
 * Service Provider para registrar servicios como singletons
 * Maneja la configuración empresarial del sistema médico
 */
class SingletonServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Registrar MedicalConfigurationService como singleton
        $this->app->singleton(MedicalConfigurationService::class, function ($app) {
            return MedicalConfigurationService::getInstance();
        });

        // Registrar servicios médicos como singletons
        $this->registerMedicalServices();
        
        // Registrar repositories como singletons
        $this->registerRepositories();
        
        // Registrar servicios de validación
        $this->registerValidationServices();
        
        // Registrar servicios de utilidades
        $this->registerUtilityServices();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Registrar observers para auditoría completa
        $this->registerMedicalObservers();
        
        // Configurar servicios de configuración
        $this->configureServices();
        
        // Validar configuración en arranque
        $this->validateConfiguration();
    }

    /**
     * Registrar servicios médicos como singletons
     */
    private function registerMedicalServices(): void
    {
        // Servicios principales del sistema médico
        $this->app->singleton(\App\Services\AppointmentValidationService::class);
        $this->app->singleton(\App\Services\TurnoService::class);
        $this->app->singleton(\App\Services\DoctorService::class);
        $this->app->singleton(\App\Services\PacienteService::class);
        $this->app->singleton(\App\Services\EvolucionService::class);
        $this->app->singleton(\App\Services\HistoriaClinicaService::class);
        
        // Servicios de especialidades
        $this->app->singleton(\App\Services\EspecialidadService::class);
        
        // Servicios de contratos médicos
        $this->app->singleton(\App\Services\DoctorContractService::class);
        
        // Servicios de notificaciones médicas
        $this->app->singleton(\App\Services\MedicalNotificationService::class);
        
        // Servicios de reportes médicos
        $this->app->singleton(\App\Services\MedicalReportService::class);
        
        // Servicios de auditoría médica
        $this->app->singleton(\App\Services\MedicalAuditService::class);
    }

    /**
     * Registrar repositories como singletons
     */
    private function registerRepositories(): void
    {
        // Repositories principales
        $this->app->singleton(\App\Repositories\TurnoRepository::class);
        $this->app->singleton(\App\Repositories\DoctorRepository::class);
        $this->app->singleton(\App\Repositories\PacienteRepository::class);
        $this->app->singleton(\App\Repositories\UserRepository::class);
        $this->app->singleton(\App\Repositories\EvolucionRepository::class);
        $this->app->singleton(\App\Repositories\HistoriaClinicaRepository::class);
        $this->app->singleton(\App\Repositories\EspecialidadRepository::class);
        $this->app->singleton(\App\Repositories\DoctorContractRepository::class);
        $this->app->singleton(\App\Repositories\LogAuditoriaRepository::class);
    }

    /**
     * Registrar servicios de validación
     */
    private function registerValidationServices(): void
    {
        $this->app->singleton(\App\Services\MedicalValidationService::class);
        $this->app->singleton(\App\Services\SecurityValidationService::class);
        $this->app->singleton(\App\Services\AppointmentConflictService::class);
        $this->app->singleton(\App\Services\DataIntegrityService::class);
    }

    /**
     * Registrar servicios de utilidades
     */
    private function registerUtilityServices(): void
    {
        $this->app->singleton(\App\Services\FileManagementService::class);
        $this->app->singleton(\App\Services\BackupService::class);
        $this->app->singleton(\App\Services\CacheManagementService::class);
        $this->app->singleton(\App\Services\SecurityMonitoringService::class);
    }

    /**
     * Registrar observers médicos
     */
    private function registerMedicalObservers(): void
    {
        // Todos los modelos tienen auditoría automática
        User::observe(AuditObserver::class);
        Doctor::observe(AuditObserver::class);
        Paciente::observe(AuditObserver::class);
        Turno::observe(AuditObserver::class);
        LogAuditoria::observe(AuditObserver::class);
        
        // Observers específicos adicionales si existen
        if (class_exists(\App\Observers\UserObserver::class)) {
            User::observe(\App\Observers\UserObserver::class);
        }
        
        if (class_exists(\App\Observers\DoctorObserver::class)) {
            Doctor::observe(\App\Observers\DoctorObserver::class);
        }
        
        if (class_exists(\App\Observers\PacienteObserver::class)) {
            Paciente::observe(\App\Observers\PacienteObserver::class);
        }
        
        if (class_exists(\App\Observers\TurnoObserver::class)) {
            Turno::observe(\App\Observers\TurnoObserver::class);
        }
    }

    /**
     * Configurar servicios después del registro
     */
    private function configureServices(): void
    {
        // Configurar timezone global
        $configService = $this->app->make(MedicalConfigurationService::class);
        $timezone = $configService->get('app.timezone', 'UTC');
        config(['app.timezone' => $timezone]);
        
        // Configurar cache según el entorno
        if ($this->app->environment('production')) {
            config(['cache.default' => 'redis']);
        }
        
        // Configurar logs médicos
        config([
            'logging.channels.medical' => [
                'driver' => 'daily',
                'path' => storage_path('logs/medical.log'),
                'level' => 'info',
                'days' => 30,
            ]
        ]);
    }

    /**
     * Validar configuración en arranque
     */
    private function validateConfiguration(): void
    {
        try {
            $configService = $this->app->make(MedicalConfigurationService::class);
            $errors = $configService->validate();
            
            if (!empty($errors)) {
                Log::warning('Configuration validation errors found', ['errors' => $errors]);
            } else {
                Log::info('Configuration validation passed');
            }
        } catch (\Exception $e) {
            Log::error('Configuration validation failed', ['error' => $e->getMessage()]);
        }
    }
}
