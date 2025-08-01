<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('paciente'));
    }

    public function rules(): array
    {
        $pacienteId = $this->route('paciente')->id;
        
        return [
            'nombre' => 'sometimes|required|string|max:100',
            'apellido' => 'sometimes|required|string|max:100',
            'dni' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('pacientes', 'dni')->ignore($pacienteId)
            ],
            'fecha_nacimiento' => 'sometimes|required|date|before:today',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'direccion' => 'nullable|string|max:255',
            'obra_social' => 'nullable|string|max:100',
            'numero_afiliado' => 'nullable|string|max:50',
            'activo' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio',
            'apellido.required' => 'El apellido es obligatorio',
            'dni.required' => 'El DNI es obligatorio',
            'dni.unique' => 'Ya existe un paciente con este DNI',
            'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy',
            'email.email' => 'El formato del email no es válido',
        ];
    }
}
