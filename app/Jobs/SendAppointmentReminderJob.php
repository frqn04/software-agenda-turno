<?php

namespace App\Jobs;

use App\Models\Turno;
use App\Models\LogAuditoria;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

/**
 * Job para enviar recordatorios de citas médicas
 * Maneja múltiples tipos de recordatorios con reintentos inteligentes
 */
class SendAppointmentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Configuración del job
    public $timeout = 120; // 2 minutos
    public $tries = 3; // 3 intentos
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

    // Tipos de recordatorio
    const TIPO_24_HORAS = '24_horas';
    const TIPO_2_HORAS = '2_horas';
    const TIPO_30_MINUTOS = '30_minutos';
    const TIPO_CONFIRMACION = 'confirmacion';
    const TIPO_SEGUIMIENTO = 'seguimiento';

    private Turno $turno;
    private string $tipoRecordatorio;
    private array $configuracion;

    /**
     * Create a new job instance.
     */
    public function __construct(
        Turno $turno, 
        string $tipoRecordatorio = self::TIPO_24_HORAS,
        array $configuracion = []
    ) {
        $this->turno = $turno->withoutRelations(); // Evitar serialización de relaciones
        $this->tipoRecordatorio = $tipoRecordatorio;
        $this->configuracion = array_merge($this->getConfiguracionDefecto(), $configuracion);
        
        // Configurar cola según prioridad
        $this->onQueue($this->determinarCola());
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            // Recargar el turno desde la base de datos
            $this->turno = $this->turno->fresh(['paciente', 'doctor', 'doctor.especialidad']);
            
            if (!$this->turno) {
                Log::warning('Turno no encontrado para recordatorio', [
                    'turno_id' => $this->turno->id ?? 'unknown'
                ]);
                return;
            }

            // Verificar si el recordatorio debe ejecutarse
            if (!$this->debeEjecutarRecordatorio()) {
                return;
            }

            // Validar datos del paciente
            if (!$this->validarDatosPaciente()) {
                $this->registrarError('Datos de contacto del paciente inválidos');
                return;
            }

            // Ejecutar el recordatorio según el tipo
            $resultado = $this->ejecutarRecordatorio($notificationService);

            if ($resultado['exitoso']) {
                $this->registrarExito($resultado);
            } else {
                $this->registrarError($resultado['error']);
            }

        } catch (Exception $e) {
            $this->manejarError($e);
        }
    }

    /**
     * Determina si el recordatorio debe ejecutarse
     */
    private function debeEjecutarRecordatorio(): bool
    {
        // Verificar estado del turno
        if ($this->turno->estado === Turno::ESTADO_CANCELADO) {
            Log::info('Recordatorio cancelado - turno cancelado', [
                'turno_id' => $this->turno->id,
                'tipo' => $this->tipoRecordatorio
            ]);
            return false;
        }

        if ($this->turno->estado === Turno::ESTADO_REALIZADO) {
            Log::info('Recordatorio cancelado - turno ya realizado', [
                'turno_id' => $this->turno->id,
                'tipo' => $this->tipoRecordatorio
            ]);
            return false;
        }

        // Verificar timing según tipo de recordatorio
        if (!$this->verificarTiming()) {
            return false;
        }

        // Verificar que no se haya enviado ya este tipo de recordatorio
        if ($this->yaSeEnvioRecordatorio()) {
            Log::info('Recordatorio ya enviado anteriormente', [
                'turno_id' => $this->turno->id,
                'tipo' => $this->tipoRecordatorio
            ]);
            return false;
        }

        return true;
    }

    /**
     * Verifica el timing correcto según el tipo de recordatorio
     */
    private function verificarTiming(): bool
    {
        $fechaHoraTurno = Carbon::parse($this->turno->fecha->format('Y-m-d') . ' ' . $this->turno->hora_inicio);
        $horasHasta = now()->diffInHours($fechaHoraTurno, false);
        $minutosHasta = now()->diffInMinutes($fechaHoraTurno, false);

        switch ($this->tipoRecordatorio) {
            case self::TIPO_24_HORAS:
                return $horasHasta >= 23 && $horasHasta <= 25;
                
            case self::TIPO_2_HORAS:
                return $horasHasta >= 1.5 && $horasHasta <= 2.5;
                
            case self::TIPO_30_MINUTOS:
                return $minutosHasta >= 25 && $minutosHasta <= 35;
                
            case self::TIPO_CONFIRMACION:
                return $horasHasta >= 47 && $horasHasta <= 49; // 48 horas antes
                
            case self::TIPO_SEGUIMIENTO:
                return $horasHasta <= -24; // 24 horas después
                
            default:
                return true;
        }
    }

    /**
     * Valida que el paciente tenga datos de contacto válidos
     */
    private function validarDatosPaciente(): bool
    {
        $paciente = $this->turno->paciente;
        
        if (!$paciente) {
            return false;
        }

        // Verificar al menos un método de contacto
        $tieneEmail = !empty($paciente->email) && filter_var($paciente->email, FILTER_VALIDATE_EMAIL);
        $tieneTelefono = !empty($paciente->telefono);
        $tieneWhatsapp = !empty($paciente->telefono) && $this->configuracion['whatsapp_habilitado'];

        return $tieneEmail || $tieneTelefono || $tieneWhatsapp;
    }

    /**
     * Ejecuta el recordatorio según el tipo
     */
    private function ejecutarRecordatorio(NotificationService $notificationService): array
    {
        try {
            switch ($this->tipoRecordatorio) {
                case self::TIPO_24_HORAS:
                    return $this->enviarRecordatorio24Horas($notificationService);
                    
                case self::TIPO_2_HORAS:
                    return $this->enviarRecordatorio2Horas($notificationService);
                    
                case self::TIPO_30_MINUTOS:
                    return $this->enviarRecordatorio30Minutos($notificationService);
                    
                case self::TIPO_CONFIRMACION:
                    return $this->enviarSolicitudConfirmacion($notificationService);
                    
                case self::TIPO_SEGUIMIENTO:
                    return $this->enviarSeguimiento($notificationService);
                    
                default:
                    return ['exitoso' => false, 'error' => 'Tipo de recordatorio no válido'];
            }
        } catch (Exception $e) {
            return ['exitoso' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Envía recordatorio 24 horas antes
     */
    private function enviarRecordatorio24Horas(NotificationService $notificationService): array
    {
        $datos = $this->prepararDatosRecordatorio([
            'titulo' => 'Recordatorio de Cita Médica',
            'mensaje' => 'Le recordamos que tiene una cita médica programada para mañana.',
            'incluir_preparacion' => true,
            'incluir_documentos' => true,
        ]);

        $resultado = $notificationService->sendAppointmentReminder($this->turno, $datos);
        
        return [
            'exitoso' => $resultado,
            'canales_utilizados' => $this->determinarCanalesNotificacion(),
            'datos_enviados' => $datos
        ];
    }

    /**
     * Envía recordatorio 2 horas antes
     */
    private function enviarRecordatorio2Horas(NotificationService $notificationService): array
    {
        $datos = $this->prepararDatosRecordatorio([
            'titulo' => 'Su cita es en 2 horas',
            'mensaje' => 'Su cita médica está programada en aproximadamente 2 horas.',
            'urgente' => true,
            'incluir_ubicacion' => true,
        ]);

        $resultado = $notificationService->sendUrgentReminder($this->turno, $datos);
        
        return [
            'exitoso' => $resultado,
            'canales_utilizados' => ['sms', 'whatsapp'],
            'datos_enviados' => $datos
        ];
    }

    /**
     * Envía recordatorio 30 minutos antes
     */
    private function enviarRecordatorio30Minutos(NotificationService $notificationService): array
    {
        $datos = $this->prepararDatosRecordatorio([
            'titulo' => '¡Su cita es en 30 minutos!',
            'mensaje' => 'Su cita médica está por comenzar. Por favor diríjase al consultorio.',
            'urgente' => true,
            'incluir_ubicacion' => true,
            'incluir_contacto_emergencia' => true,
        ]);

        $resultado = $notificationService->sendUrgentReminder($this->turno, $datos);
        
        return [
            'exitoso' => $resultado,
            'canales_utilizados' => ['sms', 'whatsapp', 'push'],
            'datos_enviados' => $datos
        ];
    }

    /**
     * Envía solicitud de confirmación
     */
    private function enviarSolicitudConfirmacion(NotificationService $notificationService): array
    {
        $datos = $this->prepararDatosRecordatorio([
            'titulo' => 'Confirme su Cita Médica',
            'mensaje' => 'Por favor confirme su asistencia a la cita médica programada.',
            'requiere_confirmacion' => true,
            'incluir_opciones_reprogramacion' => true,
        ]);

        $resultado = $notificationService->sendConfirmationRequest($this->turno, $datos);
        
        return [
            'exitoso' => $resultado,
            'canales_utilizados' => ['email', 'sms'],
            'datos_enviados' => $datos
        ];
    }

    /**
     * Envía seguimiento post-cita
     */
    private function enviarSeguimiento(NotificationService $notificationService): array
    {
        $datos = $this->prepararDatosRecordatorio([
            'titulo' => 'Seguimiento de su Consulta',
            'mensaje' => 'Esperamos que su consulta haya sido satisfactoria.',
            'incluir_encuesta' => true,
            'incluir_proxima_cita' => true,
        ]);

        $resultado = $notificationService->sendFollowUp($this->turno, $datos);
        
        return [
            'exitoso' => $resultado,
            'canales_utilizados' => ['email'],
            'datos_enviados' => $datos
        ];
    }

    /**
     * Prepara los datos del recordatorio
     */
    private function prepararDatosRecordatorio(array $configuracionEspecifica = []): array
    {
        $fechaHoraTurno = Carbon::parse($this->turno->fecha->format('Y-m-d') . ' ' . $this->turno->hora_inicio);
        
        return array_merge([
            'paciente' => $this->turno->paciente,
            'doctor' => $this->turno->doctor,
            'especialidad' => $this->turno->doctor->especialidad,
            'fecha' => $fechaHoraTurno->format('d/m/Y'),
            'hora' => $fechaHoraTurno->format('H:i'),
            'fecha_completa' => $fechaHoraTurno->format('d/m/Y H:i'),
            'motivo' => $this->turno->motivo,
            'ubicacion' => 'Consultorio Médico', // Configurar en config/app.php
            'telefono_contacto' => '', // Configurar en config/app.php
            'tipo_recordatorio' => $this->tipoRecordatorio,
        ], $configuracionEspecifica);
    }

    /**
     * Determina los canales de notificación a usar
     */
    private function determinarCanalesNotificacion(): array
    {
        $canales = [];
        $paciente = $this->turno->paciente;
        
        if ($paciente->email && $this->configuracion['email_habilitado']) {
            $canales[] = 'email';
        }
        
        if ($paciente->telefono && $this->configuracion['sms_habilitado']) {
            $canales[] = 'sms';
        }
        
        if ($paciente->telefono && $this->configuracion['whatsapp_habilitado']) {
            $canales[] = 'whatsapp';
        }
        
        return $canales;
    }

    /**
     * Verifica si ya se envió este tipo de recordatorio
     */
    private function yaSeEnvioRecordatorio(): bool
    {
        return LogAuditoria::where('tabla', 'turnos')
            ->where('registro_id', $this->turno->id)
            ->where('accion', 'recordatorio_enviado')
            ->whereJsonContains('valores_nuevos->tipo_recordatorio', $this->tipoRecordatorio)
            ->exists();
    }

    /**
     * Registra el éxito del recordatorio
     */
    private function registrarExito(array $resultado): void
    {
        LogAuditoria::create([
            'usuario_id' => null,
            'tabla' => 'turnos',
            'registro_id' => $this->turno->id,
            'accion' => 'recordatorio_enviado',
            'valores_nuevos' => [
                'tipo_recordatorio' => $this->tipoRecordatorio,
                'canales_utilizados' => $resultado['canales_utilizados'] ?? [],
                'exitoso' => true,
                'fecha_envio' => now()->toISOString(),
            ],
            'ip' => '127.0.0.1',
            'user_agent' => 'System Job',
        ]);

        Log::info('Recordatorio de turno enviado exitosamente', [
            'turno_id' => $this->turno->id,
            'tipo' => $this->tipoRecordatorio,
            'canales' => $resultado['canales_utilizados'] ?? [],
            'paciente_id' => $this->turno->paciente_id,
        ]);
    }

    /**
     * Registra el error del recordatorio
     */
    private function registrarError(string $error): void
    {
        LogAuditoria::create([
            'usuario_id' => null,
            'tabla' => 'turnos',
            'registro_id' => $this->turno->id,
            'accion' => 'recordatorio_fallido',
            'valores_nuevos' => [
                'tipo_recordatorio' => $this->tipoRecordatorio,
                'error' => $error,
                'intento' => $this->attempts(),
                'fecha_error' => now()->toISOString(),
            ],
            'ip' => '127.0.0.1',
            'user_agent' => 'System Job',
        ]);

        Log::error('Error en recordatorio de turno', [
            'turno_id' => $this->turno->id,
            'tipo' => $this->tipoRecordatorio,
            'error' => $error,
            'intento' => $this->attempts(),
        ]);
    }

    /**
     * Maneja errores del job
     */
    private function manejarError(Exception $e): void
    {
        $this->registrarError($e->getMessage());

        // Si es el último intento, marcar como fallido
        if ($this->attempts() >= $this->tries) {
            $this->fail($e);
        } else {
            // Relanzar la excepción para que el job se reintente
            throw $e;
        }
    }

    /**
     * Determina la cola según la prioridad del recordatorio
     */
    private function determinarCola(): string
    {
        return match($this->tipoRecordatorio) {
            self::TIPO_30_MINUTOS => 'high',
            self::TIPO_2_HORAS => 'medium',
            default => 'default'
        };
    }

    /**
     * Configuración por defecto
     */
    private function getConfiguracionDefecto(): array
    {
        return [
            'email_habilitado' => config('mail.default') !== null,
            'sms_habilitado' => false, // Configurar según servicio SMS disponible
            'whatsapp_habilitado' => false, // Configurar según servicio WhatsApp disponible
            'push_habilitado' => false, // Configurar según servicio Push disponible
            'reintentos_maximos' => 3,
            'tiempo_entre_reintentos' => [60, 300, 900], // segundos
            'notificar_fallos_admin' => config('app.debug', false),
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Job de recordatorio falló completamente', [
            'turno_id' => $this->turno->id,
            'tipo' => $this->tipoRecordatorio,
            'error' => $exception->getMessage(),
            'intentos_realizados' => $this->attempts(),
        ]);

        // Registrar fallo final en auditoría
        LogAuditoria::create([
            'usuario_id' => null,
            'tabla' => 'turnos',
            'registro_id' => $this->turno->id,
            'accion' => 'recordatorio_fallido_final',
            'valores_nuevos' => [
                'tipo_recordatorio' => $this->tipoRecordatorio,
                'error_final' => $exception->getMessage(),
                'intentos_realizados' => $this->attempts(),
                'fecha_fallo_final' => now()->toISOString(),
            ],
            'ip' => '127.0.0.1',
            'user_agent' => 'System Job',
        ]);

        // Opcionalmente, notificar al equipo médico del fallo
        if ($this->configuracion['notificar_fallos_admin'] ?? true) {
            // Aquí se podría enviar una notificación al administrador
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return $this->backoff;
    }

    /**
     * Determine if the job should be retried.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(6); // Reintentar por máximo 6 horas
    }
}
