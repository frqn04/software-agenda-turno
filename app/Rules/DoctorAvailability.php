<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\Doctor;
use App\Models\Turno;
use Carbon\Carbon;

/**
 * Regla de validación para disponibilidad de doctores
 * Verifica horarios, turnos existentes y capacidad
 */
class DoctorAvailability implements ValidationRule
{
    private int $doctorId;
    private ?int $excludeAppointmentId;
    private int $durationMinutes;

    public function __construct(int $doctorId, int $durationMinutes = 30, ?int $excludeAppointmentId = null)
    {
        $this->doctorId = $doctorId;
        $this->durationMinutes = $durationMinutes;
        $this->excludeAppointmentId = $excludeAppointmentId;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $appointmentDateTime = Carbon::parse($value);
        } catch (\Exception $e) {
            $fail('La fecha y hora del turno no es válida.');
            return;
        }

        // Buscar el doctor
        $doctor = Doctor::find($this->doctorId);
        if (!$doctor) {
            $fail('El doctor especificado no existe.');
            return;
        }

        // Verificar si el doctor está activo
        if (!$doctor->activo) {
            $fail('El doctor no está disponible para turnos.');
            return;
        }

        // Verificar horarios de trabajo del doctor
        if (!$this->isDoctorWorkingTime($doctor, $appointmentDateTime)) {
            $fail('El doctor no trabaja en este horario.');
            return;
        }

        // Verificar disponibilidad específica
        if (!$this->isDoctorAvailable($appointmentDateTime)) {
            $fail('El doctor ya tiene un turno programado en este horario.');
            return;
        }

        // Verificar límite de turnos por día
        if (!$this->isWithinDailyLimit($doctor, $appointmentDateTime)) {
            $fail('El doctor ha alcanzado el límite máximo de turnos para este día.');
            return;
        }

        // Verificar tiempo entre turnos
        if (!$this->hasMinimumTimeBetweenAppointments($appointmentDateTime)) {
            $fail('No hay suficiente tiempo entre turnos. Mínimo requerido: 5 minutos.');
        }
    }

    /**
     * Verificar si el doctor trabaja en este horario
     */
    private function isDoctorWorkingTime(Doctor $doctor, Carbon $dateTime): bool
    {
        $dayOfWeek = $dateTime->dayOfWeek; // 0 = domingo, 1 = lunes, etc.
        $time = $dateTime->format('H:i');

        // Verificar horarios de trabajo (esto debería venir de la base de datos)
        // Por ahora, asumimos horarios estándar
        $workingHours = [
            1 => ['08:00', '18:00'], // Lunes
            2 => ['08:00', '18:00'], // Martes
            3 => ['08:00', '18:00'], // Miércoles
            4 => ['08:00', '18:00'], // Jueves
            5 => ['08:00', '18:00'], // Viernes
            6 => ['08:00', '13:00'], // Sábado (medio día)
        ];

        if (!isset($workingHours[$dayOfWeek])) {
            return false; // No trabaja domingos
        }

        [$startTime, $endTime] = $workingHours[$dayOfWeek];
        
        return $time >= $startTime && $time <= $endTime;
    }

    /**
     * Verificar disponibilidad del doctor en el horario específico
     */
    private function isDoctorAvailable(Carbon $appointmentDateTime): bool
    {
        $endTime = $appointmentDateTime->copy()->addMinutes($this->durationMinutes);

        $query = Turno::where('doctor_id', $this->doctorId)
            ->where('estado', '!=', 'cancelado')
            ->where(function ($q) use ($appointmentDateTime, $endTime) {
                // Verificar solapamiento de horarios
                $q->whereBetween('fecha_hora', [$appointmentDateTime, $endTime])
                  ->orWhere(function ($subQ) use ($appointmentDateTime, $endTime) {
                      $subQ->where('fecha_hora', '<=', $appointmentDateTime)
                           ->whereRaw('DATE_ADD(fecha_hora, INTERVAL duracion_minutos MINUTE) > ?', [$appointmentDateTime]);
                  });
            });

        // Excluir el turno actual si se está editando
        if ($this->excludeAppointmentId) {
            $query->where('id', '!=', $this->excludeAppointmentId);
        }

        return $query->count() === 0;
    }

    /**
     * Verificar límite diario de turnos
     */
    private function isWithinDailyLimit(Doctor $doctor, Carbon $appointmentDateTime): bool
    {
        $dailyLimit = 20; // Límite por defecto
        
        // El límite podría venir de la configuración del doctor o especialidad
        // $dailyLimit = $doctor->limite_turnos_diarios ?? 20;

        $dayStart = $appointmentDateTime->copy()->startOfDay();
        $dayEnd = $appointmentDateTime->copy()->endOfDay();

        $currentAppointments = Turno::where('doctor_id', $this->doctorId)
            ->where('estado', '!=', 'cancelado')
            ->whereBetween('fecha_hora', [$dayStart, $dayEnd])
            ->count();

        // Excluir el turno actual si se está editando
        if ($this->excludeAppointmentId) {
            $existingAppointment = Turno::find($this->excludeAppointmentId);
            if ($existingAppointment && 
                $existingAppointment->fecha_hora->between($dayStart, $dayEnd)) {
                $currentAppointments--;
            }
        }

        return $currentAppointments < $dailyLimit;
    }

    /**
     * Verificar tiempo mínimo entre turnos
     */
    private function hasMinimumTimeBetweenAppointments(Carbon $appointmentDateTime): bool
    {
        $minimumGap = 5; // 5 minutos mínimo entre turnos
        
        $beforeWindow = $appointmentDateTime->copy()->subMinutes($minimumGap);
        $afterWindow = $appointmentDateTime->copy()->addMinutes($this->durationMinutes + $minimumGap);

        $query = Turno::where('doctor_id', $this->doctorId)
            ->where('estado', '!=', 'cancelado')
            ->where(function ($q) use ($beforeWindow, $afterWindow, $appointmentDateTime) {
                // Verificar turnos muy cercanos
                $q->whereBetween('fecha_hora', [$beforeWindow, $appointmentDateTime])
                  ->orWhere(function ($subQ) use ($appointmentDateTime, $afterWindow) {
                      $subQ->whereBetween('fecha_hora', [$appointmentDateTime, $afterWindow]);
                  });
            });

        if ($this->excludeAppointmentId) {
            $query->where('id', '!=', $this->excludeAppointmentId);
        }

        return $query->count() === 0;
    }
}
