<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para registrar nuevos doctores en la clínica dental
 * Validaciones específicas para profesionales odontológicos
 */
class StoreDoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Doctor::class);
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
            'especialidad_id' => [
                'required',
                'exists:especialidades,id',
                function ($attribute, $value, $fail) {
                    $especialidad = \App\Models\Especialidad::find($value);
                    if ($especialidad && !$especialidad->activa) {
                        $fail('No se puede asignar una especialidad inactiva.');
                    }
                }
            ],
            'matricula' => [
                'required',
                'string',
                'max:50',
                'unique:doctores,matricula',
                'regex:/^[A-Z0-9\-]+$/' // Formato matrícula profesional
            ],
            'telefono' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[\+]?[0-9\s\-\(\)]+$/'
            ],
            'email' => [
                'nullable',
                'email:rfc',
                'max:255',
                'unique:doctores,email'
            ],
            'horario_inicio' => [
                'nullable',
                'date_format:H:i',
                'before:horario_fin'
            ],
            'horario_fin' => [
                'nullable',
                'date_format:H:i',
                'after:horario_inicio'
            ],
            'dias_atencion' => 'nullable|array',
            'dias_atencion.*' => 'in:lunes,martes,miercoles,jueves,viernes,sabado',
            'duracion_turno' => 'nullable|integer|min:15|max:120', // Entre 15 min y 2 horas
            'activo' => 'boolean'
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del doctor es obligatorio',
            'nombre.min' => 'El nombre debe tener al menos 2 caracteres',
            'nombre.regex' => 'El nombre solo puede contener letras y espacios',
            'apellido.required' => 'El apellido del doctor es obligatorio',
            'apellido.min' => 'El apellido debe tener al menos 2 caracteres',
            'apellido.regex' => 'El apellido solo puede contener letras y espacios',
            'especialidad_id.required' => 'La especialidad odontológica es obligatoria',
            'especialidad_id.exists' => 'La especialidad seleccionada no existe en la clínica',
            'matricula.required' => 'La matrícula profesional es obligatoria',
            'matricula.unique' => 'Ya existe un doctor registrado con esta matrícula',
            'matricula.regex' => 'La matrícula debe contener solo letras mayúsculas, números y guiones',
            'telefono.regex' => 'Ingrese un número de teléfono válido',
            'email.email' => 'Ingrese un email válido para el doctor',
            'email.unique' => 'Ya existe un doctor registrado con este email',
            'horario_inicio.date_format' => 'El horario de inicio debe tener formato HH:MM',
            'horario_fin.date_format' => 'El horario de fin debe tener formato HH:MM',
            'horario_fin.after' => 'El horario de fin debe ser posterior al horario de inicio',
            'dias_atencion.*.in' => 'Día de atención no válido',
            'duracion_turno.min' => 'La duración mínima del turno es 15 minutos',
            'duracion_turno.max' => 'La duración máxima del turno es 120 minutos',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre del doctor',
            'apellido' => 'apellido del doctor',
            'especialidad_id' => 'especialidad odontológica',
            'matricula' => 'matrícula profesional',
            'telefono' => 'teléfono del doctor',
            'email' => 'email del doctor',
            'horario_inicio' => 'horario de inicio',
            'horario_fin' => 'horario de fin',
            'dias_atencion' => 'días de atención',
            'duracion_turno' => 'duración del turno',
        ];
    }
}
