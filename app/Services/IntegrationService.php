<?php

namespace App\Services;

use App\Models\Turno;
use App\Models\Paciente;
use App\Models\Doctor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Carbon\Carbon;

/**
 * Servicio de integración externa
 * Maneja integraciones con sistemas externos, APIs de terceros y notificaciones
 */
class IntegrationService
{
    private const SMS_PROVIDER_URL = 'https://api.sms-provider.com/v1/send';
    private const EMAIL_PROVIDER_URL = 'https://api.email-provider.com/v1/send';
    private const WHATSAPP_PROVIDER_URL = 'https://api.whatsapp-business.com/v1/messages';

    /**
     * Enviar notificación multicanal
     */
    public function sendMultiChannelNotification(
        int $pacienteId,
        string $message,
        array $channels = ['email', 'sms'],
        array $context = []
    ): array {
        $paciente = Paciente::find($pacienteId);
        
        if (!$paciente) {
            throw new \InvalidArgumentException('Paciente no encontrado');
        }

        $results = [];
        
        foreach ($channels as $channel) {
            try {
                switch ($channel) {
                    case 'email':
                        $results['email'] = $this->sendEmailNotification($paciente, $message, $context);
                        break;
                    case 'sms':
                        $results['sms'] = $this->sendSMSNotification($paciente, $message, $context);
                        break;
                    case 'whatsapp':
                        $results['whatsapp'] = $this->sendWhatsAppNotification($paciente, $message, $context);
                        break;
                    case 'push':
                        $results['push'] = $this->sendPushNotification($paciente, $message, $context);
                        break;
                }
            } catch (\Exception $e) {
                $results[$channel] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
                
                Log::error("Failed to send {$channel} notification", [
                    'paciente_id' => $pacienteId,
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Log de auditoría
        AuditService::logActivity(
            'notification_sent',
            'notifications',
            null,
            null,
            null,
            [
                'paciente_id' => $pacienteId,
                'channels' => $channels,
                'results' => $results,
                'message_preview' => substr($message, 0, 100),
            ]
        );

        return $results;
    }

    /**
     * Programar recordatorios automáticos para turnos
     */
    public function scheduleAppointmentReminders(Turno $turno): array
    {
        $scheduledJobs = [];
        
        // Recordatorio 24 horas antes
        $reminder24h = Carbon::parse($turno->fecha . ' ' . $turno->hora_inicio)->subDay();
        if ($reminder24h->isFuture()) {
            $jobId = Queue::later($reminder24h, function () use ($turno) {
                $this->sendAppointmentReminder($turno, '24_hours');
            });
            $scheduledJobs['24_hours'] = $jobId;
        }

        // Recordatorio 2 horas antes
        $reminder2h = Carbon::parse($turno->fecha . ' ' . $turno->hora_inicio)->subHours(2);
        if ($reminder2h->isFuture()) {
            $jobId = Queue::later($reminder2h, function () use ($turno) {
                $this->sendAppointmentReminder($turno, '2_hours');
            });
            $scheduledJobs['2_hours'] = $jobId;
        }

        // Recordatorio 30 minutos antes (solo SMS/WhatsApp)
        $reminder30m = Carbon::parse($turno->fecha . ' ' . $turno->hora_inicio)->subMinutes(30);
        if ($reminder30m->isFuture()) {
            $jobId = Queue::later($reminder30m, function () use ($turno) {
                $this->sendAppointmentReminder($turno, '30_minutes', ['sms', 'whatsapp']);
            });
            $scheduledJobs['30_minutes'] = $jobId;
        }

        // Guardar IDs de trabajos programados en el turno
        $turno->update([
            'scheduled_reminders' => json_encode($scheduledJobs),
        ]);

        return $scheduledJobs;
    }

    /**
     * Integración con sistema de facturación
     */
    public function createInvoice(Turno $turno, array $additionalData = []): array
    {
        try {
            $invoiceData = [
                'turno_id' => $turno->id,
                'paciente_id' => $turno->paciente_id,
                'doctor_id' => $turno->doctor_id,
                'fecha_servicio' => $turno->fecha,
                'hora_servicio' => $turno->hora_inicio,
                'especialidad' => $turno->doctor->especialidad->nombre ?? 'General',
                'paciente_datos' => [
                    'nombre' => $turno->paciente->nombre,
                    'apellido' => $turno->paciente->apellido,
                    'dni' => $turno->paciente->dni,
                    'email' => $turno->paciente->email,
                ],
                'doctor_datos' => [
                    'nombre' => $turno->doctor->nombre,
                    'apellido' => $turno->doctor->apellido,
                    'matricula' => $turno->doctor->matricula,
                ],
                'monto' => $this->calculateAppointmentCost($turno),
                'moneda' => 'ARS',
                'timestamp' => now()->toISOString(),
            ];

            // Agregar datos adicionales
            $invoiceData = array_merge($invoiceData, $additionalData);

            // Enviar a sistema de facturación externo
            $response = Http::timeout(30)
                          ->withHeaders([
                              'Authorization' => 'Bearer ' . config('services.billing.api_key'),
                              'Content-Type' => 'application/json',
                          ])
                          ->post(config('services.billing.url') . '/invoices', $invoiceData);

            if ($response->successful()) {
                $invoiceResponse = $response->json();
                
                // Actualizar turno con datos de facturación
                $turno->update([
                    'invoice_id' => $invoiceResponse['invoice_id'] ?? null,
                    'invoice_number' => $invoiceResponse['invoice_number'] ?? null,
                    'invoice_status' => 'generated',
                ]);

                return [
                    'success' => true,
                    'invoice_id' => $invoiceResponse['invoice_id'],
                    'invoice_number' => $invoiceResponse['invoice_number'],
                    'invoice_url' => $invoiceResponse['invoice_url'] ?? null,
                ];
            } else {
                throw new \Exception('Error en sistema de facturación: ' . $response->body());
            }

        } catch (\Exception $e) {
            Log::error('Invoice creation failed', [
                'turno_id' => $turno->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sincronizar con sistema de historia clínica externa
     */
    public function syncMedicalRecord(int $pacienteId, array $recordData): array
    {
        try {
            $paciente = Paciente::findOrFail($pacienteId);
            
            $syncData = [
                'patient_external_id' => $paciente->external_id ?? $paciente->dni,
                'patient_data' => [
                    'nombre' => $paciente->nombre,
                    'apellido' => $paciente->apellido,
                    'dni' => $paciente->dni,
                    'fecha_nacimiento' => $paciente->fecha_nacimiento,
                    'genero' => $paciente->genero,
                    'telefono' => $paciente->telefono,
                    'email' => $paciente->email,
                ],
                'medical_record' => $recordData,
                'sync_timestamp' => now()->toISOString(),
                'source_system' => config('app.name'),
            ];

            $response = Http::timeout(30)
                          ->withHeaders([
                              'Authorization' => 'Bearer ' . config('services.medical_records.api_key'),
                              'Content-Type' => 'application/json',
                          ])
                          ->post(config('services.medical_records.url') . '/records/sync', $syncData);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'external_record_id' => $response->json()['record_id'] ?? null,
                    'sync_status' => 'completed',
                ];
            } else {
                throw new \Exception('Error en sincronización: ' . $response->body());
            }

        } catch (\Exception $e) {
            Log::error('Medical record sync failed', [
                'paciente_id' => $pacienteId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Integración con Google Calendar para doctores
     */
    public function syncWithGoogleCalendar(int $doctorId, Turno $turno, string $action = 'create'): array
    {
        try {
            $doctor = Doctor::findOrFail($doctorId);
            
            if (!$doctor->google_calendar_id) {
                return [
                    'success' => false,
                    'error' => 'Doctor no tiene Google Calendar configurado',
                ];
            }

            $eventData = [
                'summary' => "Consulta - {$turno->paciente->nombre} {$turno->paciente->apellido}",
                'description' => "Motivo: {$turno->motivo_consulta}\nPaciente: {$turno->paciente->nombre} {$turno->paciente->apellido}\nTeléfono: {$turno->paciente->telefono}",
                'start' => [
                    'dateTime' => Carbon::parse($turno->fecha . ' ' . $turno->hora_inicio)->toISOString(),
                    'timeZone' => 'America/Argentina/Buenos_Aires',
                ],
                'end' => [
                    'dateTime' => Carbon::parse($turno->fecha . ' ' . $turno->hora_fin)->toISOString(),
                    'timeZone' => 'America/Argentina/Buenos_Aires',
                ],
                'attendees' => [
                    ['email' => $turno->paciente->email ?? ''],
                ],
                'reminders' => [
                    'useDefault' => false,
                    'overrides' => [
                        ['method' => 'email', 'minutes' => 1440], // 24 horas
                        ['method' => 'popup', 'minutes' => 120],  // 2 horas
                    ],
                ],
            ];

            $url = "https://www.googleapis.com/calendar/v3/calendars/{$doctor->google_calendar_id}/events";
            $method = 'POST';

            if ($action === 'update' && $turno->google_event_id) {
                $url .= "/{$turno->google_event_id}";
                $method = 'PUT';
            } elseif ($action === 'delete' && $turno->google_event_id) {
                $url .= "/{$turno->google_event_id}";
                $method = 'DELETE';
            }

            $response = Http::timeout(30)
                          ->withHeaders([
                              'Authorization' => 'Bearer ' . $doctor->google_access_token,
                              'Content-Type' => 'application/json',
                          ])
                          ->send($method, $url, $method === 'DELETE' ? [] : $eventData);

            if ($response->successful()) {
                if ($action !== 'delete') {
                    $eventResponse = $response->json();
                    $turno->update(['google_event_id' => $eventResponse['id']]);
                } else {
                    $turno->update(['google_event_id' => null]);
                }

                return [
                    'success' => true,
                    'event_id' => $action !== 'delete' ? $response->json()['id'] : null,
                    'event_url' => $action !== 'delete' ? $response->json()['htmlLink'] : null,
                ];
            } else {
                throw new \Exception('Error en Google Calendar: ' . $response->body());
            }

        } catch (\Exception $e) {
            Log::error('Google Calendar sync failed', [
                'doctor_id' => $doctorId,
                'turno_id' => $turno->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Webhook para recibir notificaciones de sistemas externos
     */
    public function handleWebhook(string $source, array $payload): array
    {
        try {
            switch ($source) {
                case 'payment_system':
                    return $this->handlePaymentWebhook($payload);
                case 'insurance_system':
                    return $this->handleInsuranceWebhook($payload);
                case 'laboratory_system':
                    return $this->handleLaboratoryWebhook($payload);
                default:
                    throw new \Exception("Unknown webhook source: {$source}");
            }
        } catch (\Exception $e) {
            Log::error('Webhook handling failed', [
                'source' => $source,
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // Métodos privados

    private function sendEmailNotification(Paciente $paciente, string $message, array $context): array
    {
        if (!$paciente->email) {
            return ['success' => false, 'error' => 'Paciente sin email'];
        }

        $emailData = [
            'to' => $paciente->email,
            'subject' => $context['subject'] ?? 'Notificación del Centro Médico',
            'html' => $this->formatEmailMessage($message, $context),
            'from' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ];

        $response = Http::timeout(30)
                      ->withHeaders(['Authorization' => 'Bearer ' . config('services.email.api_key')])
                      ->post(self::EMAIL_PROVIDER_URL, $emailData);

        return [
            'success' => $response->successful(),
            'message_id' => $response->json()['message_id'] ?? null,
            'error' => $response->successful() ? null : $response->body(),
        ];
    }

    private function sendSMSNotification(Paciente $paciente, string $message, array $context): array
    {
        if (!$paciente->telefono) {
            return ['success' => false, 'error' => 'Paciente sin teléfono'];
        }

        $smsData = [
            'to' => $this->formatPhoneNumber($paciente->telefono),
            'message' => $this->formatSMSMessage($message, $context),
            'from' => config('services.sms.sender_id'),
        ];

        $response = Http::timeout(30)
                      ->withHeaders(['Authorization' => 'Bearer ' . config('services.sms.api_key')])
                      ->post(self::SMS_PROVIDER_URL, $smsData);

        return [
            'success' => $response->successful(),
            'message_id' => $response->json()['message_id'] ?? null,
            'error' => $response->successful() ? null : $response->body(),
        ];
    }

    private function sendWhatsAppNotification(Paciente $paciente, string $message, array $context): array
    {
        if (!$paciente->telefono) {
            return ['success' => false, 'error' => 'Paciente sin teléfono'];
        }

        $whatsappData = [
            'messaging_product' => 'whatsapp',
            'to' => $this->formatPhoneNumber($paciente->telefono),
            'type' => 'text',
            'text' => [
                'body' => $this->formatWhatsAppMessage($message, $context)
            ]
        ];

        $response = Http::timeout(30)
                      ->withHeaders([
                          'Authorization' => 'Bearer ' . config('services.whatsapp.access_token'),
                          'Content-Type' => 'application/json',
                      ])
                      ->post(self::WHATSAPP_PROVIDER_URL, $whatsappData);

        return [
            'success' => $response->successful(),
            'message_id' => $response->json()['messages'][0]['id'] ?? null,
            'error' => $response->successful() ? null : $response->body(),
        ];
    }

    private function sendPushNotification(Paciente $paciente, string $message, array $context): array
    {
        // Implementación de push notifications
        // Esto dependería del proveedor (Firebase, etc.)
        return [
            'success' => false,
            'error' => 'Push notifications not implemented yet',
        ];
    }

    private function sendAppointmentReminder(Turno $turno, string $timing, array $channels = ['email', 'sms']): void
    {
        $messages = [
            '24_hours' => "Recordatorio: Tienes una cita médica mañana {$turno->fecha} a las {$turno->hora_inicio} con Dr. {$turno->doctor->nombre} {$turno->doctor->apellido}.",
            '2_hours' => "Tu cita médica es en 2 horas ({$turno->hora_inicio}) con Dr. {$turno->doctor->nombre} {$turno->doctor->apellido}. Por favor, llega 10 minutos antes.",
            '30_minutes' => "Tu cita médica es en 30 minutos. Dr. {$turno->doctor->nombre} {$turno->doctor->apellido} te espera.",
        ];

        $this->sendMultiChannelNotification(
            $turno->paciente_id,
            $messages[$timing] ?? $messages['2_hours'],
            $channels,
            [
                'type' => 'appointment_reminder',
                'timing' => $timing,
                'turno_id' => $turno->id,
                'subject' => 'Recordatorio de Cita Médica',
            ]
        );
    }

    private function calculateAppointmentCost(Turno $turno): float
    {
        // Lógica de cálculo de costo basada en especialidad, duración, etc.
        $baseCost = 12000; // Costo base en pesos argentinos
        
        $specialtyMultipliers = [
            'Cardiología' => 1.5,
            'Neurología' => 1.8,
            'Dermatología' => 1.2,
            'Pediatría' => 1.0,
            'Ginecología' => 1.3,
        ];

        $especialidad = $turno->doctor->especialidad->nombre ?? 'General';
        $multiplier = $specialtyMultipliers[$especialidad] ?? 1.0;

        return round($baseCost * $multiplier, 2);
    }

    private function formatPhoneNumber(string $phone): string
    {
        // Formatear número telefónico argentino para APIs internacionales
        $clean = preg_replace('/[^\d]/', '', $phone);
        
        if (substr($clean, 0, 2) === '54') {
            return '+' . $clean;
        } elseif (substr($clean, 0, 1) === '9') {
            return '+54' . $clean;
        } else {
            return '+549' . $clean;
        }
    }

    private function formatEmailMessage(string $message, array $context): string
    {
        $html = "<html><body>";
        $html .= "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>";
        $html .= "<div style='background-color: #f8f9fa; padding: 20px; text-align: center;'>";
        $html .= "<h2 style='color: #007bff;'>Centro Médico</h2>";
        $html .= "</div>";
        $html .= "<div style='padding: 20px;'>";
        $html .= "<p>" . nl2br(htmlspecialchars($message)) . "</p>";
        $html .= "</div>";
        $html .= "<div style='background-color: #f8f9fa; padding: 10px; text-align: center; font-size: 12px; color: #666;'>";
        $html .= "Este es un mensaje automático. No responder a este email.";
        $html .= "</div>";
        $html .= "</div>";
        $html .= "</body></html>";

        return $html;
    }

    private function formatSMSMessage(string $message, array $context): string
    {
        // Limitar longitud para SMS (160 caracteres)
        $formatted = "Centro Médico: " . $message;
        
        if (strlen($formatted) > 160) {
            $formatted = substr($formatted, 0, 157) . '...';
        }

        return $formatted;
    }

    private function formatWhatsAppMessage(string $message, array $context): string
    {
        return "🏥 *Centro Médico*\n\n" . $message;
    }

    private function handlePaymentWebhook(array $payload): array
    {
        // Manejar notificaciones de pagos
        Log::info('Payment webhook received', $payload);
        return ['success' => true, 'processed' => 'payment_webhook'];
    }

    private function handleInsuranceWebhook(array $payload): array
    {
        // Manejar notificaciones de obra social
        Log::info('Insurance webhook received', $payload);
        return ['success' => true, 'processed' => 'insurance_webhook'];
    }

    private function handleLaboratoryWebhook(array $payload): array
    {
        // Manejar notificaciones de laboratorio
        Log::info('Laboratory webhook received', $payload);
        return ['success' => true, 'processed' => 'laboratory_webhook'];
    }
}
