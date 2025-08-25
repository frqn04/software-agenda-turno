<?php

namespace App\Providers;

use App\Events\AppointmentCreated;
use App\Events\AppointmentCancelled;
use App\Events\AppointmentCompleted;
use App\Events\AppointmentReminder;
use App\Events\PatientRegistered;
use App\Events\PatientUpdated;
use App\Events\DoctorScheduleChanged;
use App\Events\MedicalHistoryUpdated;
use App\Events\EmergencyAlert;
use App\Events\ContractExpiring;
use App\Events\SecurityIncident;
use App\Events\SystemMaintenance;
use App\Listeners\SendAppointmentNotification;
use App\Listeners\SendAppointmentCancellationEmail;
use App\Listeners\UpdatePatientStatistics;
use App\Listeners\LogAppointmentActivity;
use App\Listeners\SendAppointmentReminderSMS;
use App\Listeners\CreatePatientHistory;
use App\Listeners\NotifyDoctorOfNewPatient;
use App\Listeners\UpdateDoctorAvailability;
use App\Listeners\LogMedicalHistoryChange;
use App\Listeners\NotifyEmergencyTeam;
use App\Listeners\SendContractExpirationAlert;
use App\Listeners\LogSecurityIncident;
use App\Listeners\NotifySystemAdministrators;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Provider de eventos para el sistema médico
 * Maneja todos los eventos específicos del dominio médico
 * Incluye notificaciones, alertas y workflows automatizados
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * Mapeo de eventos a listeners del sistema médico
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // Eventos de autenticación
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // Eventos de turnos médicos
        AppointmentCreated::class => [
            SendAppointmentNotification::class,
            LogAppointmentActivity::class,
            UpdateDoctorAvailability::class,
        ],

        AppointmentCancelled::class => [
            SendAppointmentCancellationEmail::class,
            LogAppointmentActivity::class,
            UpdateDoctorAvailability::class,
        ],

        AppointmentCompleted::class => [
            LogAppointmentActivity::class,
            UpdatePatientStatistics::class,
        ],

        AppointmentReminder::class => [
            SendAppointmentReminderSMS::class,
        ],

        // Eventos de pacientes
        PatientRegistered::class => [
            CreatePatientHistory::class,
            NotifyDoctorOfNewPatient::class,
            UpdatePatientStatistics::class,
        ],

        PatientUpdated::class => [
            LogMedicalHistoryChange::class,
        ],

        // Eventos de doctores
        DoctorScheduleChanged::class => [
            UpdateDoctorAvailability::class,
        ],

        // Eventos de historia clínica
        MedicalHistoryUpdated::class => [
            LogMedicalHistoryChange::class,
        ],

        // Eventos de emergencia
        EmergencyAlert::class => [
            NotifyEmergencyTeam::class,
            LogSecurityIncident::class,
        ],

        // Eventos de contratos
        ContractExpiring::class => [
            SendContractExpirationAlert::class,
        ],

        // Eventos de seguridad
        SecurityIncident::class => [
            LogSecurityIncident::class,
            NotifySystemAdministrators::class,
        ],

        // Eventos de sistema
        SystemMaintenance::class => [
            NotifySystemAdministrators::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
