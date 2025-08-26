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
            'paciente_id' => [
                'required',
                'integer',
                'exists:pacientes,id',
                function ($attribute, $value, $fail) {
                    $paciente = \App\Models\Paciente::find($value);
                    if ($paciente && !$paciente->activo) {
                        $fail('No se puede asignar un paciente inactivo.');
                    }
                }
            ],
            'doctor_id' => [
                'required',
                'integer',
                'exists:doctores,id',
                function ($attribute, $value, $fail) {
                    $doctor = \App\Models\Doctor::find($value);
                    if ($doctor && !$doctor->activo) {
                        $fail('No se puede asignar un doctor inactivo.');
                    }
                }
            ],
            'fecha' => [
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
                'nullable',
                'date_format:H:i',
                'after:hora_inicio',
                function ($attribute, $value, $fail) {
                    if ($this->hora_inicio && $value) {
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
            'duration_minutes' => [
                'nullable',
                'integer',
                'min:30',
                'max:120',
                'in:30,60,90,120' // Solo duraciones estándar
            ],
            'estado' => [
                'nullable',
                Rule::in(['programado', 'confirmado', 'en_proceso', 'realizado', 'cancelado', 'no_asistio'])
            ],
            'tipo' => [
                'nullable',
                Rule::in(['consulta_general', 'limpieza', 'extraccion', 'endodoncia', 'implante', 'ortodoncia', 'protesis', 'urgencia', 'control'])
            ],
            'motivo_consulta' => [
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
            'precio' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999.99'
            ]
        ];

        // Reglas adicionales para actualización - convertir a sometimes
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            foreach ($rules as $field => &$rule) {
                if (is_array($rule) && isset($rule[0]) && $rule[0] === 'required') {
                    $rule[0] = 'sometimes|required';
                } elseif (is_string($rule) && str_starts_with($rule, 'required')) {
                    $rule = 'sometimes|' . $rule;
                }
            }
        }

        return $rules;
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'paciente_id.required' => 'Debe seleccionar un paciente para el turno.',
            'paciente_id.exists' => 'El paciente seleccionado no existe en la clínica.',
            'doctor_id.required' => 'Debe seleccionar un doctor para el turno.',
            'doctor_id.exists' => 'El doctor seleccionado no existe en la clínica.',
            'fecha.required' => 'La fecha del turno es obligatoria.',
            'fecha.date' => 'La fecha debe ser válida.',
            'fecha.after_or_equal' => 'No se pueden programar turnos en fechas pasadas.',
            'fecha.before' => 'No se pueden programar turnos con más de 6 meses de anticipación.',
            'hora_inicio.required' => 'La hora de inicio del turno es obligatoria.',
            'hora_inicio.date_format' => 'La hora de inicio debe tener formato HH:MM (ej: 14:30).',
            'hora_fin.date_format' => 'La hora de fin debe tener formato HH:MM (ej: 15:00).',
            'hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio.',
            'duration_minutes.integer' => 'La duración debe ser un número entero.',
            'duration_minutes.min' => 'La duración mínima es de 30 minutos.',
            'duration_minutes.max' => 'La duración máxima es de 120 minutos.',
            'duration_minutes.in' => 'La duración debe ser 30, 60, 90 o 120 minutos.',
            'estado.in' => 'El estado del turno no es válido.',
            'tipo.in' => 'El tipo de consulta dental no es válido.',
            'motivo_consulta.required' => 'Debe especificar el motivo de la consulta dental.',
            'motivo_consulta.max' => 'El motivo de consulta no puede exceder 500 caracteres.',
            'motivo_consulta.regex' => 'El motivo contiene caracteres no permitidos.',
            'observaciones.max' => 'Las observaciones no pueden exceder 1000 caracteres.',
            'precio.numeric' => 'El precio debe ser un número válido.',
            'precio.min' => 'El precio no puede ser negativo.',
            'precio.max' => 'El precio excede el límite permitido.',
        ];
    }

    /**
     * Get custom attribute names.
     */
    public function attributes(): array
    {
        return [
            'paciente_id' => 'paciente',
            'doctor_id' => 'doctor',
            'fecha' => 'fecha del turno',
            'hora_inicio' => 'hora de inicio',
            'hora_fin' => 'hora de finalización',
            'duration_minutes' => 'duración en minutos',
            'estado' => 'estado del turno',
            'tipo' => 'tipo de consulta',
            'motivo_consulta' => 'motivo de consulta',
            'observaciones' => 'observaciones',
            'precio' => 'precio de la consulta',
        ];
    }

    /**
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'motivo_consulta' => strip_tags(trim($this->motivo_consulta)),
            'observaciones' => strip_tags(trim($this->observaciones)),
        ]);

        // Si no se proporciona hora_fin pero sí duration_minutes, calcularla
        if (!$this->hora_fin && $this->hora_inicio && $this->duration_minutes) {
            $inicio = \Carbon\Carbon::createFromFormat('H:i', $this->hora_inicio);
            $fin = $inicio->copy()->addMinutes($this->duration_minutes);
            $this->merge(['hora_fin' => $fin->format('H:i')]);
        }
        
        // Si no se proporciona duration_minutes, usar 30 minutos por defecto
        if (!$this->duration_minutes && !$this->hora_fin) {
            $this->merge(['duration_minutes' => 30]);
        }
    }
}
