<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Paciente;
use App\Models\Turno;
use App\Models\LogAuditoria;
use App\Observers\AuditObserver;

/**
 * Singleton para configuración de la aplicación
 */
class ConfigurationService
{
    private static ?ConfigurationService $instance = null;
    private array $config = [];

    private function __construct()
    {
        $this->loadDefaultConfiguration();
    }

    public static function getInstance(): ConfigurationService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Cargar configuración por defecto
     */
    private function loadDefaultConfiguration(): void
    {
        $this->config = [
            'appointment' => [
                'default_duration' => 30, // minutos
                'max_duration' => 120,     // minutos
                'min_advance_booking' => 1, // horas
                'max_advance_booking' => 90, // días
                'allow_same_day_booking' => true,
                'working_hours' => [
                    'start' => '08:00',
                    'end' => '18:00'
                ]
            ],
            'security' => [
                'max_login_attempts' => 5,
                'lockout_duration' => 15, // minutos
                'session_timeout' => 120, // minutos
                'password_min_length' => 8,
                'require_2fa' => false
            ],
            'audit' => [
                'enabled' => true,
                'retention_days' => 365,
                'log_failed_attempts' => true,
                'log_data_access' => true
            ],
            'notifications' => [
                'email_enabled' => true,
                'sms_enabled' => false,
                'reminder_hours_before' => 24
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
     * Establecer configuración
     */
    public function set(string $key, $value): void
    {
        data_set($this->config, $key, $value);
    }

    /**
     * Obtener toda la configuración
     */
    public function getAll(): array
    {
        return $this->config;
    }

    /**
     * Actualizar configuración desde base de datos o archivo
     */
    public function refresh(): void
    {
        // Aquí podrías cargar configuración desde base de datos
        // o desde archivos de configuración personalizados
        $this->loadDefaultConfiguration();
    }
}

/**
 * Service Provider para registrar servicios como singletons
 */
class SingletonServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Registrar ConfigurationService como singleton
        $this->app->singleton(ConfigurationService::class, function ($app) {
            return ConfigurationService::getInstance();
        });

        // Registrar otros servicios como singletons
        $this->app->singleton(\App\Services\AppointmentValidationService::class);
        $this->app->singleton(\App\Services\TurnoService::class);
        $this->app->singleton(\App\Services\DoctorService::class);
        $this->app->singleton(\App\Services\PacienteService::class);

        // Registrar Repositories como singletons
        $this->app->singleton(\App\Repositories\TurnoRepository::class);
        $this->app->singleton(\App\Repositories\DoctorRepository::class);
        $this->app->singleton(\App\Repositories\PacienteRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Registrar observers para auditoría
        User::observe(AuditObserver::class);
        Doctor::observe(AuditObserver::class);
        Paciente::observe(AuditObserver::class);
        Turno::observe(AuditObserver::class);
        LogAuditoria::observe(AuditObserver::class);
    }
}
