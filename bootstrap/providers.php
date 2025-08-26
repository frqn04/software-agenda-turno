<?php

/**
 * Providers del sistema médico de gestión de turnos
 * Configuración optimizada para entorno empresarial con cumplimiento HIPAA
 */

return [
    // Providers base de Laravel
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    
    // Providers específicos del sistema médico
    App\Providers\EventServiceProvider::class,
    App\Providers\NotificationServiceProvider::class,
    App\Providers\IntegrationServiceProvider::class,
    App\Providers\SecurityServiceProvider::class,
    App\Providers\HealthMonitoringServiceProvider::class,
    App\Providers\ReportingServiceProvider::class,
    
    // Providers de terceros (si se necesitan)
    // Laravel\Passport\PassportServiceProvider::class,
    // Spatie\Permission\PermissionServiceProvider::class,
];
