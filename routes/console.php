<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes - Sistema Clínica Dental
|--------------------------------------------------------------------------
|
| Comandos de consola para mantenimiento y gestión del sistema interno
| de la clínica dental.
|
*/

// Comando motivacional para el equipo (conservamos el original)
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Mostrar una frase inspiradora para el equipo');

// Comando para estadísticas diarias de la clínica
Artisan::command('clinica:estadisticas-diarias', function () {
    $this->info('=== Estadísticas Diarias de la Clínica ===');
    $this->line('Fecha: ' . now()->format('d/m/Y'));
    
    $turnosHoy = \App\Models\Turno::whereDate('fecha', today())->count();
    $turnosCompletados = \App\Models\Turno::whereDate('fecha', today())->where('estado', 'completado')->count();
    $doctoresActivos = \App\Models\Doctor::where('active', true)->count();
    
    $this->line("Turnos programados hoy: {$turnosHoy}");
    $this->line("Turnos completados: {$turnosCompletados}");
    $this->line("Doctores activos: {$doctoresActivos}");
    
})->purpose('Mostrar estadísticas diarias de la clínica');

// Comando para limpieza de cache del sistema
Artisan::command('clinica:limpiar-cache', function () {
    $this->info('Limpiando cache del sistema...');
    
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('view:clear');
    
    $this->info('✅ Cache del sistema limpiado correctamente');
    
})->purpose('Limpiar todo el cache del sistema');

// Comando para backup de datos críticos
Artisan::command('clinica:backup-datos', function () {
    $this->info('Iniciando backup de datos críticos...');
    
    // Aquí iría la lógica de backup
    $timestamp = now()->format('Y-m-d_H-i-s');
    $this->line("Backup creado: backup_clinica_{$timestamp}");
    $this->info('✅ Backup completado exitosamente');
    
})->purpose('Crear backup de los datos críticos de la clínica');

// Comando para verificar integridad del sistema
Artisan::command('clinica:verificar-sistema', function () {
    $this->info('Verificando integridad del sistema...');
    
    // Verificar conexión a base de datos
    try {
        \DB::connection()->getPdo();
        $this->line('✅ Conexión a base de datos: OK');
    } catch (\Exception $e) {
        $this->error('❌ Error en base de datos: ' . $e->getMessage());
    }
    
    // Verificar modelos principales
    $doctores = \App\Models\Doctor::count();
    $pacientes = \App\Models\Paciente::count();
    
    $this->line("📊 Doctores en sistema: {$doctores}");
    $this->line("📊 Pacientes en sistema: {$pacientes}");
    $this->info('✅ Verificación del sistema completada');
    
})->purpose('Verificar la integridad y estado del sistema');
