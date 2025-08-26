<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('paciente'));
    }

    public function rules(): array
    {
        $pacienteId = $this->route('paciente')->id;
        
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
            'dni' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                'regex:/^[0-9]{7,8}$/',
                Rule::unique('pacientes', 'dni')->ignore($pacienteId),
                function ($attribute, $value, $fail) {
                    // Validación básica de DNI argentino
                    if (strlen($value) < 7 || strlen($value) > 8) {
                        $fail('El DNI debe tener entre 7 y 8 dígitos.');
                    }
                }
            ],
            'fecha_nacimiento' => [
                'sometimes',
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
                'not_regex:/[<>"\']/'
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
            'activo' => [
                'boolean',
                function ($attribute, $value, $fail) {
                    // Verificar si tiene turnos pendientes antes de desactivar
                    if (!$value) {
                        $paciente = \App\Models\Paciente::find($this->route('paciente')->id);
                        if ($paciente && $paciente->turnos()->where('fecha', '>=', now())->count() > 0) {
                            $fail('No se puede desactivar un paciente con turnos pendientes.');
                        }
                    }
                }
            ],
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
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del paciente es obligatorio.',
            'nombre.regex' => 'El nombre solo puede contener letras y espacios.',
            'apellido.required' => 'El apellido del paciente es obligatorio.',
            'apellido.regex' => 'El apellido solo puede contener letras y espacios.',
            'dni.required' => 'El DNI del paciente es obligatorio.',
            'dni.unique' => 'Ya existe un paciente con este DNI en la clínica.',
            'dni.regex' => 'El DNI debe contener solo números.',
            'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'fecha_nacimiento.after' => 'La fecha de nacimiento no puede ser anterior a 1900.',
            'telefono.regex' => 'El formato del teléfono no es válido.',
            'email.email' => 'El email debe tener un formato válido.',
            'email.not_regex' => 'El email contiene caracteres no permitidos.',
            'obra_social.regex' => 'La obra social contiene caracteres no permitidos.',
            'numero_afiliado.regex' => 'El número de afiliado contiene caracteres no válidos.',
            'alergias.max' => 'Las alergias no pueden exceder los 500 caracteres.',
            'observaciones_medicas.max' => 'Las observaciones médicas no pueden exceder los 1000 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre del paciente',
            'apellido' => 'apellido del paciente',
            'dni' => 'DNI',
            'fecha_nacimiento' => 'fecha de nacimiento',
            'telefono' => 'teléfono',
            'email' => 'email',
            'direccion' => 'dirección',
            'obra_social' => 'obra social',
            'numero_afiliado' => 'número de afiliado',
            'alergias' => 'alergias',
            'observaciones_medicas' => 'observaciones médicas',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => ucwords(strtolower(strip_tags(trim($this->nombre)))),
            'apellido' => ucwords(strtolower(strip_tags(trim($this->apellido)))),
            'dni' => preg_replace('/[^0-9]/', '', $this->dni),
            'email' => strtolower(strip_tags(trim($this->email))),
            'telefono' => preg_replace('/[^0-9\+\-\(\)\s]/', '', $this->telefono),
            'obra_social' => ucwords(strtolower(strip_tags(trim($this->obra_social)))),
            'numero_afiliado' => strtoupper(strip_tags(trim($this->numero_afiliado))),
            'direccion' => strip_tags(trim($this->direccion)),
            'alergias' => strip_tags(trim($this->alergias)),
            'observaciones_medicas' => strip_tags(trim($this->observaciones_medicas)),
        ]);
    }
}
