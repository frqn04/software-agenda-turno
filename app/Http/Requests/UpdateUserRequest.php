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
            ],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email:rfc',
                'max:255',
                'unique:users,email,' . $userId,
                'not_regex:/[<>"\']/',
            ],
            'rol' => [
                'sometimes',
                'required',
                'string',
                'in:admin,doctor,recepcionista'
            ],
            'doctor_id' => [
                'nullable',
                'exists:doctores,id',
                'required_if:rol,doctor'
            ],
            'activo' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.regex' => 'El nombre solo puede contener letras y espacios.',
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email debe tener un formato válido.',
            'email.unique' => 'Este email ya está registrado.',
            'email.not_regex' => 'El email contiene caracteres no permitidos.',
            'rol.required' => 'El rol es obligatorio.',
            'rol.in' => 'El rol seleccionado no es válido.',
            'doctor_id.required_if' => 'El doctor es obligatorio para usuarios con rol doctor.',
            'doctor_id.exists' => 'El doctor seleccionado no existe.',
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
}
