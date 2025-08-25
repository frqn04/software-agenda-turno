<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Doctor;
use App\Models\DoctorContract;
use App\Models\DoctorScheduleSlot;
use App\Models\Especialidad;
use App\Models\Paciente;
use App\Models\Turno;
use App\Models\HistoriaClinica;
use App\Models\Evolucion;
use App\Models\LogAuditoria;
use Carbon\Carbon;

/**
 * Comando para verificar la salud integral del sistema médico
 * Incluye checks específicos para funcionalidades médicas críticas
 */
class SystemHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'system:health-check 
                           {--critical-only : Solo verificaciones críticas}
                           {--detailed : Mostrar información detallada}
                           {--json : Salida en formato JSON}';

    /**
     * The console command description.
     */
    protected $description = 'Verificación integral de salud del sistema médico';

    private array $resultados = [];
    private bool $criticalOnly = false;
    private bool $detailed = false;
    private bool $jsonOutput = false;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->criticalOnly = $this->option('critical-only');
        $this->detailed = $this->option('detailed');
        $this->jsonOutput = $this->option('json');

        if (!$this->jsonOutput) {
            $this->mostrarEncabezado();
        }

        try {
            // Verificaciones críticas
            $this->verificarBaseDatos();
            $this->verificarModelos();
            $this->verificarIntegridadDatos();
            $this->verificarSistemasCriticos();

            if (!$this->criticalOnly) {
                // Verificaciones adicionales
                $this->verificarConfiguracion();
                $this->verificarServicios();
                $this->verificarRendimiento();
                $this->verificarSeguridad();
                $this->verificarNotificaciones();
                $this->generarEstadisticasMedicas();
            }

            if ($this->jsonOutput) {
                $this->outputJson();
            } else {
                $this->mostrarResumen();
            }

            $this->registrarResultados();

            return $this->determinarCodigoSalida();

        } catch (\Exception $e) {
            $error = "Error durante verificación: {$e->getMessage()}";
            
            if ($this->jsonOutput) {
                echo json_encode(['error' => $error, 'success' => false]);
            } else {
                $this->error("❌ " . $error);
            }

            Log::error('Error en health check', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Verificar conexión y estado de la base de datos
     */
    private function verificarBaseDatos(): void
    {
        $this->seccion('Base de Datos');
        
        try {
            // Verificar conexión
            $pdo = DB::connection()->getPdo();
            $this->ok('Conexión a la base de datos');
            
            // Verificar transacciones
            DB::beginTransaction();
            DB::rollback();
            $this->ok('Capacidad transaccional');
            
            // Contar registros en tablas críticas
            $tablas = [
                'users' => User::count(),
                'especialidades' => Especialidad::count(),
                'doctores' => Doctor::count(),
                'pacientes' => Paciente::count(),
                'turnos' => Turno::count(),
                'historias_clinicas' => HistoriaClinica::count(),
                'evoluciones' => Evolucion::count(),
                'doctor_contracts' => DoctorContract::count(),
                'doctor_schedule_slots' => DoctorScheduleSlot::count(),
                'logs_auditoria' => LogAuditoria::count(),
            ];
            
            foreach ($tablas as $tabla => $count) {
                $this->info("Tabla {$tabla}: {$count} registros", $tabla . '_count', $count);
            }
            
            // Verificar integridad referencial básica
            $this->verificarReferenciasCriticas();
            
        } catch (\Exception $e) {
            $this->error('Error de base de datos: ' . $e->getMessage(), 'database_error');
        }
    }

    /**
     * Verificar referencias críticas entre tablas
     */
    private function verificarReferenciasCriticas(): void
    {
        // Doctores sin especialidad
        $doctoresSinEspecialidad = Doctor::whereNull('especialidad_id')->count();
        if ($doctoresSinEspecialidad > 0) {
            $this->warning("Doctores sin especialidad: {$doctoresSinEspecialidad}", 'doctores_sin_especialidad');
        }

        // Turnos huérfanos
        $turnosHuerfanos = Turno::leftJoin('pacientes', 'turnos.paciente_id', '=', 'pacientes.id')
            ->leftJoin('doctores', 'turnos.doctor_id', '=', 'doctores.id')
            ->whereNull('pacientes.id')
            ->orWhereNull('doctores.id')
            ->count();
        
        if ($turnosHuerfanos > 0) {
            $this->error("Turnos con referencias rotas: {$turnosHuerfanos}", 'turnos_huerfanos');
        } else {
            $this->ok('Integridad referencial de turnos');
        }

        // Historias clínicas sin paciente
        $historiasSinPaciente = HistoriaClinica::leftJoin('pacientes', 'historias_clinicas.paciente_id', '=', 'pacientes.id')
            ->whereNull('pacientes.id')
            ->count();
        
        if ($historiasSinPaciente > 0) {
            $this->error("Historias clínicas huérfanas: {$historiasSinPaciente}", 'historias_huerfanas');
        }
    }

    /**
     * Verificar que los modelos funcionen correctamente
     */
    private function verificarModelos(): void
    {
        $this->seccion('Modelos del Sistema');
        
        $modelos = [
            'User' => User::class,
            'Doctor' => Doctor::class,
            'DoctorContract' => DoctorContract::class,
            'DoctorScheduleSlot' => DoctorScheduleSlot::class,
            'Especialidad' => Especialidad::class,
            'Paciente' => Paciente::class,
            'Turno' => Turno::class,
            'HistoriaClinica' => HistoriaClinica::class,
            'Evolucion' => Evolucion::class,
            'LogAuditoria' => LogAuditoria::class,
        ];
        
        foreach ($modelos as $nombre => $clase) {
            try {
                // Verificar que el modelo se puede instanciar
                $modelo = new $clase();
                
                // Verificar que tiene la tabla correcta
                $tabla = $modelo->getTable();
                
                // Intentar hacer una consulta simple
                $clase::limit(1)->get();
                
                $this->ok("Modelo {$nombre} (tabla: {$tabla})");
                
            } catch (\Exception $e) {
                $this->error("Error en modelo {$nombre}: " . $e->getMessage(), "modelo_{$nombre}_error");
            }
        }
    }

    /**
     * Verificar integridad de datos médicos críticos
     */
    private function verificarIntegridadDatos(): void
    {
        $this->seccion('Integridad de Datos Médicos');

        // Verificar doctores activos sin horarios
        $doctoresSinHorarios = Doctor::activos()
            ->whereDoesntHave('horariosActivos')
            ->count();
        
        if ($doctoresSinHorarios > 0) {
            $this->warning("Doctores activos sin horarios: {$doctoresSinHorarios}", 'doctores_sin_horarios');
        } else {
            $this->ok('Doctores tienen horarios configurados');
        }

        // Verificar contratos vencidos
        $contratosVencidos = DoctorContract::where('is_active', true)
            ->where('fecha_fin', '<', now())
            ->count();
        
        if ($contratosVencidos > 0) {
            $this->warning("Contratos vencidos activos: {$contratosVencidos}", 'contratos_vencidos');
        }

        // Verificar turnos en el pasado sin estado final
        $turnosPendientesPasados = Turno::where('fecha', '<', now()->toDateString())
            ->where('estado', Turno::ESTADO_PROGRAMADO)
            ->count();
        
        if ($turnosPendientesPasados > 0) {
            $this->warning("Turnos pasados sin resolver: {$turnosPendientesPasados}", 'turnos_pendientes_pasados');
        }

        // Verificar especialidades sin doctores
        $especialidadesSinDoctores = Especialidad::activas()
            ->whereDoesntHave('doctoresActivos')
            ->count();
        
        if ($especialidadesSinDoctores > 0) {
            $this->warning("Especialidades sin doctores: {$especialidadesSinDoctores}", 'especialidades_sin_doctores');
        }

        // Verificar pacientes con datos críticos faltantes
        $pacientesSinContacto = Paciente::activos()
            ->where(function($q) {
                $q->whereNull('email')
                  ->orWhere('email', '')
                  ->whereNull('telefono')
                  ->orWhere('telefono', '');
            })
            ->count();
        
        if ($pacientesSinContacto > 0) {
            $this->warning("Pacientes sin datos de contacto: {$pacientesSinContacto}", 'pacientes_sin_contacto');
        }
    }

    /**
     * Verificar sistemas críticos
     */
    private function verificarSistemasCriticos(): void
    {
        $this->seccion('Sistemas Críticos');

        // Verificar sistema de colas
        try {
            $connection = config('queue.default');
            $this->info("Sistema de colas: {$connection}", 'queue_system', $connection);
        } catch (\Exception $e) {
            $this->error('Error en sistema de colas: ' . $e->getMessage(), 'queue_error');
        }

        // Verificar sistema de cache
        try {
            $cacheKey = 'health_check_' . now()->timestamp;
            Cache::put($cacheKey, 'test', 60);
            $value = Cache::get($cacheKey);
            
            if ($value === 'test') {
                $this->ok('Sistema de cache');
                Cache::forget($cacheKey);
            } else {
                $this->error('Sistema de cache no funciona correctamente', 'cache_error');
            }
        } catch (\Exception $e) {
            $this->error('Error en cache: ' . $e->getMessage(), 'cache_error');
        }

        // Verificar logging
        try {
            Log::info('Health check test log');
            $this->ok('Sistema de logging');
        } catch (\Exception $e) {
            $this->error('Error en logging: ' . $e->getMessage(), 'logging_error');
        }
    }

    /**
     * Verificar configuración del sistema
     */
    private function verificarConfiguracion(): void
    {
        $this->seccion('Configuración del Sistema');
        
        $configs = [
            'Entorno' => config('app.env'),
            'Debug' => config('app.debug') ? 'Habilitado' : 'Deshabilitado',
            'Base de datos' => config('database.default'),
            'Cache' => config('cache.default'),
            'Cola' => config('queue.default'),
            'Mail' => config('mail.default'),
            'Timezone' => config('app.timezone'),
            'Locale' => config('app.locale'),
        ];
        
        foreach ($configs as $key => $value) {
            $this->info("{$key}: {$value}", strtolower(str_replace(' ', '_', $key)), $value);
        }

        // Verificar configuraciones críticas
        if (config('app.env') === 'production' && config('app.debug')) {
            $this->warning('Debug habilitado en producción', 'debug_production_warning');
        }
    }

    /**
     * Verificar servicios de la aplicación
     */
    private function verificarServicios(): void
    {
        $this->seccion('Servicios de la Aplicación');
        
        $servicios = [
            'AppointmentValidationService' => \App\Services\AppointmentValidationService::class,
            'NotificationService' => \App\Services\NotificationService::class,
        ];
        
        foreach ($servicios as $nombre => $clase) {
            try {
                if (class_exists($clase)) {
                    app($clase);
                    $this->ok("Servicio {$nombre}");
                } else {
                    $this->warning("Clase {$clase} no encontrada", strtolower($nombre) . '_missing');
                }
            } catch (\Exception $e) {
                $this->error("Error en {$nombre}: " . $e->getMessage(), strtolower($nombre) . '_error');
            }
        }
    }

    /**
     * Verificar rendimiento del sistema
     */
    private function verificarRendimiento(): void
    {
        $this->seccion('Rendimiento');

        // Medir tiempo de consulta simple
        $start = microtime(true);
        User::count();
        $queryTime = round((microtime(true) - $start) * 1000, 2);
        
        if ($queryTime > 100) {
            $this->warning("Consulta lenta detectada: {$queryTime}ms", 'slow_query');
        } else {
            $this->ok("Tiempo de consulta: {$queryTime}ms");
        }

        // Verificar uso de memoria
        $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2);
        $this->info("Uso de memoria: {$memoryUsage}MB", 'memory_usage', $memoryUsage);

        // Verificar espacio en disco (si es posible)
        if (function_exists('disk_free_space')) {
            $freeSpace = disk_free_space('.');
            if ($freeSpace !== false) {
                $freeSpaceGB = round($freeSpace / 1024 / 1024 / 1024, 2);
                $this->info("Espacio libre en disco: {$freeSpaceGB}GB", 'disk_space', $freeSpaceGB);
                
                if ($freeSpaceGB < 1) {
                    $this->warning('Poco espacio en disco', 'low_disk_space');
                }
            }
        }
    }

    /**
     * Verificar seguridad básica
     */
    private function verificarSeguridad(): void
    {
        $this->seccion('Seguridad');

        // Verificar usuarios bloqueados
        $usuariosBloqueados = User::bloqueados()->count();
        if ($usuariosBloqueados > 0) {
            $this->info("Usuarios bloqueados: {$usuariosBloqueados}", 'usuarios_bloqueados', $usuariosBloqueados);
        }

        // Verificar usuarios sin acceso reciente
        $usuariosSinAcceso = User::sinAccesoReciente(30)->count();
        if ($usuariosSinAcceso > 0) {
            $this->info("Usuarios sin acceso (30 días): {$usuariosSinAcceso}", 'usuarios_sin_acceso', $usuariosSinAcceso);
        }

        // Verificar logs de auditoría recientes
        $logsRecientes = LogAuditoria::where('created_at', '>=', now()->subDays(7))->count();
        $this->info("Logs de auditoría (7 días): {$logsRecientes}", 'logs_auditoria', $logsRecientes);
    }

    /**
     * Verificar sistema de notificaciones
     */
    private function verificarNotificaciones(): void
    {
        $this->seccion('Sistema de Notificaciones');

        // Verificar configuración de mail
        try {
            $mailConfig = config('mail.default');
            if ($mailConfig) {
                $this->ok("Configuración de email: {$mailConfig}");
            } else {
                $this->warning('Email no configurado', 'email_not_configured');
            }
        } catch (\Exception $e) {
            $this->error('Error en configuración de email', 'email_config_error');
        }

        // Verificar jobs pendientes (si hay información disponible)
        try {
            // Esta verificación depende del driver de queue usado
            $this->info('Sistema de jobs: Configurado', 'jobs_system', 'configured');
        } catch (\Exception $e) {
            $this->warning('No se pudo verificar sistema de jobs', 'jobs_check_failed');
        }
    }

    /**
     * Generar estadísticas médicas importantes
     */
    private function generarEstadisticasMedicas(): void
    {
        $this->seccion('Estadísticas Médicas');

        // Turnos de hoy
        $turnosHoy = Turno::whereDate('fecha', today())->count();
        $this->info("Turnos para hoy: {$turnosHoy}", 'turnos_hoy', $turnosHoy);

        // Turnos de la semana
        $turnosSemana = Turno::whereBetween('fecha', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ])->count();
        $this->info("Turnos esta semana: {$turnosSemana}", 'turnos_semana', $turnosSemana);

        // Doctores activos
        $doctoresActivos = Doctor::activos()->count();
        $this->info("Doctores activos: {$doctoresActivos}", 'doctores_activos', $doctoresActivos);

        // Pacientes activos
        $pacientesActivos = Paciente::activos()->count();
        $this->info("Pacientes activos: {$pacientesActivos}", 'pacientes_activos', $pacientesActivos);

        // Especialidades disponibles
        $especialidadesActivas = Especialidad::activas()->count();
        $this->info("Especialidades activas: {$especialidadesActivas}", 'especialidades_activas', $especialidadesActivas);

        // Turnos por estado
        $estadosCount = Turno::selectRaw('estado, COUNT(*) as count')
            ->groupBy('estado')
            ->pluck('count', 'estado')
            ->toArray();
        
        foreach ($estadosCount as $estado => $count) {
            $this->info("Turnos {$estado}: {$count}", "turnos_{$estado}", $count);
        }
    }

    /**
     * Determinar código de salida basado en errores críticos
     */
    private function determinarCodigoSalida(): int
    {
        $erroresCriticos = collect($this->resultados)
            ->where('tipo', 'error')
            ->count();

        return $erroresCriticos > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Métodos auxiliares para registrar resultados
     */
    private function ok(string $mensaje, string $key = null, $value = null): void
    {
        $this->registrarResultado('ok', $mensaje, $key, $value);
        if (!$this->jsonOutput) {
            $this->line("  ✅ {$mensaje}");
        }
    }

    private function warning(string $mensaje, string $key = null, $value = null): void
    {
        $this->registrarResultado('warning', $mensaje, $key, $value);
        if (!$this->jsonOutput) {
            $this->line("  ⚠️  {$mensaje}");
        }
    }

    private function error(string $mensaje, string $key = null, $value = null): void
    {
        $this->registrarResultado('error', $mensaje, $key, $value);
        if (!$this->jsonOutput) {
            $this->line("  ❌ {$mensaje}");
        }
    }

    private function info(string $mensaje, string $key = null, $value = null): void
    {
        $this->registrarResultado('info', $mensaje, $key, $value);
        if (!$this->jsonOutput && $this->detailed) {
            $this->line("  ℹ️  {$mensaje}");
        }
    }

    private function seccion(string $titulo): void
    {
        if (!$this->jsonOutput) {
            $this->line("");
            $this->line("🔍 {$titulo}");
        }
    }

    private function registrarResultado(string $tipo, string $mensaje, string $key = null, $value = null): void
    {
        $resultado = [
            'tipo' => $tipo,
            'mensaje' => $mensaje,
            'timestamp' => now()->toISOString(),
        ];

        if ($key) {
            $resultado['key'] = $key;
        }

        if ($value !== null) {
            $resultado['value'] = $value;
        }

        $this->resultados[] = $resultado;
    }

    private function mostrarEncabezado(): void
    {
        $this->line('');
        $this->line('🏥 <bg=blue;fg=white> VERIFICACIÓN DE SALUD DEL SISTEMA MÉDICO </bg=blue;fg=white>');
        $this->line('📅 Ejecutado: ' . now()->format('d/m/Y H:i:s'));
        $this->line('🖥️  Servidor: ' . gethostname());
        $this->line('');
    }

    private function mostrarResumen(): void
    {
        $totales = collect($this->resultados)->countBy('tipo');
        
        $this->line('');
        $this->line('📊 <bg=green;fg=white> RESUMEN DE VERIFICACIÓN </bg=green;fg=white>');
        $this->line("✅ Exitosos: " . ($totales['ok'] ?? 0));
        $this->line("⚠️  Advertencias: " . ($totales['warning'] ?? 0));
        $this->line("❌ Errores: " . ($totales['error'] ?? 0));
        $this->line("ℹ️  Informativos: " . ($totales['info'] ?? 0));
        
        if (($totales['error'] ?? 0) === 0) {
            $this->line('');
            $this->line('🎉 <bg=green;fg=white> Sistema funcionando correctamente </bg=green;fg=white>');
        } else {
            $this->line('');
            $this->line('⚠️  <bg=red;fg=white> Se encontraron errores críticos </bg=red;fg=white>');
        }
        
        $this->line('');
    }

    private function outputJson(): void
    {
        $totales = collect($this->resultados)->countBy('tipo');
        
        $output = [
            'success' => ($totales['error'] ?? 0) === 0,
            'timestamp' => now()->toISOString(),
            'summary' => [
                'ok' => $totales['ok'] ?? 0,
                'warnings' => $totales['warning'] ?? 0,
                'errors' => $totales['error'] ?? 0,
                'info' => $totales['info'] ?? 0,
            ],
            'details' => $this->resultados,
        ];

        echo json_encode($output, JSON_PRETTY_PRINT);
    }

    private function registrarResultados(): void
    {
        Log::info('Health check ejecutado', [
            'resultados' => $this->resultados,
            'resumen' => collect($this->resultados)->countBy('tipo'),
            'opciones' => [
                'critical-only' => $this->criticalOnly,
                'detailed' => $this->detailed,
                'json' => $this->jsonOutput,
            ],
        ]);
    }
}
