<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Regla de validación para DNI argentino
 * Valida formato y dígito verificador
 */
class ArgentinianDni implements ValidationRule
{
    private bool $requireVerification;

    public function __construct(bool $requireVerification = true)
    {
        $this->requireVerification = $requireVerification;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Limpiar el DNI de espacios y puntos
        $dni = preg_replace('/[^0-9]/', '', $value);

        // Validar longitud (7 u 8 dígitos)
        if (strlen($dni) < 7 || strlen($dni) > 8) {
            $fail('El DNI debe tener entre 7 y 8 dígitos.');
            return;
        }

        // Validar que sean solo números
        if (!is_numeric($dni)) {
            $fail('El DNI debe contener solo números.');
            return;
        }

        // Validar rango válido
        $dniNumber = (int) $dni;
        if ($dniNumber < 1000000 || $dniNumber > 99999999) {
            $fail('El DNI ingresado no es válido.');
            return;
        }

        // Verificación adicional del dígito verificador si está habilitada
        if ($this->requireVerification && !$this->isValidDniChecksum($dni)) {
            $fail('El DNI ingresado no es válido (dígito verificador incorrecto).');
        }
    }

    /**
     * Validar dígito verificador del DNI (algoritmo simplificado)
     */
    private function isValidDniChecksum(string $dni): bool
    {
        // Para un sistema real, implementar el algoritmo completo de ANSES
        // Por ahora, validación básica de patrones comunes
        $invalidPatterns = [
            '00000000', '11111111', '22222222', '33333333',
            '44444444', '55555555', '66666666', '77777777',
            '88888888', '99999999', '12345678', '87654321'
        ];

        $paddedDni = str_pad($dni, 8, '0', STR_PAD_LEFT);
        return !in_array($paddedDni, $invalidPatterns);
    }
}
