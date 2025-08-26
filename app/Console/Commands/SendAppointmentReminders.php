<?php

namespace App\Console\Commands;

use App\Models\Turno;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Comando para generar alertas internas de turnos
 * Sistema interno - solo para notificaciones al personal de la clínica
 */
class SendAppointmentReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'clinica:alertas-turnos 
                           {--tipo=hoy : Tipo de alerta (hoy, manana, vencidos)}
                           {--silent : Ejecutar sin mostrar detalles}';

    /**
     * The console command description.
     */
    protected $description = 'Genera alertas internas para el personal sobre turnos del día';

    private array $estadisticas = [
        'turnos_hoy' => 0,
        'turnos_manana' => 0,
        'turnos_vencidos' => 0,
        'doctores_ocupados' => 0
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->mostrarEncabezado();

        try {
            $tipo = $this->option('tipo');
            $silent = $this->option('silent');

            switch ($tipo) {
                case 'hoy':
                    $this->procesarTurnosHoy($silent);
                    break;
                case 'manana':
                    $this->procesarTurnosManana($silent);
                    break;
                case 'vencidos':
                    $this->procesarTurnosVencidos($silent);
                    break;
                default:
                    $this->procesarTodosLosTurnos($silent);
            }

            if (!$silent) {
                $this->mostrarResumen();
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            Log::error('Error en alertas de turnos', [
                'error' => $e->getMessage(),
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Ejecuta todos los tipos de recordatorios
     */
    private function ejecutarTodosLosRecordatorios(bool $dryRun, bool $force): void
    {
        $tipos = [
            SendAppointmentReminderJob::TIPO_CONFIRMACION,
            SendAppointmentReminderJob::TIPO_24_HORAS,
            SendAppointmentReminderJob::TIPO_2_HORAS,
            SendAppointmentReminderJob::TIPO_30_MINUTOS,
            SendAppointmentReminderJob::TIPO_SEGUIMIENTO,
        ];

        foreach ($tipos as $tipo) {
            $this->line("");
            $this->ejecutarRecordatorioEspecifico($tipo, $dryRun, $force);
        }
    }

    /**
     * Ejecuta un tipo específico de recordatorio
     */
    private function ejecutarRecordatorioEspecifico(string $tipo, bool $dryRun, bool $force): void
    {
        $this->info("📅 Procesando recordatorios tipo: " . strtoupper($tipo));

        $turnos = $this->obtenerTurnosParaRecordatorio($tipo, $force);
        $this->estadisticas['total_encontrados'] += $turnos->count();

        if ($turnos->isEmpty()) {
            $this->line("   ℹ️  No hay turnos para procesar");
            return;
        }

        $this->line("   📋 Encontrados {$turnos->count()} turnos");

        $enviados = 0;
        $errores = 0;
        $omitidos = 0;

        $progressBar = $this->output->createProgressBar($turnos->count());
        $progressBar->start();

        foreach ($turnos as $turno) {
            try {
                // Validar turno antes de enviar
                $validacion = $this->validarTurno($turno, $tipo);
                
                if (!$validacion['valido']) {
                    $omitidos++;
                    if (!$dryRun) {
                        $this->line("\n   ⚠️  Turno {$turno->id} omitido: {$validacion['razon']}");
                    }
                    $progressBar->advance();
                    continue;
                }

                if (!$dryRun) {
                    // Enviar recordatorio real
                    SendAppointmentReminderJob::dispatch($turno, $tipo)
                        ->onQueue($this->determinarCola($tipo));
                }

                $enviados++;
                
                if (!$dryRun) {
                    $this->line("\n   ✅ Recordatorio programado para turno {$turno->id} - {$turno->paciente->nombre_completo}");
                }

            } catch (\Exception $e) {
                $errores++;
                $this->line("\n   ❌ Error con turno {$turno->id}: {$e->getMessage()}");
                
                Log::error('Error programando recordatorio específico', [
                    'turno_id' => $turno->id,
                    'tipo' => $tipo,
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->line("");

        // Actualizar estadísticas
        $this->estadisticas['enviados_exitosos'] += $enviados;
        $this->estadisticas['errores'] += $errores;
        $this->estadisticas['omitidos'] += $omitidos;
        $this->estadisticas['por_tipo'][$tipo] = [
            'encontrados' => $turnos->count(),
            'enviados' => $enviados,
            'errores' => $errores,
            'omitidos' => $omitidos,
        ];

        $this->mostrarResumenTipo($tipo, $turnos->count(), $enviados, $errores, $omitidos);
    }

    /**
     * Obtiene turnos para un tipo específico de recordatorio
     */
    private function obtenerTurnosParaRecordatorio(string $tipo, bool $force): \Illuminate\Database\Eloquent\Collection
    {
        $query = Turno::with(['paciente', 'doctor', 'doctor.especialidad'])
            ->where('estado', Turno::ESTADO_PROGRAMADO);

        // Aplicar filtros de fecha según el tipo de recordatorio
        switch ($tipo) {
            case SendAppointmentReminderJob::TIPO_CONFIRMACION:
                // 48 horas antes (rango de 47-49 horas)
                $fechaInicio = now()->addHours(47);
                $fechaFin = now()->addHours(49);
                break;

            case SendAppointmentReminderJob::TIPO_24_HORAS:
                // 24 horas antes (rango de 23-25 horas)
                $fechaInicio = now()->addHours(23);
                $fechaFin = now()->addHours(25);
                break;

            case SendAppointmentReminderJob::TIPO_2_HORAS:
                // 2 horas antes (rango de 1.5-2.5 horas)
                $fechaInicio = now()->addMinutes(90);
                $fechaFin = now()->addMinutes(150);
                break;

            case SendAppointmentReminderJob::TIPO_30_MINUTOS:
                // 30 minutos antes (rango de 25-35 minutos)
                $fechaInicio = now()->addMinutes(25);
                $fechaFin = now()->addMinutes(35);
                break;

            case SendAppointmentReminderJob::TIPO_SEGUIMIENTO:
                // 24 horas después (rango de 23-25 horas después)
                $fechaInicio = now()->subHours(25);
                $fechaFin = now()->subHours(23);
                $query->where('estado', Turno::ESTADO_REALIZADO);
                break;

            default:
                throw new \InvalidArgumentException("Tipo de recordatorio no válido: {$tipo}");
        }

        // Construir query de fecha y hora
        $query->where(function ($q) use ($fechaInicio, $fechaFin) {
            $q->whereBetween('fecha', [
                $fechaInicio->toDateString(),
                $fechaFin->toDateString()
            ]);
            
            // Si es el mismo día, filtrar también por hora
            if ($fechaInicio->toDateString() === $fechaFin->toDateString()) {
                $q->whereBetween('hora_inicio', [
                    $fechaInicio->toTimeString(),
                    $fechaFin->toTimeString()
                ]);
            }
        });

        // Si no es force, evitar duplicados verificando en logs de auditoría
        if (!$force) {
            $query->whereDoesntHave('logsAuditoria', function ($q) use ($tipo) {
                $q->where('accion', 'recordatorio_enviado')
                  ->whereJsonContains('valores_nuevos->tipo_recordatorio', $tipo)
                  ->where('created_at', '>=', now()->subHours(6)); // Evitar duplicados en las últimas 6 horas
            });
        }

        return $query->get();
    }

    /**
     * Valida si un turno puede recibir recordatorio
     */
    private function validarTurno(Turno $turno, string $tipo): array
    {
        // Verificar que el paciente exista
        if (!$turno->paciente) {
            return ['valido' => false, 'razon' => 'Paciente no encontrado'];
        }

        // Verificar datos de contacto según el tipo
        $paciente = $turno->paciente;
        $tieneEmail = !empty($paciente->email) && filter_var($paciente->email, FILTER_VALIDATE_EMAIL);
        $tieneTelefono = !empty($paciente->telefono) && strlen(preg_replace('/[^0-9]/', '', $paciente->telefono)) >= 8;

        switch ($tipo) {
            case SendAppointmentReminderJob::TIPO_CONFIRMACION:
            case SendAppointmentReminderJob::TIPO_SEGUIMIENTO:
                if (!$tieneEmail) {
                    return ['valido' => false, 'razon' => 'Email requerido para este tipo de recordatorio'];
                }
                break;

            case SendAppointmentReminderJob::TIPO_30_MINUTOS:
            case SendAppointmentReminderJob::TIPO_2_HORAS:
                if (!$tieneTelefono) {
                    return ['valido' => false, 'razon' => 'Teléfono requerido para recordatorios urgentes'];
                }
                break;

            case SendAppointmentReminderJob::TIPO_24_HORAS:
                if (!$tieneEmail && !$tieneTelefono) {
                    return ['valido' => false, 'razon' => 'Email o teléfono requerido'];
                }
                break;
        }

        // Verificar que el doctor esté activo
        if (!$turno->doctor || !$turno->doctor->activo) {
            return ['valido' => false, 'razon' => 'Doctor inactivo'];
        }

        // Verificar que el turno no esté muy en el pasado (para seguimiento)
        if ($tipo === SendAppointmentReminderJob::TIPO_SEGUIMIENTO) {
            $fechaTurno = Carbon::parse($turno->fecha . ' ' . $turno->hora_inicio);
            if ($fechaTurno->addDays(7)->isPast()) {
                return ['valido' => false, 'razon' => 'Turno muy antiguo para seguimiento'];
            }
        }

        return ['valido' => true, 'razon' => ''];
    }

    /**
     * Determina la cola según el tipo de recordatorio
     */
    private function determinarCola(string $tipo): string
    {
        return match($tipo) {
            SendAppointmentReminderJob::TIPO_30_MINUTOS => 'high',
            SendAppointmentReminderJob::TIPO_2_HORAS => 'medium',
            default => 'default'
        };
    }

    /**
     * Muestra el encabezado del comando
     */
    private function mostrarEncabezado(): void
    {
        $this->line('');
        $this->line('🏥 <bg=blue;fg=white> SISTEMA DE RECORDATORIOS MÉDICOS </bg=blue;fg=white>');
        $this->line('📅 Ejecutado: ' . now()->format('d/m/Y H:i:s'));
        $this->line('');
    }

    /**
     * Muestra resumen por tipo
     */
    private function mostrarResumenTipo(string $tipo, int $encontrados, int $enviados, int $errores, int $omitidos): void
    {
        $this->line("   📊 Resumen {$tipo}:");
        $this->line("      • Encontrados: {$encontrados}");
        $this->line("      • Enviados: {$enviados}");
        $this->line("      • Errores: {$errores}");
        $this->line("      • Omitidos: {$omitidos}");
    }

    /**
     * Muestra resumen final
     */
    private function mostrarResumen(): void
    {
        $this->line('');
        $this->line('📈 <bg=green;fg=white> RESUMEN FINAL </bg=green;fg=white>');
        $this->line("📋 Total encontrados: {$this->estadisticas['total_encontrados']}");
        $this->line("✅ Enviados exitosos: {$this->estadisticas['enviados_exitosos']}");
        $this->line("❌ Errores: {$this->estadisticas['errores']}");
        $this->line("⚠️  Omitidos: {$this->estadisticas['omitidos']}");

        if ($this->estadisticas['total_encontrados'] > 0) {
            $porcentajeExito = round(($this->estadisticas['enviados_exitosos'] / $this->estadisticas['total_encontrados']) * 100, 2);
            $this->line("📊 Porcentaje de éxito: {$porcentajeExito}%");
        }

        $this->line('');
    }

    /**
     * Registra estadísticas en logs
     */
    private function registrarEstadisticas(): void
    {
        Log::info('Comando de recordatorios ejecutado', [
            'estadisticas' => $this->estadisticas,
            'comando' => 'appointments:send-reminders',
            'opciones' => [
                'type' => $this->option('type'),
                'dry-run' => $this->option('dry-run'),
                'force' => $this->option('force'),
            ],
            'fecha_ejecucion' => now()->toISOString(),
        ]);
    }
}
