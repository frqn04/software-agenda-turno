<?php

namespace App\Services;

use App\Models\Turno;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Enviar notificación de turno creado
     */
    public function notifyAppointmentCreated(Turno $turno): void
    {
        try {
            // Notificar al paciente
            if ($turno->paciente->email) {
                $this->sendAppointmentConfirmation($turno);
            }

            // Notificar al doctor
            if ($turno->doctor->user && $turno->doctor->user->email) {
                $this->sendDoctorNotification($turno);
            }

            // Log de auditoría
            Log::info('Notificaciones de turno enviadas', [
                'turno_id' => $turno->id,
                'paciente_id' => $turno->paciente_id,
                'doctor_id' => $turno->doctor_id,
            ]);

        } catch (\Exception $e) {
            Log::error('Error enviando notificaciones de turno', [
                'turno_id' => $turno->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Enviar recordatorio de turno
     */
    public function sendAppointmentReminder(Turno $turno): void
    {
        try {
            if ($turno->paciente->email) {
                // Aquí iría la lógica de envío de email de recordatorio
                Log::info('Recordatorio de turno enviado', [
                    'turno_id' => $turno->id,
                    'paciente_email' => $turno->paciente->email,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error enviando recordatorio de turno', [
                'turno_id' => $turno->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notificar cancelación de turno
     */
    public function notifyAppointmentCancelled(Turno $turno, string $reason = ''): void
    {
        try {
            // Notificar al paciente
            if ($turno->paciente->email) {
                Log::info('Notificación de cancelación enviada al paciente', [
                    'turno_id' => $turno->id,
                    'paciente_email' => $turno->paciente->email,
                    'reason' => $reason,
                ]);
            }

            // Notificar al doctor
            if ($turno->doctor->user && $turno->doctor->user->email) {
                Log::info('Notificación de cancelación enviada al doctor', [
                    'turno_id' => $turno->id,
                    'doctor_email' => $turno->doctor->user->email,
                    'reason' => $reason,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error enviando notificaciones de cancelación', [
                'turno_id' => $turno->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendAppointmentConfirmation(Turno $turno): void
    {
        // Aquí iría la lógica de envío de email de confirmación
        Log::info('Confirmación de turno enviada', [
            'turno_id' => $turno->id,
            'paciente_email' => $turno->paciente->email,
        ]);
    }

    private function sendDoctorNotification(Turno $turno): void
    {
        // Aquí iría la lógica de notificación al doctor
        Log::info('Notificación enviada al doctor', [
            'turno_id' => $turno->id,
            'doctor_email' => $turno->doctor->user->email,
        ]);
    }
}
