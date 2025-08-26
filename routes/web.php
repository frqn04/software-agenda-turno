<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Sistema de Clínica Dental Interno
|--------------------------------------------------------------------------
|
| Rutas web para el sistema de gestión de turnos odontológicos.
| Sistema interno para personal de clínica únicamente.
|
*/

// Ruta principal - redirige al sistema de turnos
Route::get('/', function () {
    return redirect('/admin/turnos');
});

// Ruta de salud del sistema para monitoreo interno
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'sistema' => 'Clínica Dental - Gestión de Turnos',
        'version' => '1.0.0',
        'timestamp' => now()->toISOString(),
        'ambiente' => 'interno'
    ]);
});

// Ruta de información del sistema (solo acceso interno)
Route::get('/system-info', function () {
    return response()->json([
        'laravel_version' => app()->version(),
        'php_version' => PHP_VERSION,
        'servidor' => 'Sistema Interno Clínica',
        'usuarios_activos' => \App\Models\User::where('active', true)->count(),
        'doctores_activos' => \App\Models\Doctor::where('active', true)->count(),
    ]);
});
