<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para registrar nuevos pacientes en la clínica dental
 * Validaciones específicas para datos de pacientes odontológicos
 */
class StorePacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Paciente::class);
    }

    public function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
                'min:2',
                'regex:/^[a-zA-ZÀ-ÿ\s]+$/'
            ],
            'apellido' => [
                'required',
                'string',
                'max:100',
                'min:2',
                'regex:/^[a-zA-ZÀ-ÿ\s]+$/'
            ],
            'dni' => [
                'required',
                'string',
                'max:20',
                'unique:pacientes,dni',
                'regex:/^[0-9]{7,8}$/' // DNI argentino
            ],
            'fecha_nacimiento' => [
                'required',
                'date',
                'before:today',
                'after:' . now()->subYears(120)->format('Y-m-d') // Máximo 120 años
            ],
            'telefono' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[\+]?[0-9\s\-\(\)]+$/' // Formato telefónico
            ],
            'email' => [
                'nullable',
                'email:rfc',
                'max:255',
                'unique:pacientes,email'
            ],
            'direccion' => 'nullable|string|max:255',
            'obra_social' => 'nullable|string|max:100',
            'numero_afiliado' => 'nullable|string|max:50',
            'contacto_emergencia' => 'nullable|string|max:255',
            'telefono_emergencia' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[\+]?[0-9\s\-\(\)]+$/'
            ],
            'alergias' => 'nullable|string|max:500',
            'medicamentos' => 'nullable|string|max:500',
            'observaciones_medicas' => 'nullable|string|max:1000',
            'activo' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del paciente es obligatorio',
            'nombre.min' => 'El nombre debe tener al menos 2 caracteres',
            'nombre.regex' => 'El nombre solo puede contener letras y espacios',
            'apellido.required' => 'El apellido del paciente es obligatorio',
            'apellido.min' => 'El apellido debe tener al menos 2 caracteres',
            'apellido.regex' => 'El apellido solo puede contener letras y espacios',
            'dni.required' => 'El DNI del paciente es obligatorio',
            'dni.unique' => 'Ya existe un paciente registrado con este DNI en la clínica',
            'dni.regex' => 'El DNI debe tener entre 7 y 8 números',
            'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy',
            'fecha_nacimiento.after' => 'Ingrese una fecha de nacimiento válida',
            'telefono.regex' => 'Ingrese un número de teléfono válido',
            'email.email' => 'Ingrese un email válido para el paciente',
            'email.unique' => 'Ya existe un paciente registrado con este email',
            'telefono_emergencia.regex' => 'Ingrese un teléfono de emergencia válido',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre del paciente',
            'apellido' => 'apellido del paciente',
            'dni' => 'DNI del paciente',
            'fecha_nacimiento' => 'fecha de nacimiento',
            'telefono' => 'teléfono del paciente',
            'email' => 'email del paciente',
            'obra_social' => 'obra social',
            'numero_afiliado' => 'número de afiliado',
        ];
    }
}
