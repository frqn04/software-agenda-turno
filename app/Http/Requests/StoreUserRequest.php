<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\User::class);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-ZÀ-ÿ\s]+$/',
                function ($attribute, $value, $fail) {
                    if (str_word_count($value) < 2) {
                        $fail('El nombre debe contener al menos nombre y apellido.');
                    }
                }
            ],
            'email' => [
                'required',
                'string',
                'email:rfc,dns',
                'max:255',
                'unique:users',
                'not_regex:/[<>"\']/',
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
            'rol' => [
                'required',
                'string',
                'in:admin,doctor,secretaria,operador' // Roles específicos de clínica dental
            ],
            'doctor_id' => [
                'nullable',
                'exists:doctores,id',
                'required_if:rol,doctor',
                function ($attribute, $value, $fail) {
                    if ($this->rol === 'doctor' && $value) {
                        $doctorUser = \App\Models\User::where('doctor_id', $value)->first();
                        if ($doctorUser) {
                            $fail('Este doctor ya tiene un usuario asignado en el sistema.');
                        }
                    }
                }
            ],
            'activo' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre completo del personal es obligatorio.',
            'name.regex' => 'El nombre solo puede contener letras y espacios.',
            'email.required' => 'El email del personal es obligatorio.',
            'email.email' => 'El email debe tener un formato válido.',
            'email.unique' => 'Este email ya está registrado en el sistema de la clínica.',
            'email.not_regex' => 'El email contiene caracteres no permitidos.',
            'password.required' => 'La contraseña del personal es obligatoria.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
            'rol.required' => 'Debe asignar un rol al personal de la clínica.',
            'rol.in' => 'El rol debe ser: admin, doctor, secretaria u operador.',
            'doctor_id.required_if' => 'Debe seleccionar el doctor al que pertenece esta cuenta.',
            'doctor_id.exists' => 'El doctor seleccionado no existe en la clínica.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre del personal',
            'email' => 'email del personal',
            'password' => 'contraseña',
            'rol' => 'rol en la clínica',
            'doctor_id' => 'doctor asignado',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => strip_tags(trim($this->name)),
            'email' => strtolower(strip_tags(trim($this->email))),
        ]);
    }

    public function passedValidation(): void
    {
        $this->merge([
            'password' => bcrypt($this->password),
        ]);
    }
}
