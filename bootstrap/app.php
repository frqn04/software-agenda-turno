<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            // Rutas específicas del sistema médico
            Route::middleware(['api', 'auth:sanctum'])
                 ->prefix('api/v1/secure')
                 ->group(__DIR__.'/../routes/api-secure.php');
        }
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Configuración API con Sanctum para autenticación médica
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
        
        // Excluir rutas API del middleware CSRF
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'webhooks/*',
            'integrations/*',
        ]);
        
        // Middlewares de seguridad globales para datos médicos
        $middleware->web(append: [
            \App\Http\Middleware\SecureHeaders::class,
            \App\Http\Middleware\AuditMiddleware::class,
        ]);
        
        $middleware->api(append: [
            \App\Http\Middleware\SecureHeaders::class,
            \App\Http\Middleware\SecurityLogging::class,
            \App\Http\Middleware\ApiRateLimiting::class,
            \App\Http\Middleware\MedicalDataAccess::class,
        ]);
        
        // Aliases de middleware específicos para sistema odontológico interno
        $middleware->alias([
            // Roles del personal interno
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'doctor' => \App\Http\Middleware\DoctorMiddleware::class,
            'receptionist' => \App\Http\Middleware\ReceptionistMiddleware::class,
            'operator' => \App\Http\Middleware\OperatorMiddleware::class,
            
            // Seguridad avanzada
            'throttle.ban' => \App\Http\Middleware\ThrottleWithBanMiddleware::class,
            'secure.headers' => \App\Http\Middleware\SecureHeaders::class,
            'security.logging' => \App\Http\Middleware\SecurityLogging::class,
            'audit.trail' => \App\Http\Middleware\AuditMiddleware::class,
            
            // Específicos del dominio médico
            'medical.access' => \App\Http\Middleware\MedicalDataAccess::class,
            'hipaa.compliance' => \App\Http\Middleware\HipaaComplianceMiddleware::class,
            'appointment.validation' => \App\Http\Middleware\AppointmentValidationMiddleware::class,
            'doctor.schedule' => \App\Http\Middleware\DoctorScheduleMiddleware::class,
            
            // API y integraciones
            'api.version' => \App\Http\Middleware\ApiVersionMiddleware::class,
            'api.rate.limit' => \App\Http\Middleware\ApiRateLimiting::class,
            'integration.auth' => \App\Http\Middleware\IntegrationAuthMiddleware::class,
            
            // Monitoreo y performance
            'performance.monitor' => \App\Http\Middleware\PerformanceMonitorMiddleware::class,
            'request.id' => \App\Http\Middleware\RequestIdMiddleware::class,
        ]);
        
        // Configuración de rate limiting específica para sistema médico
        $middleware->throttleApi('api');
        
        // Middleware condicionalmente aplicado
        $middleware->when(
            fn () => app()->environment('production'),
            [\App\Http\Middleware\ForceHttpsMiddleware::class]
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Manejo específico de excepciones para sistemas médicos
        
        // Excepciones de seguridad médica
        $exceptions->render(function (\App\Exceptions\MedicalDataAccessException $e) {
            \App\Services\AuditService::logSecurityEvent(
                'unauthorized_medical_data_access',
                'critical',
                ['exception' => $e->getMessage(), 'user_id' => auth()->id()]
            );
            
            return response()->json([
                'error' => 'Acceso no autorizado a datos médicos',
                'message' => 'Este incidente ha sido registrado',
                'code' => 'MEDICAL_ACCESS_DENIED'
            ], 403);
        });
        
        // Excepciones de validación de citas
        $exceptions->render(function (\App\Exceptions\AppointmentValidationException $e) {
            return response()->json([
                'error' => 'Error de validación de cita',
                'message' => $e->getMessage(),
                'code' => 'APPOINTMENT_VALIDATION_ERROR'
            ], 422);
        });
        
        // Excepciones de disponibilidad de doctor
        $exceptions->render(function (\App\Exceptions\DoctorAvailabilityException $e) {
            return response()->json([
                'error' => 'Doctor no disponible',
                'message' => $e->getMessage(),
                'available_slots' => $e->getAvailableSlots(),
                'code' => 'DOCTOR_NOT_AVAILABLE'
            ], 409);
        });
        
        // Excepciones de integración externa
        $exceptions->render(function (\App\Exceptions\ExternalIntegrationException $e) {
            \Illuminate\Support\Facades\Log::error('External integration failed', [
                'service' => $e->getService(),
                'error' => $e->getMessage(),
                'context' => $e->getContext()
            ]);
            
            return response()->json([
                'error' => 'Error en servicio externo',
                'message' => 'El servicio no está disponible temporalmente',
                'code' => 'EXTERNAL_SERVICE_ERROR'
            ], 503);
        });
        
        // Log de todas las excepciones para auditoría
        $exceptions->reportable(function (Throwable $e) {
            if (auth()->check()) {
                \App\Services\AuditService::logActivity(
                    'exception_occurred',
                    'system_errors',
                    null,
                    auth()->id(),
                    null,
                    [
                        'exception_class' => get_class($e),
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString()
                    ]
                );
            }
        });
    })->create();
