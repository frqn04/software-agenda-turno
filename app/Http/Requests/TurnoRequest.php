<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TurnoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Autorización manejada por policies
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'paciente_id' => 'required|integer|exists:pacientes,id',
            'doctor_id' => 'required|integer|exists:doctores,id',
            'fecha' => 'required|date|after_or_equal:today',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i|after:hora_inicio',
            'duration_minutes' => 'nullable|integer|min:15|max:120',
            'estado' => [
                'nullable',
                Rule::in(['pendiente', 'confirmado', 'en_proceso', 'completado', 'cancelado', 'no_asistio'])
            ],
            'tipo' => [
                'nullable',
                Rule::in(['consulta', 'control', 'procedimiento', 'cirugia'])
            ],
            'observaciones' => 'nullable|string|max:1000',
            'motivo_consulta' => 'nullable|string|max:500'
        ];

        // Reglas adicionales para actualización
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['paciente_id'] = 'sometimes|required|integer|exists:pacientes,id';
            $rules['doctor_id'] = 'sometimes|required|integer|exists:doctores,id';
            $rules['fecha'] = 'sometimes|required|date';
            $rules['hora_inicio'] = 'sometimes|required|date_format:H:i';
        }

        return $rules;
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'paciente_id.required' => 'El paciente es obligatorio.',
            'paciente_id.exists' => 'El paciente seleccionado no existe.',
            'doctor_id.required' => 'El doctor es obligatorio.',
            'doctor_id.exists' => 'El doctor seleccionado no existe.',
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.date' => 'La fecha debe ser válida.',
            'fecha.after_or_equal' => 'La fecha no puede ser anterior a hoy.',
            'hora_inicio.required' => 'La hora de inicio es obligatoria.',
            'hora_inicio.date_format' => 'La hora de inicio debe tener formato HH:MM.',
            'hora_fin.date_format' => 'La hora de fin debe tener formato HH:MM.',
            'hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio.',
            'duration_minutes.integer' => 'La duración debe ser un número entero.',
            'duration_minutes.min' => 'La duración mínima es de 15 minutos.',
            'duration_minutes.max' => 'La duración máxima es de 120 minutos.',
            'estado.in' => 'El estado seleccionado no es válido.',
            'tipo.in' => 'El tipo de turno seleccionado no es válido.',
            'observaciones.max' => 'Las observaciones no pueden exceder 1000 caracteres.',
            'motivo_consulta.max' => 'El motivo de consulta no puede exceder 500 caracteres.'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validación personalizada: si no se proporciona hora_fin, calcularla
            if (!$this->hora_fin && !$this->duration_minutes) {
                $this->merge(['duration_minutes' => 30]); // Default 30 minutos
            }

            // Validación de horario laboral (ejemplo: 8:00 - 18:00)
            if ($this->hora_inicio) {
                $hora = \Carbon\Carbon::createFromFormat('H:i', $this->hora_inicio);
                $inicio_laboral = \Carbon\Carbon::createFromFormat('H:i', '08:00');
                $fin_laboral = \Carbon\Carbon::createFromFormat('H:i', '18:00');

                if ($hora->lt($inicio_laboral) || $hora->gt($fin_laboral)) {
                    $validator->errors()->add('hora_inicio', 'La hora debe estar entre 08:00 y 18:00.');
                }
            }
        });
    }
}
