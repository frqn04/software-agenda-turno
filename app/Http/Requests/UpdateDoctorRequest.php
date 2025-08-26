<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('doctor'));
    }

    public function rules(): array
    {
        $doctorId = $this->route('doctor')->id;
        
        return [
            'nombre' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-ZÀ-ÿ\s]+$/'
            ],
            'apellido' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-ZÀ-ÿ\s]+$/'
            ],
            'especialidad_id' => [
                'sometimes',
                'required',
                'exists:especialidades,id',
                function ($attribute, $value, $fail) {
                    $especialidad = \App\Models\Especialidad::find($value);
                    if ($especialidad && !$especialidad->activo) {
                        $fail('No se puede asignar una especialidad inactiva.');
                    }
                }
            ],
            'matricula' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9\-]+$/',
                Rule::unique('doctores', 'matricula')->ignore($doctorId),
                function ($attribute, $value, $fail) {
                    // Validación específica de matrícula dental argentina
                    if (!preg_match('/^[A-Z]{1,3}[0-9]{3,6}$/', $value)) {
                        $fail('El formato de matrícula debe ser letras seguidas de números (ej: MN12345).');
                    }
                }
            ],
            'telefono' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[\+]?[0-9\s\-\(\)]+$/'
            ],
            'email' => [
                'nullable',
                'email:rfc,dns',
                'max:255',
                'not_regex:/[<>"\']/',
                Rule::unique('doctores', 'email')->ignore($doctorId)
            ],
            'activo' => 'boolean',
            'observaciones' => [
                'nullable',
                'string',
                'max:1000'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del doctor es obligatorio.',
            'nombre.regex' => 'El nombre solo puede contener letras y espacios.',
            'apellido.required' => 'El apellido del doctor es obligatorio.',
            'apellido.regex' => 'El apellido solo puede contener letras y espacios.',
            'especialidad_id.required' => 'Debe seleccionar la especialidad dental.',
            'especialidad_id.exists' => 'La especialidad dental seleccionada no existe.',
            'matricula.required' => 'La matrícula profesional es obligatoria.',
            'matricula.unique' => 'Ya existe otro doctor con esta matrícula en la clínica.',
            'matricula.regex' => 'La matrícula debe contener solo letras mayúsculas, números y guiones.',
            'telefono.regex' => 'El formato del teléfono no es válido.',
            'email.email' => 'El email debe tener un formato válido.',
            'email.unique' => 'Ya existe otro doctor con este email en la clínica.',
            'email.not_regex' => 'El email contiene caracteres no permitidos.',
            'observaciones.max' => 'Las observaciones no pueden exceder los 1000 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre del doctor',
            'apellido' => 'apellido del doctor',
            'especialidad_id' => 'especialidad dental',
            'matricula' => 'matrícula profesional',
            'telefono' => 'teléfono',
            'email' => 'email',
            'observaciones' => 'observaciones',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => ucwords(strtolower(strip_tags(trim($this->nombre)))),
            'apellido' => ucwords(strtolower(strip_tags(trim($this->apellido)))),
            'matricula' => strtoupper(strip_tags(trim($this->matricula))),
            'email' => strtolower(strip_tags(trim($this->email))),
            'telefono' => preg_replace('/[^0-9\+\-\(\)\s]/', '', $this->telefono),
            'observaciones' => strip_tags(trim($this->observaciones)),
        ]);
    }
}
