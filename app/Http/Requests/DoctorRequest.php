<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DoctorRequest extends FormRequest
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
        $doctorId = $this->route('doctor') ?? $this->route('id');

        $rules = [
            'nombre' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-ZÀ-ÿ\s]+$/'
            ],
            'apellido' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-ZÀ-ÿ\s]+$/'
            ],
            'email' => [
                'required',
                'email:rfc,dns',
                'max:255',
                'not_regex:/[<>"\']/',
                Rule::unique('doctores')->ignore($doctorId)
            ],
            'telefono' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[\+]?[0-9\s\-\(\)]+$/'
            ],
            'especialidad_id' => [
                'required',
                'integer',
                'exists:especialidades,id',
                function ($attribute, $value, $fail) {
                    $especialidad = \App\Models\Especialidad::find($value);
                    if ($especialidad && !$especialidad->activo) {
                        $fail('No se puede asignar una especialidad inactiva.');
                    }
                }
            ],
            'matricula' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9\-]+$/',
                Rule::unique('doctores')->ignore($doctorId),
                function ($attribute, $value, $fail) {
                    // Validación específica de matrícula dental argentina
                    if (!preg_match('/^[A-Z]{1,3}[0-9]{3,6}$/', $value)) {
                        $fail('El formato de matrícula debe ser letras seguidas de números (ej: MN12345).');
                    }
                }
            ],
            'activo' => 'nullable|boolean',
            'observaciones' => [
                'nullable',
                'string',
                'max:1000'
            ]
        ];

        // Reglas para actualización - convertir a sometimes
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
            'nombre.required' => 'El nombre del doctor es obligatorio.',
            'nombre.string' => 'El nombre debe ser texto válido.',
            'nombre.max' => 'El nombre no puede exceder 100 caracteres.',
            'nombre.regex' => 'El nombre solo puede contener letras y espacios.',
            'apellido.required' => 'El apellido del doctor es obligatorio.',
            'apellido.string' => 'El apellido debe ser texto válido.',
            'apellido.max' => 'El apellido no puede exceder 100 caracteres.',
            'apellido.regex' => 'El apellido solo puede contener letras y espacios.',
            'email.required' => 'El email del doctor es obligatorio.',
            'email.email' => 'El email debe tener un formato válido.',
            'email.max' => 'El email no puede exceder 255 caracteres.',
            'email.unique' => 'Ya existe otro doctor con este email en la clínica.',
            'email.not_regex' => 'El email contiene caracteres no permitidos.',
            'telefono.string' => 'El teléfono debe ser texto válido.',
            'telefono.max' => 'El teléfono no puede exceder 20 caracteres.',
            'telefono.regex' => 'El formato del teléfono no es válido.',
            'especialidad_id.required' => 'Debe seleccionar la especialidad dental.',
            'especialidad_id.integer' => 'La especialidad debe ser un valor numérico.',
            'especialidad_id.exists' => 'La especialidad dental seleccionada no existe.',
            'matricula.required' => 'La matrícula profesional es obligatoria.',
            'matricula.string' => 'La matrícula debe ser texto válido.',
            'matricula.max' => 'La matrícula no puede exceder 50 caracteres.',
            'matricula.regex' => 'La matrícula debe contener solo letras mayúsculas, números y guiones.',
            'matricula.unique' => 'Ya existe otro doctor con esta matrícula en la clínica.',
            'activo.boolean' => 'El estado activo debe ser verdadero o falso.',
            'observaciones.max' => 'Las observaciones no pueden exceder los 1000 caracteres.',
        ];
    }

    /**
     * Get custom attribute names.
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre del doctor',
            'apellido' => 'apellido del doctor',
            'email' => 'email del doctor',
            'telefono' => 'teléfono',
            'especialidad_id' => 'especialidad dental',
            'matricula' => 'matrícula profesional',
            'observaciones' => 'observaciones',
        ];
    }

    /**
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => ucwords(strtolower(strip_tags(trim($this->nombre)))),
            'apellido' => ucwords(strtolower(strip_tags(trim($this->apellido)))),
            'email' => strtolower(strip_tags(trim($this->email))),
            'matricula' => strtoupper(strip_tags(trim($this->matricula))),
            'telefono' => preg_replace('/[^0-9\+\-\(\)\s]/', '', $this->telefono),
            'observaciones' => strip_tags(trim($this->observaciones)),
        ]);
    }
}
