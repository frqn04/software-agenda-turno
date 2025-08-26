<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para login del personal de la clínica dental
 * Solo personal interno: admin, doctores, secretarias, operadores
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                'not_regex:/[<>"\']/',
            ],
            'password' => [
                'required',
                'string',
                'min:6',
                'max:255',
            ],
            'remember' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'El email del personal es obligatorio.',
            'email.email' => 'Ingrese un email válido del personal de la clínica.',
            'email.not_regex' => 'El email contiene caracteres no permitidos.',
            'password.required' => 'La contraseña del personal es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(strip_tags(trim($this->email))),
        ]);
    }

    public function attributes(): array
    {
        return [
            'email' => 'email del personal',
            'password' => 'contraseña del personal',
        ];
    }

    public function throttleKey(): string
    {
        return strtolower($this->input('email')).'|'.$this->ip();
    }
}
