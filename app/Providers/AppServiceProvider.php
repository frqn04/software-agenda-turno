<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\User;
use App\Models\Paciente;
use App\Models\Doctor;
use App\Models\Turno;
use App\Models\DoctorContract;
use App\Observers\AuditObserver;
use App\Observers\PacienteObserver;
use App\Observers\DoctorObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registrar observers para auditoría
        User::observe(AuditObserver::class);
        Paciente::observe(PacienteObserver::class);
        Doctor::observe(DoctorObserver::class);
        Turno::observe(AuditObserver::class);
        DoctorContract::observe(AuditObserver::class);
    }
}
