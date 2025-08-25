<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use App\Models\User;
use App\Models\Paciente;
use App\Models\Doctor;
use App\Models\Turno;
use App\Models\DoctorContract;
use App\Models\Evolucion;
use App\Models\HistoriaClinica;
use App\Models\Especialidad;
use App\Models\LogAuditoria;
use App\Observers\AuditObserver;
use App\Observers\PacienteObserver;
use App\Observers\DoctorObserver;
use App\Observers\TurnoObserver;
use App\Observers\DoctorContractObserver;
use App\Observers\EvolucionObserver;
use App\Observers\HistoriaClinicaObserver;
use App\Observers\EspecialidadObserver;
use App\Observers\UserObserver;
use Carbon\Carbon;

/**
 * Service Provider principal de la aplicación médica
 * Configura servicios globales, observers, validaciones y configuraciones empresariales
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Configurar timezone para la aplicación médica
        config(['app.timezone' => 'America/Argentina/Buenos_Aires']);
        
        // Registrar macros personalizados
        $this->registerMacros();
        
        // Registrar providers médicos especializados
        $this->registerMedicalProviders();
        
        // Configurar servicios en diferentes entornos
        if ($this->app->environment('local')) {
            $this->registerDevelopmentServices();
        }
        
        if ($this->app->environment('production')) {
            $this->registerProductionServices();
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configuraciones de base de datos
        $this->configureDatabaseSettings();
        
        // Configurar Carbon para fechas médicas
        $this->configureCarbonSettings();
        
        // Registrar todos los observers del sistema médico
        $this->registerObservers();
        
        // Configurar validaciones personalizadas para el sector médico
        $this->registerCustomValidations();
        
        // Configurar vistas globales
        $this->configureGlobalViews();
        
        // Configurar paginación
        $this->configurePagination();
        
        // Configurar URL para HTTPS en producción
        $this->configureUrlGeneration();
        
        // Configurar Model para prevenir lazy loading en desarrollo
        $this->configureModelBehavior();
    }

    /**
     * Configurar settings de base de datos
     */
    private function configureDatabaseSettings(): void
    {
        // Configurar longitud de string por defecto para MySQL
        Schema::defaultStringLength(191);
        
        // Configurar charset para soporte completo de caracteres
        if (config('database.default') === 'mysql') {
            config(['database.connections.mysql.charset' => 'utf8mb4']);
            config(['database.connections.mysql.collation' => 'utf8mb4_unicode_ci']);
        }
    }

    /**
     * Configurar Carbon para fechas médicas
     */
    private function configureCarbonSettings(): void
    {
        // Configurar locale español para fechas
        Carbon::setLocale('es');
        
        // Configurar timezone específico para el sistema médico
        Carbon::setTimezone('America/Argentina/Buenos_Aires');
        
        // Configurar formatos personalizados para el sector médico
        Carbon::macro('toMedicalDate', function () {
            return $this->format('d/m/Y');
        });
        
        Carbon::macro('toMedicalDateTime', function () {
            return $this->format('d/m/Y H:i');
        });
        
        Carbon::macro('toMedicalTime', function () {
            return $this->format('H:i');
        });
    }

    /**
     * Registrar todos los observers del sistema médico
     */
    private function registerObservers(): void
    {
        // Observers principales con auditoría completa
        User::observe(UserObserver::class);
        User::observe(AuditObserver::class);
        
        Paciente::observe(PacienteObserver::class);
        Paciente::observe(AuditObserver::class);
        
        Doctor::observe(DoctorObserver::class);
        Doctor::observe(AuditObserver::class);
        
        Turno::observe(TurnoObserver::class);
        Turno::observe(AuditObserver::class);
        
        DoctorContract::observe(DoctorContractObserver::class);
        DoctorContract::observe(AuditObserver::class);
        
        Evolucion::observe(EvolucionObserver::class);
        Evolucion::observe(AuditObserver::class);
        
        HistoriaClinica::observe(HistoriaClinicaObserver::class);
        HistoriaClinica::observe(AuditObserver::class);
        
        Especialidad::observe(EspecialidadObserver::class);
        Especialidad::observe(AuditObserver::class);
        
        // LogAuditoria solo con observer específico (sin auditoría recursiva)
        LogAuditoria::observe(AuditObserver::class);
    }

    /**
     * Registrar validaciones personalizadas para el sector médico
     */
    private function registerCustomValidations(): void
    {
        // Validación de DNI argentino
        Validator::extend('dni_argentino', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^[0-9]{7,8}$/', $value);
        });
        
        // Validación de CUIL/CUIT
        Validator::extend('cuil_cuit', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^[0-9]{2}-[0-9]{8}-[0-9]{1}$/', $value);
        });
        
        // Validación de matrícula médica
        Validator::extend('matricula_medica', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^[A-Z]{1,3}[0-9]{4,6}$/', $value);
        });
        
        // Validación de número de obra social
        Validator::extend('numero_obra_social', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^[0-9]{6,12}$/', $value);
        });
        
        // Validación de horario médico (formato HH:MM)
        Validator::extend('horario_medico', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $value);
        });
        
        // Validación de fecha de turno médico (no puede ser en el pasado)
        Validator::extend('fecha_turno_valida', function ($attribute, $value, $parameters, $validator) {
            $fecha = Carbon::parse($value);
            return $fecha->isToday() || $fecha->isFuture();
        });
        
        // Validación de duración de turno médico
        Validator::extend('duracion_turno_valida', function ($attribute, $value, $parameters, $validator) {
            $duracionesValidas = [15, 30, 45, 60, 90, 120]; // minutos
            return in_array((int) $value, $duracionesValidas);
        });
        
        // Registrar mensajes de validación personalizados
        Validator::replacer('dni_argentino', function ($message, $attribute, $rule, $parameters) {
            return 'El DNI debe tener entre 7 y 8 dígitos numéricos.';
        });
        
        Validator::replacer('cuil_cuit', function ($message, $attribute, $rule, $parameters) {
            return 'El CUIL/CUIT debe tener el formato: XX-XXXXXXXX-X.';
        });
        
        Validator::replacer('matricula_medica', function ($message, $attribute, $rule, $parameters) {
            return 'La matrícula médica debe tener el formato: ABC1234.';
        });
        
        Validator::replacer('numero_obra_social', function ($message, $attribute, $rule, $parameters) {
            return 'El número de obra social debe tener entre 6 y 12 dígitos.';
        });
        
        Validator::replacer('horario_medico', function ($message, $attribute, $rule, $parameters) {
            return 'El horario debe tener el formato HH:MM (24 horas).';
        });
        
        Validator::replacer('fecha_turno_valida', function ($message, $attribute, $rule, $parameters) {
            return 'La fecha del turno no puede ser en el pasado.';
        });
        
        Validator::replacer('duracion_turno_valida', function ($message, $attribute, $rule, $parameters) {
            return 'La duración del turno debe ser: 15, 30, 45, 60, 90 o 120 minutos.';
        });
    }

    /**
     * Configurar vistas globales con datos del sistema médico
     */
    private function configureGlobalViews(): void
    {
        // Compartir configuración global de la aplicación médica
        View::composer('*', function ($view) {
            $view->with([
                'appName' => config('app.name', 'Sistema Médico'),
                'appVersion' => '2.0.0',
                'currentTimezone' => config('app.timezone'),
                'medicalFormats' => [
                    'date' => 'd/m/Y',
                    'datetime' => 'd/m/Y H:i',
                    'time' => 'H:i'
                ]
            ]);
        });
        
        // Compartir datos específicos para vistas de turnos
        View::composer(['turnos.*', 'appointments.*'], function ($view) {
            $view->with([
                'duracionesTurno' => [15, 30, 45, 60, 90, 120],
                'horariosTrabajo' => [
                    'inicio' => '08:00',
                    'fin' => '18:00'
                ]
            ]);
        });
    }

    /**
     * Configurar paginación para el sistema médico
     */
    private function configurePagination(): void
    {
        // Usar Bootstrap 5 para paginación
        Paginator::defaultView('pagination::bootstrap-4');
        Paginator::defaultSimpleView('pagination::simple-bootstrap-4');
        
        // Configurar paginación por defecto para diferentes secciones
        config([
            'pagination.per_page.default' => 25,
            'pagination.per_page.turnos' => 50,
            'pagination.per_page.pacientes' => 30,
            'pagination.per_page.doctores' => 20,
            'pagination.per_page.auditorias' => 100
        ]);
    }

    /**
     * Configurar generación de URLs
     */
    private function configureUrlGeneration(): void
    {
        // Forzar HTTPS en producción
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Configurar comportamiento de modelos
     */
    private function configureModelBehavior(): void
    {
        // Prevenir lazy loading en desarrollo para detectar N+1
        if ($this->app->environment('local', 'testing')) {
            Model::preventLazyLoading(true);
        }
        
        // Configurar comportamiento silencioso en producción
        if ($this->app->environment('production')) {
            Model::preventLazyLoading(false);
            Model::preventAccessingMissingAttributes(false);
        }
    }

    /**
     * Registrar providers médicos especializados
     */
    private function registerMedicalProviders(): void
    {
        // Providers médicos especializados para sistema empresarial
        $medicalProviders = [
            EventServiceProvider::class,
            NotificationServiceProvider::class,
            IntegrationServiceProvider::class,
            SecurityServiceProvider::class,
            HealthMonitoringServiceProvider::class,
            ReportingServiceProvider::class,
        ];

        foreach ($medicalProviders as $provider) {
            if (class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }

    /**
     * Registrar macros personalizados para el sistema médico
     */
    private function registerMacros(): void
    {
        // Macro para colección de turnos médicos
        \Illuminate\Support\Collection::macro('filterByMedicalStatus', function ($status) {
            return $this->filter(function ($turno) use ($status) {
                return $turno->estado === $status;
            });
        });
        
        // Macro para formatear números de teléfono
        \Illuminate\Support\Str::macro('formatPhone', function ($phone) {
            // Formato argentino: +54 11 1234-5678
            $cleaned = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($cleaned) === 10) {
                return '+54 ' . substr($cleaned, 0, 2) . ' ' . substr($cleaned, 2, 4) . '-' . substr($cleaned, 6);
            }
            return $phone;
        });
    }

    /**
     * Registrar servicios de desarrollo
     */
    private function registerDevelopmentServices(): void
    {
        // Servicios específicos para desarrollo local
        if (class_exists(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class)) {
            $this->app->register(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class);
        }
        
        // Debug bar en desarrollo
        if (class_exists(\Barryvdh\Debugbar\ServiceProvider::class)) {
            $this->app->register(\Barryvdh\Debugbar\ServiceProvider::class);
        }
    }

    /**
     * Registrar servicios de producción
     */
    private function registerProductionServices(): void
    {
        // Configuraciones específicas para producción
        
        // Optimizar carga de configuración
        config(['app.debug' => false]);
        
        // Configurar logs para producción
        config([
            'logging.channels.daily.days' => 30,
            'logging.channels.daily.level' => 'warning'
        ]);
        
        // Configurar cache de configuración
        if (file_exists(base_path('bootstrap/cache/config.php'))) {
            config(['config.cached' => true]);
        }
    }
}
