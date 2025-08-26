<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;
        
        return [
            'name' => [
                'sometimes',
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
                'sometimes',
                'required',
                'string',
                'email:rfc,dns',
                'max:255',
                'unique:users,email,' . $userId,
                'not_regex:/[<>"\']/',
            ],
            'password' => [
                'nullable',
                'confirmed',
                \Illuminate\Validation\Rules\Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
            'rol' => [
                'sometimes',
                'required',
                'string',
                'in:admin,doctor,secretaria,operador', // Roles específicos de clínica dental
                function ($attribute, $value, $fail) {
                    $user = \App\Models\User::find($this->route('user')->id);
                    
                    // No permitir cambiar el último admin
                    if ($user && $user->rol === 'admin' && $value !== 'admin') {
                        $adminCount = \App\Models\User::where('rol', 'admin')->where('activo', true)->count();
                        if ($adminCount <= 1) {
                            $fail('No se puede cambiar el rol del último administrador de la clínica.');
                        }
                    }
                }
            ],
            'doctor_id' => [
                'nullable',
                'exists:doctores,id',
                'required_if:rol,doctor',
                function ($attribute, $value, $fail) {
                    if ($this->rol === 'doctor' && $value) {
                        $currentUser = \App\Models\User::find($this->route('user')->id);
                        $doctorUser = \App\Models\User::where('doctor_id', $value)
                            ->where('id', '!=', $currentUser->id)
                            ->first();
                        
                        if ($doctorUser) {
                            $fail('Este doctor ya tiene otro usuario asignado en el sistema.');
                        }
                    }
                }
            ],
            'activo' => [
                'boolean',
                function ($attribute, $value, $fail) {
                    $user = \App\Models\User::find($this->route('user')->id);
                    
                    // No permitir desactivar el último admin
                    if (!$value && $user && $user->rol === 'admin') {
                        $adminCount = \App\Models\User::where('rol', 'admin')->where('activo', true)->count();
                        if ($adminCount <= 1) {
                            $fail('No se puede desactivar el último administrador de la clínica.');
                        }
                    }
                }
            ],
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
        if ($this->has('name')) {
            $this->merge([
                'name' => strip_tags(trim($this->name)),
            ]);
        }
        
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(strip_tags(trim($this->email))),
            ]);
        }
    }

    public function passedValidation(): void
    {
        if ($this->password) {
            $this->merge([
                'password' => bcrypt($this->password),
            ]);
        }
    }
}
