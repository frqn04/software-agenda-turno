<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Especialidad;
use App\Models\Paciente;
use App\Models\Turno;

class SystemHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:health-check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verificar la salud del sistema de agenda de turnos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🏥 Sistema de Agenda de Turnos - Verificación de Salud');
        $this->newLine();

        // Verificar base de datos
        $this->checkDatabase();
        
        // Verificar modelos
        $this->checkModels();
        
        // Verificar configuración
        $this->checkConfiguration();
        
        // Verificar servicios
        $this->checkServices();

        $this->newLine();
        $this->info('✅ Verificación completada');
    }

    private function checkDatabase()
    {
        $this->info('📊 Verificando Base de Datos...');
        
        try {
            // Verificar conexión
            \DB::connection()->getPdo();
            $this->line('  ✅ Conexión a la base de datos: OK');
            
            // Contar registros
            $tablas = [
                'users' => User::count(),
                'especialidades' => Especialidad::count(),
                'doctores' => Doctor::count(),
                'pacientes' => Paciente::count(),
                'turnos' => Turno::count(),
            ];
            
            foreach ($tablas as $tabla => $count) {
                $this->line("  📋 {$tabla}: {$count} registros");
            }
            
        } catch (\Exception $e) {
            $this->error('  ❌ Error de base de datos: ' . $e->getMessage());
        }
    }

    private function checkModels()
    {
        $this->info('🔧 Verificando Modelos...');
        
        $modelos = [
            'User' => User::class,
            'Doctor' => Doctor::class,
            'Especialidad' => Especialidad::class,
            'Paciente' => Paciente::class,
            'Turno' => Turno::class,
        ];
        
        foreach ($modelos as $nombre => $clase) {
            try {
                $modelo = new $clase();
                $this->line("  ✅ Modelo {$nombre}: OK");
            } catch (\Exception $e) {
                $this->error("  ❌ Error en modelo {$nombre}: " . $e->getMessage());
            }
        }
    }

    private function checkConfiguration()
    {
        $this->info('⚙️ Verificando Configuración...');
        
        $configs = [
            'APP_ENV' => config('app.env'),
            'DB_CONNECTION' => config('database.default'),
            'SANCTUM_EXPIRATION' => config('sanctum.expiration', '24 horas'),
            'CACHE_DRIVER' => config('cache.default'),
        ];
        
        foreach ($configs as $key => $value) {
            $this->line("  📝 {$key}: {$value}");
        }
    }

    private function checkServices()
    {
        $this->info('🛠️ Verificando Servicios...');
        
        try {
            // Verificar AppointmentValidationService
            $service = app(\App\Services\AppointmentValidationService::class);
            $this->line('  ✅ AppointmentValidationService: OK');
        } catch (\Exception $e) {
            $this->error('  ❌ Error en AppointmentValidationService: ' . $e->getMessage());
        }
        
        try {
            // Verificar cache
            \Cache::put('health_check', 'test', 60);
            $value = \Cache::get('health_check');
            if ($value === 'test') {
                $this->line('  ✅ Sistema de Cache: OK');
            } else {
                $this->error('  ❌ Sistema de Cache: FALLO');
            }
        } catch (\Exception $e) {
            $this->error('  ❌ Error en Cache: ' . $e->getMessage());
        }
    }
}
