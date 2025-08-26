<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\PdfController;

/*
|--------------------------------------------------------------------------
| API Segura - Rutas con Mayor Protección
|--------------------------------------------------------------------------
|
| API con throttling estricto y validaciones adicionales para operaciones
| críticas del sistema de clínica dental.
|
*/

// Login con protección anti-ataques
Route::middleware(['throttle.ban:5,1'])->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Rutas básicas con throttling moderado
Route::middleware(['throttle:30,1'])->group(function () {
    Route::get('/estado', function () {
        return response()->json([
            'mensaje' => 'Sistema de clínica dental operativo',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0',
            'modo' => 'interno'
        ]);
    });
});

// Rutas protegidas con alta seguridad
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    
    // Gestión de sesión segura
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/perfil', [AuthController::class, 'perfil']);
    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
    
    // Solo administradores pueden crear usuarios
    Route::middleware(['role:admin'])->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/revoke-all-tokens', [AuthController::class, 'revokeAllTokens']);
        Route::get('/usuarios-activos', [AuthController::class, 'usuariosActivos']);
    });

    // Gestión de pacientes con permisos
    Route::apiResource('pacientes', PatientController::class);
    Route::get('/pacientes/{id}/historia-clinica', [PatientController::class, 'historiaClinica'])
        ->middleware('can:verHistoriaClinica,id');
    Route::post('/pacientes/{id}/historia-clinica', [PatientController::class, 'crearHistoriaClinica'])
        ->middleware('can:crearHistoriaClinica,id');
    Route::post('/pacientes/{id}/evoluciones', [PatientController::class, 'agregarEvolucion'])
        ->middleware('can:actualizarHistoriaClinica,id');

    // Gestión de doctores
    Route::apiResource('doctores', DoctorController::class);
    Route::get('/doctores/{id}/agenda', [DoctorController::class, 'agenda'])
        ->middleware('can:verAgenda,id');
    Route::get('/doctores/{id}/slots-disponibles', [DoctorController::class, 'slotsDisponibles'])
        ->middleware('can:verAgenda,id');
    Route::get('/doctores/{id}/estadisticas', [DoctorController::class, 'estadisticas'])
        ->middleware('can:verEstadisticas,id');

    // Gestión de turnos con nombres específicos
    Route::apiResource('turnos', AppointmentController::class, [
        'names' => [
            'index' => 'turnos.index',
            'store' => 'turnos.store',
            'show' => 'turnos.show',
            'update' => 'turnos.update',
            'destroy' => 'turnos.destroy',
        ]
    ]);
    
    Route::patch('/turnos/{id}/completar', [AppointmentController::class, 'completar'])
        ->middleware('can:completar,id');
    Route::patch('/turnos/{id}/cancelar', [AppointmentController::class, 'cancelar'])
        ->middleware('can:cancelar,id');

    // Agenda del doctor
    Route::get('/agenda/doctor/{id}', [DoctorController::class, 'agenda'])
        ->middleware('can:verAgenda,id');
    
    // Reportes PDF para personal autorizado
    Route::middleware(['role:admin,doctor,secretaria'])->group(function () {
        Route::get('/agenda/pdf', [PdfController::class, 'agendaPdf']);
        Route::get('/reportes/turnos/pdf', [PdfController::class, 'reporteTurnosPdf']);
        Route::get('/reportes/estadisticas/pdf', [PdfController::class, 'estadisticasPdf']);
    });
    
    // Historia clínica PDF (solo médicos y admin)
    Route::middleware(['role:admin,doctor'])->group(function () {
        Route::get('/historia-clinica/{id}/pdf', [PdfController::class, 'historiaClinicaPdf'])
            ->middleware('can:verHistoriaClinica,id');
    });

    // Especialidades de la clínica
    Route::get('/especialidades', function() {
        return response()->json([
            'success' => true,
            'especialidades' => \App\Models\Especialidad::orderBy('nombre')->get()
        ]);
    });

    // Operaciones de emergencia (solo admin)
    Route::middleware(['role:admin'])->group(function () {
        Route::post('/emergencia/cerrar-sistema', function() {
            // Lógica para cerrar acceso en emergencias
            return response()->json([
                'mensaje' => 'Sistema cerrado por emergencia',
                'timestamp' => now()
            ]);
        });
        
        Route::post('/emergencia/backup-urgente', function() {
            // Trigger backup de emergencia
            return response()->json([
                'mensaje' => 'Backup de emergencia iniciado',
                'timestamp' => now()
            ]);
        });
    });
});
