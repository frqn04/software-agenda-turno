<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\Paciente;
use App\Models\Doctor;
use App\Models\Turno;
use App\Models\Evolucion;
use App\Models\HistoriaClinica;
use App\Models\Especialidad;
use App\Models\DoctorContract;
use App\Models\LogAuditoria;
use App\Policies\UserPolicy;
use App\Policies\PacientePolicy;
use App\Policies\DoctorPolicy;
use App\Policies\TurnoPolicy;
use App\Policies\EvolucionPolicy;
use App\Policies\HistoriaClinicaPolicy;
use App\Policies\EspecialidadPolicy;
use App\Policies\DoctorContractPolicy;
use App\Policies\LogAuditoriaPolicy;

/**
 * Service Provider de autenticación y autorización médica
 * Maneja el mapeo de políticas y gates personalizados para el sistema médico
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Paciente::class => PacientePolicy::class,
        Doctor::class => DoctorPolicy::class,
        Turno::class => TurnoPolicy::class,
        Evolucion::class => EvolucionPolicy::class,
        HistoriaClinica::class => HistoriaClinicaPolicy::class,
        Especialidad::class => EspecialidadPolicy::class,
        DoctorContract::class => DoctorContractPolicy::class,
        LogAuditoria::class => LogAuditoriaPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Registrar gates administrativos
        $this->registerAdministrativeGates();
        
        // Registrar gates médicos
        $this->registerMedicalGates();
        
        // Registrar gates de seguridad
        $this->registerSecurityGates();
        
        // Registrar gates de auditoría
        $this->registerAuditGates();
        
        // Registrar gates de reportes
        $this->registerReportGates();
    }

    /**
     * Registrar gates administrativos
     */
    private function registerAdministrativeGates(): void
    {
        // Gates de administración general
        Gate::define('admin-only', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });

        Gate::define('super-admin-only', function (User $user) {
            return $this->isActiveUser($user) && 
                   $user->role === 'super_admin';
        });

        // Gates de gestión de usuarios
        Gate::define('can-manage-users', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });

        Gate::define('can-create-admin-users', function (User $user) {
            return $this->isActiveUser($user) && 
                   $user->role === 'super_admin';
        });

        // Gates de gestión de doctores
        Gate::define('can-manage-doctors', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });

        Gate::define('can-manage-doctor-contracts', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });

        // Gates de gestión de especialidades
        Gate::define('can-manage-specialties', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });

        // Gates de configuración del sistema
        Gate::define('can-manage-system-settings', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });
    }

    /**
     * Registrar gates médicos específicos
     */
    private function registerMedicalGates(): void
    {
        // Gates de gestión de pacientes
        Gate::define('can-view-all-patients', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin', 'doctor', 'recepcionista']);
        });

        Gate::define('can-create-patients', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin', 'recepcionista']);
        });

        Gate::define('can-access-patient-sensitive-data', function (User $user, Paciente $paciente = null) {
            if (!$this->isActiveUser($user)) {
                return false;
            }

            // Administradores y doctores pueden acceder a datos sensibles
            if (in_array($user->role, ['administrador', 'super_admin', 'doctor'])) {
                return true;
            }

            return false;
        });

        // Gates de gestión de turnos
        Gate::define('can-manage-appointments', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin', 'doctor', 'recepcionista']);
        });

        Gate::define('can-schedule-emergency-appointments', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin', 'doctor']);
        });

        Gate::define('can-override-appointment-restrictions', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });

        // Gates de historia clínica
        Gate::define('can-view-medical-history', function (User $user, Paciente $paciente = null) {
            if (!$this->isActiveUser($user)) {
                return false;
            }

            // Administradores y doctores pueden ver historias clínicas
            if (in_array($user->role, ['administrador', 'super_admin', 'doctor'])) {
                return true;
            }

            return false;
        });

        Gate::define('can-update-medical-history', function (User $user, Paciente $paciente = null) {
            if (!$this->isActiveUser($user)) {
                return false;
            }

            // Solo doctores y administradores pueden actualizar
            return in_array($user->role, ['administrador', 'super_admin', 'doctor']);
        });

        Gate::define('can-delete-medical-records', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });

        // Gates de evoluciones médicas
        Gate::define('can-create-medical-evolutions', function (User $user) {
            return $this->isActiveUser($user) && 
                   $user->role === 'doctor';
        });

        Gate::define('can-sign-medical-documents', function (User $user) {
            return $this->isActiveUser($user) && 
                   $user->role === 'doctor';
        });

        Gate::define('can-view-all-medical-evolutions', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });
    }

    /**
     * Registrar gates de seguridad
     */
    private function registerSecurityGates(): void
    {
        // Gates de acceso a logs
        Gate::define('can-view-system-logs', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });

        Gate::define('can-view-security-logs', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });

        Gate::define('can-manage-user-sessions', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });

        // Gates de backup y recuperación
        Gate::define('can-perform-system-backup', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });

        Gate::define('can-restore-system-backup', function (User $user) {
            return $this->isActiveUser($user) && 
                   $user->role === 'super_admin';
        });

        // Gates de mantenimiento
        Gate::define('can-access-maintenance-mode', function (User $user) {
            return $this->isActiveUser($user) && 
                   $user->role === 'super_admin';
        });

        Gate::define('can-clear-system-cache', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });
    }

    /**
     * Registrar gates de auditoría
     */
    private function registerAuditGates(): void
    {
        // Gates de auditoría general
        Gate::define('can-view-audit-logs', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });

        Gate::define('can-export-audit-logs', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });

        Gate::define('can-configure-audit-settings', function (User $user) {
            return $this->isActiveUser($user) && 
                   $user->role === 'super_admin';
        });

        // Gates de auditoría médica específica
        Gate::define('can-view-medical-access-logs', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });

        Gate::define('can-track-patient-data-access', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });

        // Gates de compliance
        Gate::define('can-generate-compliance-reports', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });
    }

    /**
     * Registrar gates de reportes
     */
    private function registerReportGates(): void
    {
        // Gates de reportes generales
        Gate::define('can-access-reports', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin', 'doctor']);
        });

        Gate::define('can-generate-financial-reports', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });

        // Gates de reportes médicos
        Gate::define('can-view-medical-statistics', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin', 'doctor']);
        });

        Gate::define('can-generate-doctor-performance-reports', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });

        Gate::define('can-view-appointment-statistics', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin', 'doctor', 'recepcionista']);
        });

        // Gates de exportación
        Gate::define('can-export-patient-data', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });

        Gate::define('can-export-medical-reports', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin', 'doctor']);
        });

        // Gates de análisis avanzado
        Gate::define('can-access-advanced-analytics', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });

        Gate::define('can-create-custom-reports', function (User $user) {
            return $this->isActiveUser($user) && 
                   in_array($user->role, ['administrador', 'super_admin']);
        });
    }

    /**
     * Verificar si el usuario está activo
     */
    private function isActiveUser(User $user): bool
    {
        return $user && $user->is_active;
    }
}
