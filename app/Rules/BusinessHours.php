<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Carbon\Carbon;

/**
 * Regla de validación para horarios comerciales médicos
 * Valida días laborables, horarios de atención y intervalos permitidos
 */
class BusinessHours implements ValidationRule
{
    private array $businessHours;
    private array $allowedIntervals;
    private array $excludedDates;

    public function __construct(
        array $businessHours = ['start' => '08:00', 'end' => '18:00'],
        array $allowedIntervals = [15, 30, 60],
        array $excludedDates = []
    ) {
        $this->businessHours = $businessHours;
        $this->allowedIntervals = $allowedIntervals;
        $this->excludedDates = $excludedDates;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $date = Carbon::parse($value);

        // Validar que no sea una fecha excluida (feriados, etc.)
        if ($this->isExcludedDate($date)) {
            $fail('No se pueden programar turnos en esta fecha (feriado o día no laborable).');
            return;
        }

        // Validar que sea día laboral (lunes a viernes por defecto)
        if ($date->isWeekend()) {
            $fail('Los turnos solo se pueden programar de lunes a viernes.');
            return;
        }

        // Validar horario de atención
        if (!$this->isWithinBusinessHours($date)) {
            $fail("Los turnos solo se pueden programar entre las {$this->businessHours['start']} y {$this->businessHours['end']} horas.");
            return;
        }

        // Validar intervalos permitidos
        if (!$this->isValidInterval($date)) {
            $intervals = implode(', ', $this->allowedIntervals);
            $fail("Los turnos solo se pueden programar en intervalos de {$intervals} minutos.");
        }
    }

    /**
     * Verificar si la fecha está dentro del horario de atención
     */
    private function isWithinBusinessHours(Carbon $date): bool
    {
        $startTime = Carbon::createFromFormat('H:i', $this->businessHours['start']);
        $endTime = Carbon::createFromFormat('H:i', $this->businessHours['end']);
        
        $appointmentTime = Carbon::createFromFormat('H:i', $date->format('H:i'));
        
        return $appointmentTime->between($startTime, $endTime->subMinute());
    }

    /**
     * Verificar si el minuto del turno coincide con los intervalos permitidos
     */
    private function isValidInterval(Carbon $date): bool
    {
        $minute = $date->minute;
        
        foreach ($this->allowedIntervals as $interval) {
            if ($minute % $interval === 0) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Verificar si la fecha está en la lista de fechas excluidas
     */
    private function isExcludedDate(Carbon $date): bool
    {
        $dateString = $date->format('Y-m-d');
        return in_array($dateString, $this->excludedDates);
    }
}
