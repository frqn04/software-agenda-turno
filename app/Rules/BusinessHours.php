<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Carbon\Carbon;

class BusinessHours implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $date = Carbon::parse($value);

        // Validar que sea día laboral (lunes a viernes)
        if ($date->isWeekend()) {
            $fail('Los turnos solo se pueden programar de lunes a viernes.');
            return;
        }

        // Validar horario de atención (8:00 a 18:00)
        $hour = $date->hour;
        if ($hour < 8 || $hour >= 18) {
            $fail('Los turnos solo se pueden programar entre las 8:00 y 18:00 horas.');
            return;
        }

        // Validar que los minutos sean 00 o 30
        if (!in_array($date->minute, [0, 30])) {
            $fail('Los turnos solo se pueden programar cada 30 minutos (en punto o y media).');
        }
    }
}
