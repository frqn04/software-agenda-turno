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
use App\Policies\UserPolicy;
use App\Policies\PacientePolicy;
use App\Policies\DoctorPolicy;
use App\Policies\TurnoPolicy;
use App\Policies\EvolucionPolicy;
use App\Policies\HistoriaClinicaPolicy;

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
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Definir permisos adicionales
        Gate::define('admin-only', function (User $user) {
            return $user->rol === 'admin';
        });

        Gate::define('can-manage-doctors', function (User $user) {
            return $user->rol === 'admin';
        });

        Gate::define('can-manage-users', function (User $user) {
            return $user->rol === 'admin';
        });

        Gate::define('can-view-all-patients', function (User $user) {
            return in_array($user->rol, ['admin', 'doctor', 'recepcionista']);
        });

        Gate::define('can-manage-appointments', function (User $user) {
            return in_array($user->rol, ['admin', 'doctor', 'recepcionista']);
        });

        Gate::define('can-view-medical-history', function (User $user, Paciente $paciente = null) {
            return in_array($user->rol, ['admin', 'doctor']);
        });

        Gate::define('can-update-medical-history', function (User $user, Paciente $paciente = null) {
            return in_array($user->rol, ['admin', 'doctor']);
        });
    }
}
