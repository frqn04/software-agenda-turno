<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\PdfController;

Route::middleware(['throttle.ban:10,1'])->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware(['throttle:60,1'])->group(function () {
    Route::get('/test', function () {
        return response()->json([
            'message' => 'API funcionando correctamente',
            'timestamp' => now(),
            'version' => '1.0.0'
        ]);
    });
});

Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
    
    Route::middleware(['role:admin'])->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/revoke-all-tokens', [AuthController::class, 'revokeAllTokens']);
    });

    Route::apiResource('pacientes', PatientController::class);
    Route::get('/pacientes/{id}/historia-clinica', [PatientController::class, 'clinicalHistory'])
        ->can('viewClinicalHistory,id');
    Route::post('/pacientes/{id}/historia-clinica', [PatientController::class, 'createClinicalHistory'])
        ->can('createClinicalHistory,id');
    Route::post('/pacientes/{id}/evoluciones', [PatientController::class, 'addEvolution'])
        ->can('updateClinicalHistory,id');

    Route::apiResource('doctores', DoctorController::class);
    Route::get('/doctores/{id}/agenda', [DoctorController::class, 'agenda'])
        ->can('viewSchedule,id');
    Route::get('/doctores/{id}/slots-disponibles', [DoctorController::class, 'availableSlots'])
        ->can('viewSchedule,id');

    Route::apiResource('turnos', AppointmentController::class, [
        'names' => [
            'index' => 'appointments.index',
            'store' => 'appointments.store',
            'show' => 'appointments.show',
            'update' => 'appointments.update',
            'destroy' => 'appointments.destroy',
        ]
    ]);
    
    Route::patch('/turnos/{id}/realizar', [AppointmentController::class, 'complete'])
        ->can('complete,id');
    Route::patch('/turnos/{id}/cancelar', [AppointmentController::class, 'cancel'])
        ->can('cancel,id');

    Route::get('/agenda/doctor/{id}', [DoctorController::class, 'agenda'])
        ->can('viewSchedule,id');
    
    Route::middleware(['role:admin,doctor,secretaria'])->group(function () {
        Route::get('/agenda/pdf', [PdfController::class, 'agendaPdf']);
        Route::get('/reportes/turnos/pdf', [PdfController::class, 'reporteTurnosPdf']);
    });
    
    Route::middleware(['role:admin,doctor'])->group(function () {
        Route::get('/historia-clinica/{id}/pdf', [PdfController::class, 'historiaClinicaPdf']);
    });

    Route::middleware(['role:admin,doctor,secretaria'])->group(function () {
        Route::get('/especialidades', function() {
            return response()->json([
                'success' => true,
                'data' => \App\Models\Especialidad::orderBy('nombre')->get()
            ]);
        });
    });
});
