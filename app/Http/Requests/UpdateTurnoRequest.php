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
        $turnoId = $this->route('turno')->id;
        
        return [
            'paciente_id' => [
                'sometimes',
                'required',
                'exists:pacientes,id',
                function ($attribute, $value, $fail) {
                    $paciente = \App\Models\Paciente::find($value);
                    if ($paciente && !$paciente->activo) {
                        $fail('No se puede asignar un paciente inactivo.');
                    }
                }
            ],
            'doctor_id' => [
                'sometimes',
                'required',
                'exists:doctores,id',
                function ($attribute, $value, $fail) {
                    $doctor = \App\Models\Doctor::find($value);
                    if ($doctor && !$doctor->activo) {
                        $fail('No se puede asignar un doctor inactivo.');
                    }
                }
            ],
            'fecha' => [
                'sometimes',
                'required',
                'date',
                'after_or_equal:today',
                'before:' . now()->addMonths(6)->format('Y-m-d'),
                function ($attribute, $value, $fail) {
                    // Verificar que no sea domingo
                    $fecha = \Carbon\Carbon::parse($value);
                    if ($fecha->isSunday()) {
                        $fail('No se pueden programar turnos los domingos.');
                    }
                }
            ],
            'hora_inicio' => [
                'sometimes',
                'required',
                'date_format:H:i',
                function ($attribute, $value, $fail) {
                    // Horarios de clínica dental: 8:00 a 20:00, no domingos
                    $hora = \Carbon\Carbon::createFromFormat('H:i', $value);
                    if ($hora->hour < 8 || $hora->hour >= 20) {
                        $fail('Los turnos deben ser entre 08:00 y 20:00 horas.');
                    }
                    // Verificar intervalos de 30 minutos
                    if ($hora->minute != 0 && $hora->minute != 30) {
                        $fail('Los turnos deben programarse cada 30 minutos (ej: 08:00, 08:30).');
                    }
                }
            ],
            'hora_fin' => [
                'sometimes',
                'required',
                'date_format:H:i',
                'after:hora_inicio',
                function ($attribute, $value, $fail) {
                    if ($this->hora_inicio) {
                        $inicio = \Carbon\Carbon::createFromFormat('H:i', $this->hora_inicio);
                        $fin = \Carbon\Carbon::createFromFormat('H:i', $value);
                        $duracion = $fin->diffInMinutes($inicio);
                        
                        // Duración mínima 30 min, máxima 120 min para procedimientos dentales
                        if ($duracion < 30) {
                            $fail('La duración mínima del turno es 30 minutos.');
                        }
                        if ($duracion > 120) {
                            $fail('La duración máxima del turno es 120 minutos.');
                        }
                    }
                }
            ],
            'motivo' => [
                'sometimes',
                'required',
                'string',
                'max:500',
                'regex:/^[a-zA-ZÀ-ÿ0-9\s\.\,\-\(\)]+$/',
                function ($attribute, $value, $fail) {
                    // Sugerir motivos dentales comunes
                    $motivosDentales = [
                        'consulta general', 'limpieza dental', 'extracción', 'endodoncia',
                        'implante', 'ortodoncia', 'protesis', 'urgencia dental', 'control'
                    ];
                    
                    $motivoLower = strtolower($value);
                    $esComun = false;
                    foreach ($motivosDentales as $motivoComun) {
                        if (str_contains($motivoLower, $motivoComun)) {
                            $esComun = true;
                            break;
                        }
                    }
                    
                    if (!$esComun) {
                        \Log::info("Motivo dental no común registrado: {$value}");
                    }
                }
            ],
            'observaciones' => [
                'nullable',
                'string',
                'max:1000'
            ],
            'estado' => [
                'sometimes',
                'in:programado,confirmado,cancelado,realizado',
                function ($attribute, $value, $fail) {
                    $turno = \App\Models\Turno::find($this->route('turno')->id);
                    
                    // Validaciones de transición de estado
                    if ($turno && $turno->estado === 'realizado' && $value !== 'realizado') {
                        $fail('No se puede cambiar el estado de un turno ya realizado.');
                    }
                    
                    if ($turno && $turno->estado === 'cancelado' && $value === 'realizado') {
                        $fail('No se puede marcar como realizado un turno cancelado.');
                    }
                }
            ],
            'precio' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999.99'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'paciente_id.required' => 'Debe seleccionar un paciente para el turno.',
            'paciente_id.exists' => 'El paciente seleccionado no existe en la clínica.',
            'doctor_id.required' => 'Debe seleccionar un doctor para el turno.',
            'doctor_id.exists' => 'El doctor seleccionado no existe en la clínica.',
            'fecha.required' => 'La fecha del turno es obligatoria.',
            'fecha.after_or_equal' => 'No se pueden programar turnos en fechas pasadas.',
            'fecha.before' => 'No se pueden programar turnos con más de 6 meses de anticipación.',
            'hora_inicio.required' => 'La hora de inicio del turno es obligatoria.',
            'hora_inicio.date_format' => 'El formato de hora debe ser HH:MM (ej: 14:30).',
            'hora_fin.required' => 'La hora de finalización del turno es obligatoria.',
            'hora_fin.date_format' => 'El formato de hora debe ser HH:MM (ej: 15:00).',
            'hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio.',
            'motivo.required' => 'Debe especificar el motivo de la consulta dental.',
            'motivo.max' => 'El motivo no puede exceder los 500 caracteres.',
            'motivo.regex' => 'El motivo contiene caracteres no permitidos.',
            'observaciones.max' => 'Las observaciones no pueden exceder los 1000 caracteres.',
            'estado.in' => 'El estado del turno no es válido.',
            'precio.numeric' => 'El precio debe ser un número válido.',
            'precio.min' => 'El precio no puede ser negativo.',
            'precio.max' => 'El precio excede el límite permitido.',
        ];
    }

    public function attributes(): array
    {
        return [
            'paciente_id' => 'paciente',
            'doctor_id' => 'doctor',
            'fecha' => 'fecha del turno',
            'hora_inicio' => 'hora de inicio',
            'hora_fin' => 'hora de finalización',
            'motivo' => 'motivo de consulta',
            'observaciones' => 'observaciones',
            'estado' => 'estado del turno',
            'precio' => 'precio de la consulta',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'motivo' => strip_tags(trim($this->motivo)),
            'observaciones' => strip_tags(trim($this->observaciones)),
        ]);
    }
}
