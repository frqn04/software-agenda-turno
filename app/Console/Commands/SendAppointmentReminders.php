<?php

namespace App\Console\Commands;

use App\Models\Turno;
use App\Jobs\SendAppointmentReminderJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendAppointmentReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'appointments:send-reminders';

    /**
     * The console command description.
     */
    protected $description = 'Envía recordatorios de turnos programados para mañana';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando envío de recordatorios de turnos...');

        try {
            // Buscar turnos para mañana
            $tomorrow = Carbon::tomorrow();
            $dayAfterTomorrow = Carbon::tomorrow()->addDay();

            $turnos = Turno::whereBetween('fecha_hora', [$tomorrow, $dayAfterTomorrow])
                ->whereIn('estado', ['programado', 'confirmado'])
                ->with(['paciente', 'doctor.user'])
                ->get();

            $this->info("Encontrados {$turnos->count()} turnos para recordar");

            $sent = 0;
            $errors = 0;

            foreach ($turnos as $turno) {
                try {
                    // Verificar que el paciente tenga email
                    if (!$turno->paciente->email) {
                        $this->warn("Turno ID {$turno->id}: Paciente sin email");
                        continue;
                    }

                    // Despachar job de recordatorio
                    SendAppointmentReminderJob::dispatch($turno);
                    $sent++;

                    $this->line("✓ Recordatorio programado para turno ID {$turno->id}");

                } catch (\Exception $e) {
                    $errors++;
                    $this->error("✗ Error con turno ID {$turno->id}: {$e->getMessage()}");
                    
                    Log::error('Error programando recordatorio', [
                        'turno_id' => $turno->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->info("Proceso completado:");
            $this->info("- Recordatorios programados: {$sent}");
            $this->info("- Errores: {$errors}");

            Log::info('Comando de recordatorios ejecutado', [
                'turnos_encontrados' => $turnos->count(),
                'recordatorios_enviados' => $sent,
                'errores' => $errors,
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error general: {$e->getMessage()}");
            
            Log::error('Error en comando de recordatorios', [
                'error' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }
    }
}
