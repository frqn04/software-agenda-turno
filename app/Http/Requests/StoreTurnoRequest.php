<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\AfterToday;
use App\Rules\BusinessHours;

class StoreTurnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Turno::class);
    }

    public function rules(): array
    {
        return [
            'paciente_id' => 'required|exists:pacientes,id',
            'doctor_id' => 'required|exists:doctores,id',
            'fecha' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
            'hora_inicio' => [
                'required',
                'date_format:H:i',
                new BusinessHours(),
            ],
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
            'motivo' => 'required|string|max:500',
            'observaciones' => 'nullable|string|max:1000',
            'estado' => 'nullable|in:programado,confirmado,cancelado,realizado',
        ];
    }

    public function messages(): array
    {
        return [
            'paciente_id.required' => 'Debe seleccionar un paciente',
            'paciente_id.exists' => 'El paciente seleccionado no existe',
            'doctor_id.required' => 'Debe seleccionar un doctor',
            'doctor_id.exists' => 'El doctor seleccionado no existe',
            'fecha.required' => 'La fecha es obligatoria',
            'fecha.after_or_equal' => 'La fecha debe ser hoy o posterior',
            'hora_inicio.required' => 'La hora de inicio es obligatoria',
            'hora_inicio.date_format' => 'El formato de hora debe ser HH:MM',
            'hora_fin.required' => 'La hora de fin es obligatoria',
            'hora_fin.date_format' => 'El formato de hora debe ser HH:MM',
            'hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio',
            'motivo.required' => 'El motivo de la consulta es obligatorio',
            'motivo.max' => 'El motivo no puede exceder los 500 caracteres',
            'observaciones.max' => 'Las observaciones no pueden exceder los 1000 caracteres',
        ];
    }
}
