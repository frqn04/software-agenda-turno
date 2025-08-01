<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\AfterToday;
use App\Rules\BusinessHours;

class UpdateTurnoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('turno'));
    }

    public function rules(): array
    {
        return [
            'paciente_id' => 'sometimes|required|exists:pacientes,id',
            'doctor_id' => 'sometimes|required|exists:doctores,id',
            'fecha_hora' => [
                'sometimes',
                'required',
                'date',
                new AfterToday(2), // Mínimo 2 horas de anticipación
                new BusinessHours(), // Validar horarios de negocio
            ],
            'motivo' => 'sometimes|required|string|max:500',
            'observaciones' => 'nullable|string|max:1000',
            'estado' => 'sometimes|in:programado,confirmado,cancelado,completado',
        ];
    }

    public function messages(): array
    {
        return [
            'paciente_id.required' => 'Debe seleccionar un paciente',
            'paciente_id.exists' => 'El paciente seleccionado no existe',
            'doctor_id.required' => 'Debe seleccionar un doctor',
            'doctor_id.exists' => 'El doctor seleccionado no existe',
            'fecha_hora.required' => 'La fecha y hora son obligatorias',
            'motivo.required' => 'El motivo de la consulta es obligatorio',
            'motivo.max' => 'El motivo no puede exceder los 500 caracteres',
            'observaciones.max' => 'Las observaciones no pueden exceder los 1000 caracteres',
        ];
    }
}
