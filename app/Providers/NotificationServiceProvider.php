<?php

namespace App\Providers;

use App\Services\Notifications\EmailNotificationService;
use App\Services\Notifications\SmsNotificationService;
use App\Services\Notifications\WhatsAppNotificationService;
use App\Services\Notifications\PushNotificationService;
use App\Services\Notifications\TelegramNotificationService;
use App\Services\Notifications\NotificationTemplateService;
use App\Services\Notifications\NotificationPreferenceService;
use App\Services\Notifications\NotificationSchedulerService;
use App\Services\Notifications\NotificationTrackingService;
use App\Services\Notifications\EmergencyNotificationService;
use App\Contracts\NotificationServiceInterface;
use App\Contracts\SmsServiceInterface;
use App\Contracts\PushNotificationInterface;
use App\Contracts\EmailServiceInterface;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;

/**
 * Provider de notificaciones para el sistema médico
 * Centraliza todos los servicios de notificación médica
 * Incluye múltiples canales y configuraciones personalizadas
 */
class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Servicio principal de notificaciones
        $this->app->singleton(NotificationServiceInterface::class, function ($app) {
            return new EmailNotificationService(
                config('mail.default'),
                config('notifications.medical.templates')
            );
        });

        // Servicio de SMS médicos
        $this->app->singleton(SmsServiceInterface::class, function ($app) {
            return new SmsNotificationService(
                config('services.sms.default'),
                config('notifications.medical.sms')
            );
        });

        // Servicio de notificaciones push
        $this->app->singleton(PushNotificationInterface::class, function ($app) {
            return new PushNotificationService(
                config('services.push.default'),
                config('notifications.medical.push')
            );
        });

        // Servicio de email médico
        $this->app->singleton(EmailServiceInterface::class, function ($app) {
            return new EmailNotificationService(
                config('mail.medical'),
                config('notifications.medical.email')
            );
        });

        // Servicios especializados
        $this->app->singleton('notification.whatsapp', function ($app) {
            return new WhatsAppNotificationService(
                config('services.whatsapp.token'),
                config('services.whatsapp.phone_id')
            );
        });

        $this->app->singleton('notification.telegram', function ($app) {
            return new TelegramNotificationService(
                config('services.telegram.bot_token'),
                config('services.telegram.chat_id')
            );
        });

        // Servicios de gestión de notificaciones
        $this->app->singleton('notification.templates', function ($app) {
            return new NotificationTemplateService(
                $app->make('cache.store'),
                config('notifications.medical.templates_path')
            );
        });

        $this->app->singleton('notification.preferences', function ($app) {
            return new NotificationPreferenceService(
                $app->make('db'),
                config('notifications.medical.preferences_table')
            );
        });

        $this->app->singleton('notification.scheduler', function ($app) {
            return new NotificationSchedulerService(
                $app->make('queue'),
                config('notifications.medical.scheduler')
            );
        });

        $this->app->singleton('notification.tracking', function ($app) {
            return new NotificationTrackingService(
                $app->make('db'),
                config('notifications.medical.tracking_table')
            );
        });

        // Servicio de notificaciones de emergencia
        $this->app->singleton('notification.emergency', function ($app) {
            return new EmergencyNotificationService([
                $app->make(SmsServiceInterface::class),
                $app->make(PushNotificationInterface::class),
                $app->make('notification.whatsapp'),
                $app->make('notification.telegram'),
            ]);
        });

        // Aliases para facilitar el acceso
        $this->app->alias(NotificationServiceInterface::class, 'notification.email');
        $this->app->alias(SmsServiceInterface::class, 'notification.sms');
        $this->app->alias(PushNotificationInterface::class, 'notification.push');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Configuración de canales de notificación médica
        $this->registerNotificationChannels();
        
        // Configuración de plantillas de notificación médica
        $this->registerMedicalTemplates();
        
        // Configuración de preferencias por defecto
        $this->registerDefaultPreferences();
        
        // Configuración de tracking de notificaciones
        $this->registerNotificationTracking();
    }

    /**
     * Registra los canales de notificación médica
     */
    private function registerNotificationChannels(): void
    {
        // Canal de recordatorios de turnos
        Config::set('notifications.channels.appointment_reminder', [
            'enabled' => true,
            'channels' => ['email', 'sms', 'push'],
            'schedule' => [
                '24_hours_before' => true,
                '2_hours_before' => true,
                '30_minutes_before' => false,
            ]
        ]);

        // Canal de emergencias médicas
        Config::set('notifications.channels.medical_emergency', [
            'enabled' => true,
            'channels' => ['sms', 'push', 'whatsapp', 'telegram'],
            'priority' => 'high',
            'immediate' => true,
        ]);

        // Canal de resultados médicos
        Config::set('notifications.channels.medical_results', [
            'enabled' => true,
            'channels' => ['email', 'sms'],
            'secure' => true,
            'encryption' => true,
        ]);

        // Canal de notificaciones administrativas
        Config::set('notifications.channels.administrative', [
            'enabled' => true,
            'channels' => ['email', 'push'],
            'business_hours_only' => true,
        ]);
    }

    /**
     * Registra las plantillas de notificación médica
     */
    private function registerMedicalTemplates(): void
    {
        Config::set('notifications.medical.templates', [
            'appointment_created' => [
                'subject' => 'Turno Confirmado - {{date}} con Dr. {{doctor}}',
                'template' => 'notifications.medical.appointment_created',
                'variables' => ['patient_name', 'doctor_name', 'date', 'time', 'specialty'],
            ],
            'appointment_reminder' => [
                'subject' => 'Recordatorio: Turno mañana con Dr. {{doctor}}',
                'template' => 'notifications.medical.appointment_reminder',
                'variables' => ['patient_name', 'doctor_name', 'date', 'time', 'location'],
            ],
            'appointment_cancelled' => [
                'subject' => 'Turno Cancelado - {{date}}',
                'template' => 'notifications.medical.appointment_cancelled',
                'variables' => ['patient_name', 'doctor_name', 'date', 'reason'],
            ],
            'medical_results_ready' => [
                'subject' => 'Resultados Médicos Disponibles',
                'template' => 'notifications.medical.results_ready',
                'variables' => ['patient_name', 'test_type', 'date'],
                'secure' => true,
            ],
            'emergency_alert' => [
                'subject' => 'ALERTA MÉDICA - Atención Inmediata Requerida',
                'template' => 'notifications.medical.emergency_alert',
                'variables' => ['patient_name', 'emergency_type', 'location', 'time'],
                'priority' => 'urgent',
            ],
            'contract_expiring' => [
                'subject' => 'Contrato Médico por Vencer - {{days}} días restantes',
                'template' => 'notifications.medical.contract_expiring',
                'variables' => ['doctor_name', 'contract_type', 'expiry_date', 'days_remaining'],
            ],
        ]);
    }

    /**
     * Registra las preferencias por defecto
     */
    private function registerDefaultPreferences(): void
    {
        Config::set('notifications.medical.default_preferences', [
            'appointment_reminders' => [
                'enabled' => true,
                'channels' => ['email', 'sms'],
                'timing' => ['24_hours', '2_hours'],
            ],
            'medical_results' => [
                'enabled' => true,
                'channels' => ['email'],
                'secure_delivery' => true,
            ],
            'emergency_alerts' => [
                'enabled' => true,
                'channels' => ['sms', 'push', 'whatsapp'],
                'bypass_quiet_hours' => true,
            ],
            'administrative' => [
                'enabled' => true,
                'channels' => ['email'],
                'business_hours_only' => true,
            ],
        ]);
    }

    /**
     * Registra el sistema de tracking de notificaciones
     */
    private function registerNotificationTracking(): void
    {
        Config::set('notifications.medical.tracking', [
            'enabled' => true,
            'track_opens' => true,
            'track_clicks' => true,
            'track_delivery' => true,
            'retention_days' => 365,
            'analytics' => [
                'enabled' => true,
                'dashboard' => true,
                'reports' => true,
            ],
        ]);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            NotificationServiceInterface::class,
            SmsServiceInterface::class,
            PushNotificationInterface::class,
            EmailServiceInterface::class,
            'notification.email',
            'notification.sms',
            'notification.push',
            'notification.whatsapp',
            'notification.telegram',
            'notification.templates',
            'notification.preferences',
            'notification.scheduler',
            'notification.tracking',
            'notification.emergency',
        ];
    }
}
