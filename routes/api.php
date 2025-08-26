<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TurnoController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\PacienteController;
use App\Http\Controllers\Api\AgendaController;

/*
|--------------------------------------------------------------------------
| API Routes - Sistema Interno Clínica Dental
|--------------------------------------------------------------------------
|
| API REST para gestión de turnos odontológicos.
| Solo para personal interno: admin, doctores, recepcionistas, operadores.
| No hay pacientes en el sistema.
|
*/

Route::prefix('v1')->group(function () {
    
    // Autenticación para personal interno
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']); // Solo admin puede crear usuarios
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    // Rutas protegidas - personal autenticado
    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
        
        // Gestión de sesión
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/user', [AuthController::class, 'user']);
            Route::post('/change-password', [AuthController::class, 'changePassword']);
        });

        // Gestión de Turnos - funcionalidad principal
        Route::apiResource('turnos', TurnoController::class);
        Route::prefix('turnos')->group(function () {
            Route::get('/available-slots', [TurnoController::class, 'availableSlots']);
            Route::patch('/{id}/confirm', [TurnoController::class, 'confirm']);
            Route::patch('/{id}/cancel', [TurnoController::class, 'cancel']);
            Route::patch('/{id}/complete', [TurnoController::class, 'complete']);
            Route::get('/hoy', [TurnoController::class, 'turnosHoy']);
            Route::get('/semana', [TurnoController::class, 'turnosSemana']);
        });

        // Gestión de Doctores
        Route::apiResource('doctores', DoctorController::class);
        Route::prefix('doctores')->group(function () {
            Route::get('/activos', [DoctorController::class, 'activos']);
            Route::get('/especialidad/{especialidadId}', [DoctorController::class, 'porEspecialidad']);
            Route::patch('/{id}/activar', [DoctorController::class, 'activar']);
            Route::patch('/{id}/desactivar', [DoctorController::class, 'desactivar']);
            Route::get('/{id}/estadisticas', [DoctorController::class, 'estadisticas']);
            Route::get('/{id}/agenda/{fecha}', [DoctorController::class, 'agendaDelDia']);
        });

        // Gestión de Pacientes (datos básicos para turnos)
        Route::apiResource('pacientes', PacienteController::class);
        Route::prefix('pacientes')->group(function () {
            Route::get('/activos', [PacienteController::class, 'activos']);
            Route::get('/{id}/turnos', [PacienteController::class, 'historialTurnos']);
            Route::get('/{id}/historia-clinica', [PacienteController::class, 'historiaClinica']);
            Route::post('/{id}/historia-clinica', [PacienteController::class, 'crearHistoriaClinica']);
            Route::post('/buscar', [PacienteController::class, 'buscar']);
        });

        // Agenda y calendario interno
        Route::prefix('agenda')->group(function () {
            Route::get('/doctor/{doctorId}', [AgendaController::class, 'porDoctor']);
            Route::get('/fecha/{fecha}', [AgendaController::class, 'porFecha']);
            Route::get('/disponibilidad', [AgendaController::class, 'disponibilidad']);
            Route::get('/resumen-dia', [AgendaController::class, 'resumenDia']);
            Route::get('/pdf', [AgendaController::class, 'generarPDF']);
        });

        // Estadísticas y reportes para el personal
        Route::prefix('estadisticas')->group(function () {
            Route::get('/dashboard', function() {
                return response()->json([
                    'doctores_activos' => \App\Models\Doctor::where('active', true)->count(),
                    'pacientes_total' => \App\Models\Paciente::count(),
                    'turnos_hoy' => \App\Models\Turno::whereDate('fecha', today())->count(),
                    'turnos_pendientes' => \App\Models\Turno::where('estado', 'pendiente')->count(),
                    'turnos_completados_mes' => \App\Models\Turno::where('estado', 'completado')
                        ->whereMonth('fecha', now()->month)->count(),
                ]);
            });
            
            Route::get('/doctor/{doctorId}', function($doctorId) {
                return response()->json([
                    'turnos_mes' => \App\Models\Turno::where('doctor_id', $doctorId)
                        ->whereMonth('fecha', now()->month)->count(),
                    'pacientes_atendidos' => \App\Models\Turno::where('doctor_id', $doctorId)
                        ->where('estado', 'completado')
                        ->distinct('paciente_id')->count(),
                ]);
            });
        });

        // Administración del sistema (solo admin)
        Route::prefix('admin')->middleware('can:administrar,App\Models\User')->group(function () {
            Route::get('/auditoria', function(Request $request) {
                return \App\Models\LogAuditoria::with('user')
                    ->when($request->modelo, fn($q) => $q->where('model_type', $request->modelo))
                    ->when($request->accion, fn($q) => $q->where('action', $request->accion))
                    ->latest()
                    ->paginate($request->per_page ?? 20);
            });
            
            // Cache management para administradores
            Route::post('/cache/limpiar', function () {
                $cacheService = app(\App\Services\CacheService::class);
                $cacheService->clearAllCache();
                
                return response()->json([
                    'success' => true,
                    'mensaje' => 'Cache del sistema limpiado correctamente'
                ]);
            });

            Route::get('/cache/estado', function () {
                $cacheService = app(\App\Services\CacheService::class);
                return response()->json($cacheService->getCacheStats());
            });
        });
        
        // Especialidades disponibles en la clínica
        Route::get('/especialidades', function() {
            return response()->json([
                'success' => true,
                'especialidades' => \App\Models\Especialidad::orderBy('nombre')->get()
            ]);
        });
    });
});
