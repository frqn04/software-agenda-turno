<?php

namespace App\Jobs;

use App\Models\Turno;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAppointmentReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Turno $turno
    ) {}

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            // Verificar que el turno sigue activo
            if ($this->turno->estado === 'cancelado') {
                Log::info('Recordatorio cancelado - turno cancelado', [
                    'turno_id' => $this->turno->id
                ]);
                return;
            }

            // Verificar que falta exactamente 24 horas
            $hoursUntilAppointment = now()->diffInHours($this->turno->fecha_hora);
            
            if ($hoursUntilAppointment >= 23 && $hoursUntilAppointment <= 25) {
                $notificationService->sendAppointmentReminder($this->turno);
                
                Log::info('Recordatorio de turno procesado', [
                    'turno_id' => $this->turno->id,
                    'hours_until' => $hoursUntilAppointment,
                ]);
            } else {
                Log::warning('Recordatorio ejecutado fuera de tiempo', [
                    'turno_id' => $this->turno->id,
                    'hours_until' => $hoursUntilAppointment,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error procesando recordatorio de turno', [
                'turno_id' => $this->turno->id,
                'error' => $e->getMessage(),
            ]);

            // Reintentar el job
            $this->fail($e);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Job de recordatorio falló completamente', [
            'turno_id' => $this->turno->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
