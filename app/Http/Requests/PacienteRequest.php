<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PacienteRequest extends FormRequest
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
        $pacienteId = $this->route('paciente') ?? $this->route('id');

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
            'dni' => [
                'required',
                'string',
                'max:20',
                'regex:/^[0-9]{7,8}$/',
                Rule::unique('pacientes')->ignore($pacienteId),
                function ($attribute, $value, $fail) {
                    // Validación básica de DNI argentino
                    if (strlen($value) < 7 || strlen($value) > 8) {
                        $fail('El DNI debe tener entre 7 y 8 dígitos.');
                    }
                }
            ],
            'email' => [
                'nullable',
                'email:rfc,dns',
                'max:255',
                'not_regex:/[<>"\']/',
                Rule::unique('pacientes')->ignore($pacienteId)
            ],
            'telefono' => [
                'required',
                'string',
                'max:20',
                'regex:/^[\+]?[0-9\s\-\(\)]+$/'
            ],
            'fecha_nacimiento' => [
                'required',
                'date',
                'before:today',
                'after:1900-01-01',
                function ($attribute, $value, $fail) {
                    $edad = \Carbon\Carbon::parse($value)->age;
                    if ($edad > 120) {
                        $fail('La edad no puede ser mayor a 120 años.');
                    }
                }
            ],
            'direccion' => [
                'nullable',
                'string',
                'max:255'
            ],
            'obra_social' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-zA-ZÀ-ÿ0-9\s\.\-]+$/'
            ],
            'numero_afiliado' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[a-zA-Z0-9\-\/]+$/'
            ],
            'contacto_emergencia' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-ZÀ-ÿ\s]+$/'
            ],
            'telefono_emergencia' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[\+]?[0-9\s\-\(\)]+$/'
            ],
            'activo' => 'nullable|boolean',
            'alergias' => [
                'nullable',
                'string',
                'max:500'
            ],
            'observaciones_medicas' => [
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
            // El teléfono sigue siendo requerido en actualización
            $rules['telefono'][0] = 'sometimes|required';
        }

        return $rules;
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del paciente es obligatorio.',
            'nombre.string' => 'El nombre debe ser texto válido.',
            'nombre.max' => 'El nombre no puede exceder 100 caracteres.',
            'nombre.regex' => 'El nombre solo puede contener letras y espacios.',
            'apellido.required' => 'El apellido del paciente es obligatorio.',
            'apellido.string' => 'El apellido debe ser texto válido.',
            'apellido.max' => 'El apellido no puede exceder 100 caracteres.',
            'apellido.regex' => 'El apellido solo puede contener letras y espacios.',
            'dni.required' => 'El DNI del paciente es obligatorio.',
            'dni.string' => 'El DNI debe ser texto válido.',
            'dni.max' => 'El DNI no puede exceder 20 caracteres.',
            'dni.regex' => 'El DNI debe contener solo números.',
            'dni.unique' => 'Ya existe un paciente con este DNI en la clínica.',
            'email.email' => 'El email debe tener un formato válido.',
            'email.max' => 'El email no puede exceder 255 caracteres.',
            'email.unique' => 'Ya existe un paciente con este email en la clínica.',
            'email.not_regex' => 'El email contiene caracteres no permitidos.',
            'telefono.required' => 'El teléfono del paciente es obligatorio.',
            'telefono.string' => 'El teléfono debe ser texto válido.',
            'telefono.max' => 'El teléfono no puede exceder 20 caracteres.',
            'telefono.regex' => 'El formato del teléfono no es válido.',
            'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria.',
            'fecha_nacimiento.date' => 'La fecha de nacimiento debe ser válida.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'fecha_nacimiento.after' => 'La fecha de nacimiento no puede ser anterior a 1900.',
            'direccion.string' => 'La dirección debe ser texto válido.',
            'direccion.max' => 'La dirección no puede exceder 255 caracteres.',
            'obra_social.string' => 'La obra social debe ser texto válido.',
            'obra_social.max' => 'La obra social no puede exceder 100 caracteres.',
            'obra_social.regex' => 'La obra social contiene caracteres no permitidos.',
            'numero_afiliado.string' => 'El número de afiliado debe ser texto válido.',
            'numero_afiliado.max' => 'El número de afiliado no puede exceder 50 caracteres.',
            'numero_afiliado.regex' => 'El número de afiliado contiene caracteres no válidos.',
            'contacto_emergencia.string' => 'El contacto de emergencia debe ser texto válido.',
            'contacto_emergencia.max' => 'El contacto de emergencia no puede exceder 255 caracteres.',
            'contacto_emergencia.regex' => 'El contacto de emergencia solo puede contener letras y espacios.',
            'telefono_emergencia.string' => 'El teléfono de emergencia debe ser texto válido.',
            'telefono_emergencia.max' => 'El teléfono de emergencia no puede exceder 20 caracteres.',
            'telefono_emergencia.regex' => 'El formato del teléfono de emergencia no es válido.',
            'activo.boolean' => 'El estado activo debe ser verdadero o falso.',
            'alergias.max' => 'Las alergias no pueden exceder los 500 caracteres.',
            'observaciones_medicas.max' => 'Las observaciones médicas no pueden exceder los 1000 caracteres.',
        ];
    }

    /**
     * Get custom attribute names.
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre del paciente',
            'apellido' => 'apellido del paciente',
            'dni' => 'DNI',
            'email' => 'email del paciente',
            'telefono' => 'teléfono',
            'fecha_nacimiento' => 'fecha de nacimiento',
            'direccion' => 'dirección',
            'obra_social' => 'obra social',
            'numero_afiliado' => 'número de afiliado',
            'contacto_emergencia' => 'contacto de emergencia',
            'telefono_emergencia' => 'teléfono de emergencia',
            'alergias' => 'alergias',
            'observaciones_medicas' => 'observaciones médicas',
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
            'dni' => preg_replace('/[^0-9]/', '', $this->dni),
            'email' => strtolower(strip_tags(trim($this->email))),
            'telefono' => preg_replace('/[^0-9\+\-\(\)\s]/', '', $this->telefono),
            'telefono_emergencia' => preg_replace('/[^0-9\+\-\(\)\s]/', '', $this->telefono_emergencia),
            'obra_social' => ucwords(strtolower(strip_tags(trim($this->obra_social)))),
            'numero_afiliado' => strtoupper(strip_tags(trim($this->numero_afiliado))),
            'contacto_emergencia' => ucwords(strtolower(strip_tags(trim($this->contacto_emergencia)))),
            'direccion' => strip_tags(trim($this->direccion)),
            'alergias' => strip_tags(trim($this->alergias)),
            'observaciones_medicas' => strip_tags(trim($this->observaciones_medicas)),
        ]);
    }
}
