<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\AfterToday;
use App\Rules\BusinessHours;

/**
 * Request para crear turnos en la clínica dental
 * Validaciones específicas para sistema interno de gestión de turnos
 */
class StoreTurnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Turno::class);
    }

    public function rules(): array
    {
        return [
            'paciente_id' => [
                'required',
                'exists:pacientes,id',
                function ($attribute, $value, $fail) {
                    $paciente = \App\Models\Paciente::find($value);
                    if ($paciente && !$paciente->activo) {
                        $fail('No se pueden asignar turnos a pacientes inactivos.');
                    }
                }
            ],
            'doctor_id' => [
                'required',
                'exists:doctores,id',
                function ($attribute, $value, $fail) {
                    $doctor = \App\Models\Doctor::find($value);
                    if ($doctor && !$doctor->activo) {
                        $fail('No se pueden asignar turnos a doctores inactivos.');
                    }
                }
            ],
            'fecha' => [
                'required',
                'date',
                'after_or_equal:today',
                'before:' . now()->addMonths(3)->format('Y-m-d'), // Máximo 3 meses adelante
            ],
            'hora_inicio' => [
                'required',
                'date_format:H:i',
                new BusinessHours(),
            ],
            'hora_fin' => [
                'required',
                'date_format:H:i',
                'after:hora_inicio',
                function ($attribute, $value, $fail) {
                    $inicio = \Carbon\Carbon::createFromFormat('H:i', $this->hora_inicio);
                    $fin = \Carbon\Carbon::createFromFormat('H:i', $value);
                    $duracion = $fin->diffInMinutes($inicio);
                    
                    if ($duracion < 15) {
                        $fail('La duración mínima del turno debe ser de 15 minutos.');
                    }
                    if ($duracion > 120) {
                        $fail('La duración máxima del turno debe ser de 2 horas.');
                    }
                }
            ],
            'motivo' => [
                'required',
                'string',
                'max:500',
                'min:10'
            ],
            'observaciones' => 'nullable|string|max:1000',
            'estado' => 'nullable|in:programado,confirmado,cancelado,realizado',
            'especialidad_id' => 'nullable|exists:especialidades,id',
        ];
    }

    public function messages(): array
    {
        return [
            'paciente_id.required' => 'Debe seleccionar un paciente de la clínica',
            'paciente_id.exists' => 'El paciente seleccionado no está registrado en la clínica',
            'doctor_id.required' => 'Debe seleccionar un doctor de la clínica',
            'doctor_id.exists' => 'El doctor seleccionado no está registrado en la clínica',
            'fecha.required' => 'La fecha del turno es obligatoria',
            'fecha.after_or_equal' => 'La fecha del turno debe ser hoy o posterior',
            'fecha.before' => 'No se pueden programar turnos con más de 3 meses de anticipación',
            'hora_inicio.required' => 'La hora de inicio del turno es obligatoria',
            'hora_inicio.date_format' => 'El formato de hora debe ser HH:MM (ej: 09:30)',
            'hora_fin.required' => 'La hora de fin del turno es obligatoria',
            'hora_fin.date_format' => 'El formato de hora debe ser HH:MM (ej: 10:30)',
            'hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio',
            'motivo.required' => 'El motivo de la consulta odontológica es obligatorio',
            'motivo.min' => 'El motivo debe tener al menos 10 caracteres',
            'motivo.max' => 'El motivo no puede exceder los 500 caracteres',
            'observaciones.max' => 'Las observaciones no pueden exceder los 1000 caracteres',
            'especialidad_id.exists' => 'La especialidad seleccionada no existe',
        ];
    }

    public function attributes(): array
    {
        return [
            'paciente_id' => 'paciente',
            'doctor_id' => 'doctor',
            'fecha' => 'fecha del turno',
            'hora_inicio' => 'hora de inicio',
            'hora_fin' => 'hora de fin',
            'motivo' => 'motivo de consulta',
            'observaciones' => 'observaciones del turno',
        ];
    }
}
