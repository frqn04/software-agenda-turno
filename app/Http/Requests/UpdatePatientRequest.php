<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $patient = $this->route('paciente');
        return $this->user()->can('update', $patient);
    }

    public function rules(): array
    {
        $patientId = $this->route('paciente')->id ?? $this->route('id');
        
        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-ZÀ-ÿ\s]+$/',
            ],
            'apellido' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-ZÀ-ÿ\s]+$/',
            ],
            'dni' => [
                'required',
                'string',
                'max:20',
                'regex:/^[0-9]{7,8}$/',
                Rule::unique('pacientes', 'dni')->ignore($patientId),
            ],
            'fecha_nacimiento' => [
                'required',
                'date',
                'before:today',
                'after:1900-01-01',
            ],
            'sexo' => [
                'required',
                'string',
                'in:M,F',
            ],
            'telefono' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[\+]?[0-9\s\-\(\)]+$/',
            ],
            'email' => [
                'nullable',
                'email:rfc,dns',
                'max:255',
                'not_regex:/[<>"\']/',
            ],
            'direccion' => [
                'nullable',
                'string',
                'max:255',
            ],
            'observaciones' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.regex' => 'El nombre solo puede contener letras y espacios.',
            'apellido.required' => 'El apellido es obligatorio.',
            'apellido.regex' => 'El apellido solo puede contener letras y espacios.',
            'dni.required' => 'El DNI es obligatorio.',
            'dni.regex' => 'El DNI debe contener entre 7 y 8 dígitos.',
            'dni.unique' => 'Ya existe un paciente con este DNI.',
            'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'fecha_nacimiento.after' => 'La fecha de nacimiento debe ser posterior a 1900.',
            'sexo.required' => 'El sexo es obligatorio.',
            'sexo.in' => 'El sexo debe ser M o F.',
            'telefono.regex' => 'El formato del teléfono no es válido.',
            'email.email' => 'El email debe tener un formato válido.',
            'email.not_regex' => 'El email contiene caracteres no permitidos.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => strip_tags(trim($this->nombre)),
            'apellido' => strip_tags(trim($this->apellido)),
            'dni' => preg_replace('/[^0-9]/', '', $this->dni),
            'telefono' => $this->telefono ? strip_tags(trim($this->telefono)) : null,
            'email' => $this->email ? strtolower(strip_tags(trim($this->email))) : null,
            'direccion' => $this->direccion ? strip_tags(trim($this->direccion)) : null,
            'observaciones' => $this->observaciones ? strip_tags(trim($this->observaciones)) : null,
        ]);
    }
}
