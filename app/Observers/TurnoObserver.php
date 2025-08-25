<?php

namespace App\Observers;

use App\Models\Turno;
use App\Models\LogAuditoria;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Observer para el modelo Turno
 * Maneja eventos críticos del sistema de turnos médicos
 */
class TurnoObserver
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Manejar la creación de un turno
     */
    public function created(Turno $turno): void
    {
        try {
            // Log de auditoría
            LogAuditoria::logActivity(
                'created',
                'turnos',
                $turno->id,
                auth()->id(),
                null,
                [
                    'paciente_id' => $turno->paciente_id,
                    'doctor_id' => $turno->doctor_id,
                    'fecha' => $turno->fecha,
                    'hora' => $turno->hora,
                    'estado' => $turno->estado,
                    'motivo' => $turno->motivo,
                ]
            );

            // Notificación automática al paciente
            if ($turno->paciente && $turno->paciente->email) {
                $this->notificationService->enviarConfirmacionTurno($turno);
            }

            // Log para seguimiento
            Log::info('Nuevo turno creado', [
                'turno_id' => $turno->id,
                'paciente' => $turno->paciente?->nombre_completo,
                'doctor' => $turno->doctor?->nombre_completo,
                'fecha_hora' => "{$turno->fecha} {$turno->hora}",
            ]);

            // Verificar conflictos de horario
            $this->verificarConflictos($turno);

        } catch (\Exception $e) {
            Log::error('Error en TurnoObserver::created', [
                'turno_id' => $turno->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar la actualización de un turno
     */
    public function updated(Turno $turno): void
    {
        try {
            $cambios = $turno->getChanges();
            $original = $turno->getOriginal();

            // Log de auditoría
            LogAuditoria::logActivity(
                'updated',
                'turnos',
                $turno->id,
                auth()->id(),
                $original,
                $cambios
            );

            // Notificar cambios importantes
            $this->manejarCambiosImportantes($turno, $cambios, $original);

            // Log detallado de cambios
            Log::info('Turno actualizado', [
                'turno_id' => $turno->id,
                'cambios' => $cambios,
                'usuario' => auth()->user()?->name,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en TurnoObserver::updated', [
                'turno_id' => $turno->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar la eliminación de un turno
     */
    public function deleted(Turno $turno): void
    {
        try {
            // Log de auditoría
            LogAuditoria::logActivity(
                'deleted',
                'turnos',
                $turno->id,
                auth()->id(),
                [
                    'paciente_id' => $turno->paciente_id,
                    'doctor_id' => $turno->doctor_id,
                    'fecha' => $turno->fecha,
                    'hora' => $turno->hora,
                    'estado' => $turno->estado,
                    'motivo' => $turno->motivo,
                ],
                null
            );

            // Notificar cancelación al paciente
            if ($turno->paciente && $turno->paciente->email) {
                $this->notificationService->enviarCancelacionTurno($turno);
            }

            // Log crítico para cancelaciones
            Log::warning('Turno eliminado/cancelado', [
                'turno_id' => $turno->id,
                'paciente' => $turno->paciente?->nombre_completo,
                'doctor' => $turno->doctor?->nombre_completo,
                'fecha_hora' => "{$turno->fecha} {$turno->hora}",
                'usuario' => auth()->user()?->name,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en TurnoObserver::deleted', [
                'turno_id' => $turno->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar la restauración de un turno
     */
    public function restored(Turno $turno): void
    {
        try {
            // Log de auditoría
            LogAuditoria::logActivity(
                'restored',
                'turnos',
                $turno->id,
                auth()->id(),
                null,
                [
                    'paciente_id' => $turno->paciente_id,
                    'doctor_id' => $turno->doctor_id,
                    'fecha' => $turno->fecha,
                    'hora' => $turno->hora,
                    'estado' => $turno->estado,
                ]
            );

            // Verificar si aún es válido restaurar
            $this->verificarValidezRestauracion($turno);

            Log::info('Turno restaurado', [
                'turno_id' => $turno->id,
                'usuario' => auth()->user()?->name,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en TurnoObserver::restored', [
                'turno_id' => $turno->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Verificar conflictos de horario con otros turnos
     */
    private function verificarConflictos(Turno $turno): void
    {
        $conflictos = Turno::where('doctor_id', $turno->doctor_id)
            ->where('fecha', $turno->fecha)
            ->where('hora', $turno->hora)
            ->where('id', '!=', $turno->id)
            ->where('estado', '!=', Turno::ESTADO_CANCELADO)
            ->count();

        if ($conflictos > 0) {
            Log::warning('Conflicto de horario detectado', [
                'turno_id' => $turno->id,
                'doctor_id' => $turno->doctor_id,
                'fecha' => $turno->fecha,
                'hora' => $turno->hora,
                'conflictos_count' => $conflictos,
            ]);

            // Crear alerta para administradores
            LogAuditoria::logActivity(
                'alert',
                'turnos',
                $turno->id,
                auth()->id(),
                null,
                [
                    'tipo' => 'conflicto_horario',
                    'mensaje' => "Posible conflicto de horario para doctor {$turno->doctor_id}",
                    'conflictos' => $conflictos,
                ]
            );
        }
    }

    /**
     * Manejar cambios importantes en el turno
     */
    private function manejarCambiosImportantes(Turno $turno, array $cambios, array $original): void
    {
        $cambiosImportantes = ['fecha', 'hora', 'doctor_id', 'estado'];
        $hayCambiosImportantes = array_intersect_key($cambios, array_flip($cambiosImportantes));

        if (!empty($hayCambiosImportantes)) {
            // Notificar al paciente sobre cambios importantes
            if ($turno->paciente && $turno->paciente->email) {
                $this->notificationService->enviarCambioTurno($turno, $hayCambiosImportantes);
            }

            // Log especial para cambios críticos
            Log::warning('Cambios importantes en turno', [
                'turno_id' => $turno->id,
                'cambios_importantes' => $hayCambiosImportantes,
                'usuario' => auth()->user()?->name,
            ]);
        }

        // Manejar cambio de estado específico
        if (isset($cambios['estado'])) {
            $this->manejarCambioEstado($turno, $original['estado'], $cambios['estado']);
        }
    }

    /**
     * Manejar cambios de estado del turno
     */
    private function manejarCambioEstado(Turno $turno, string $estadoAnterior, string $estadoNuevo): void
    {
        $transicionesImportantes = [
            Turno::ESTADO_PROGRAMADO => Turno::ESTADO_CONFIRMADO,
            Turno::ESTADO_CONFIRMADO => Turno::ESTADO_EN_CURSO,
            Turno::ESTADO_EN_CURSO => Turno::ESTADO_COMPLETADO,
            Turno::ESTADO_PROGRAMADO => Turno::ESTADO_CANCELADO,
            Turno::ESTADO_CONFIRMADO => Turno::ESTADO_CANCELADO,
        ];

        $transicion = "{$estadoAnterior}->{$estadoNuevo}";

        // Log específico por tipo de transición
        switch ($estadoNuevo) {
            case Turno::ESTADO_CONFIRMADO:
                Log::info('Turno confirmado', [
                    'turno_id' => $turno->id,
                    'paciente' => $turno->paciente?->nombre_completo,
                    'fecha_hora' => "{$turno->fecha} {$turno->hora}",
                ]);
                break;

            case Turno::ESTADO_COMPLETADO:
                Log::info('Turno completado', [
                    'turno_id' => $turno->id,
                    'duracion_estimada' => $this->calcularDuracion($turno),
                ]);
                break;

            case Turno::ESTADO_CANCELADO:
                Log::warning('Turno cancelado', [
                    'turno_id' => $turno->id,
                    'estado_anterior' => $estadoAnterior,
                    'motivo_cancelacion' => $turno->observaciones,
                ]);
                break;

            case Turno::ESTADO_NO_ASISTIO:
                Log::warning('Paciente no asistió', [
                    'turno_id' => $turno->id,
                    'paciente_id' => $turno->paciente_id,
                    'fecha' => $turno->fecha,
                ]);
                break;
        }
    }

    /**
     * Verificar si es válido restaurar un turno
     */
    private function verificarValidezRestauracion(Turno $turno): void
    {
        $fechaTurno = Carbon::parse("{$turno->fecha} {$turno->hora}");
        
        if ($fechaTurno->isPast()) {
            Log::warning('Intento de restaurar turno pasado', [
                'turno_id' => $turno->id,
                'fecha_turno' => $fechaTurno->toDateTimeString(),
                'usuario' => auth()->user()?->name,
            ]);

            // Crear alerta
            LogAuditoria::logActivity(
                'alert',
                'turnos',
                $turno->id,
                auth()->id(),
                null,
                [
                    'tipo' => 'restauracion_turno_pasado',
                    'mensaje' => 'Se restauró un turno con fecha pasada',
                    'fecha_turno' => $fechaTurno->toDateTimeString(),
                ]
            );
        }
    }

    /**
     * Calcular duración estimada de un turno
     */
    private function calcularDuracion(Turno $turno): ?string
    {
        // Esta lógica podría mejorarse con timestamps reales de inicio/fin
        return '30 minutos'; // Valor por defecto
    }
}
