<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Carbon\Carbon;

/**
 * Regla de validación para fechas futuras con tiempo mínimo de anticipación
 * Útil para evitar turnos médicos de último momento
 */
class AfterToday implements ValidationRule
{
    private int $hoursAfter;
    private bool $allowEmergency;
    private array $emergencyRoles;

    public function __construct(
        int $hoursAfter = 2,
        bool $allowEmergency = false,
        array $emergencyRoles = ['admin', 'emergency_staff']
    ) {
        $this->hoursAfter = $hoursAfter;
        $this->allowEmergency = $allowEmergency;
        $this->emergencyRoles = $emergencyRoles;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $date = Carbon::parse($value);
        $minimumDate = now()->addHours($this->hoursAfter);

        // Si es fecha pasada, fallar inmediatamente
        if ($date->isPast()) {
            $fail('No se pueden programar turnos en fechas pasadas.');
            return;
        }

        // Verificar si se permite emergencia y el usuario tiene permisos
        if ($this->allowEmergency && $this->hasEmergencyPermission()) {
            return; // Permitir cualquier fecha futura para personal de emergencia
        }

        // Validar tiempo mínimo de anticipación
        if ($date->isBefore($minimumDate)) {
            $fail("La fecha debe ser al menos {$this->hoursAfter} horas después del momento actual.");
        }

        // Validar que no sea demasiado lejana (máximo 6 meses)
        $maxDate = now()->addMonths(6);
        if ($date->isAfter($maxDate)) {
            $fail('No se pueden programar turnos con más de 6 meses de anticipación.');
        }
    }

    /**
     * Verificar si el usuario actual tiene permisos de emergencia
     */
    private function hasEmergencyPermission(): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();
        
        // Verificar roles de emergencia
        if (method_exists($user, 'hasRole')) {
            foreach ($this->emergencyRoles as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
        }

        return false;
    }
}
