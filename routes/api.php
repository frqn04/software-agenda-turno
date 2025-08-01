<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TurnoController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\PacienteController;
use App\Http\Controllers\Api\AgendaController;

// API Routes - Medical Appointment System with Enterprise Architecture
Route::prefix('v1')->group(function () {
    
    // Rutas públicas de autenticación
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    // Rutas protegidas por autenticación
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        
        // Autenticación
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/user', [AuthController::class, 'user']);
            Route::post('/change-password', [AuthController::class, 'changePassword']);
            Route::post('/enable-2fa', [AuthController::class, 'enable2FA']);
            Route::post('/disable-2fa', [AuthController::class, 'disable2FA']);
        });

        // CRUD de Turnos
        Route::apiResource('turnos', TurnoController::class);
        Route::prefix('turnos')->group(function () {
            Route::get('/available-slots', [TurnoController::class, 'availableSlots']);
            Route::patch('/{id}/confirm', [TurnoController::class, 'confirm']);
            Route::patch('/{id}/cancel', [TurnoController::class, 'cancel']);
            Route::patch('/{id}/complete', [TurnoController::class, 'complete']);
        });

        // CRUD de Doctores
        Route::apiResource('doctores', DoctorController::class);
        Route::prefix('doctores')->group(function () {
            Route::get('/active', [DoctorController::class, 'active']);
            Route::get('/especialidad/{especialidadId}', [DoctorController::class, 'byEspecialidad']);
            Route::patch('/{id}/activate', [DoctorController::class, 'activate']);
            Route::patch('/{id}/deactivate', [DoctorController::class, 'deactivate']);
            Route::get('/{id}/stats', [DoctorController::class, 'stats']);
        });

        // CRUD de Pacientes
        Route::apiResource('pacientes', PacienteController::class);
        Route::prefix('pacientes')->group(function () {
            Route::get('/active', [PacienteController::class, 'active']);
            Route::get('/{id}/turnos', [PacienteController::class, 'withTurnos']);
            Route::get('/{id}/historia-clinica', [PacienteController::class, 'withHistoriaClinica']);
            Route::patch('/{id}/activate', [PacienteController::class, 'activate']);
            Route::patch('/{id}/deactivate', [PacienteController::class, 'deactivate']);
            Route::get('/stats', [PacienteController::class, 'stats']);
            Route::post('/validate-availability', [PacienteController::class, 'validateAvailability']);
        });

        // Agenda y reportes
        Route::prefix('agenda')->group(function () {
            Route::get('/doctor/{doctor}', [AgendaController::class, 'porDoctor']);
            Route::get('/fecha/{fecha}', [AgendaController::class, 'porFecha']);
            Route::get('/disponibilidad', [AgendaController::class, 'disponibilidad']);
            Route::get('/pdf', [AgendaController::class, 'generarPDF']);
        });

        // Rutas administrativas (solo para admin)
        // TODO: Implementar middleware de roles
        Route::prefix('admin')->group(function () {
            Route::get('/audit-logs', function(Request $request) {
                return \App\Models\LogAuditoria::with('user')
                    ->when($request->model, fn($q) => $q->where('model_type', $request->model))
                    ->when($request->action, fn($q) => $q->where('action', $request->action))
                    ->latest()
                    ->paginate($request->per_page ?? 15);
            });
            
            Route::get('/system-stats', function() {
                return response()->json([
                    'doctores' => \App\Models\Doctor::count(),
                    'pacientes' => \App\Models\Paciente::count(),
                    'turnos_hoy' => \App\Models\Turno::whereDate('fecha', today())->count(),
                    'turnos_pendientes' => \App\Models\Turno::where('estado', 'pendiente')->count(),
                ]);
            });
        });

        // Horarios disponibles
        Route::get('/doctores/{doctor}/horarios-disponibles/{fecha}', function ($doctorId, $fecha) {
            $cacheService = app(\App\Services\CacheService::class);
            $slots = $cacheService->getDoctorAvailableSlots($doctorId, $fecha);
            
            return response()->json([
                'success' => true,
                'horarios_disponibles' => $slots,
                'fecha' => $fecha,
                'doctor_id' => $doctorId,
            ]);
        });

        // Cache management (solo admin)
        Route::middleware('can:manageSystem,App\Models\User')->group(function () {
            Route::post('/cache/clear', function () {
                $cacheService = app(\App\Services\CacheService::class);
                $cacheService->clearAllCache();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Cache limpiado exitosamente'
                ]);
            });

            Route::get('/cache/stats', function () {
                $cacheService = app(\App\Services\CacheService::class);
                return response()->json($cacheService->getCacheStats());
            });
        });
    });
});
