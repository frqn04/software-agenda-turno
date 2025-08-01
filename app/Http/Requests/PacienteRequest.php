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
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'dni' => [
                'required',
                'string',
                'max:20',
                Rule::unique('pacientes')->ignore($pacienteId)
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('pacientes')->ignore($pacienteId)
            ],
            'telefono' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'required|date|before:today',
            'direccion' => 'nullable|string|max:255',
            'obra_social' => 'nullable|string|max:100',
            'numero_afiliado' => 'nullable|string|max:50',
            'contacto_emergencia' => 'nullable|string|max:255',
            'telefono_emergencia' => 'nullable|string|max:20',
            'activo' => 'nullable|boolean'
        ];

        // Reglas para actualización
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['nombre'] = 'sometimes|required|string|max:100';
            $rules['apellido'] = 'sometimes|required|string|max:100';
            $rules['dni'] = [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('pacientes')->ignore($pacienteId)
            ];
            $rules['email'] = [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('pacientes')->ignore($pacienteId)
            ];
            $rules['fecha_nacimiento'] = 'sometimes|required|date|before:today';
        }

        return $rules;
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.string' => 'El nombre debe ser texto.',
            'nombre.max' => 'El nombre no puede exceder 100 caracteres.',
            'apellido.required' => 'El apellido es obligatorio.',
            'apellido.string' => 'El apellido debe ser texto.',
            'apellido.max' => 'El apellido no puede exceder 100 caracteres.',
            'dni.required' => 'El DNI es obligatorio.',
            'dni.string' => 'El DNI debe ser texto.',
            'dni.max' => 'El DNI no puede exceder 20 caracteres.',
            'dni.unique' => 'Ya existe un paciente con este DNI.',
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email debe ser válido.',
            'email.max' => 'El email no puede exceder 255 caracteres.',
            'email.unique' => 'Ya existe un paciente con este email.',
            'telefono.string' => 'El teléfono debe ser texto.',
            'telefono.max' => 'El teléfono no puede exceder 20 caracteres.',
            'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria.',
            'fecha_nacimiento.date' => 'La fecha de nacimiento debe ser válida.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'direccion.string' => 'La dirección debe ser texto.',
            'direccion.max' => 'La dirección no puede exceder 255 caracteres.',
            'obra_social.string' => 'La obra social debe ser texto.',
            'obra_social.max' => 'La obra social no puede exceder 100 caracteres.',
            'numero_afiliado.string' => 'El número de afiliado debe ser texto.',
            'numero_afiliado.max' => 'El número de afiliado no puede exceder 50 caracteres.',
            'contacto_emergencia.string' => 'El contacto de emergencia debe ser texto.',
            'contacto_emergencia.max' => 'El contacto de emergencia no puede exceder 255 caracteres.',
            'telefono_emergencia.string' => 'El teléfono de emergencia debe ser texto.',
            'telefono_emergencia.max' => 'El teléfono de emergencia no puede exceder 20 caracteres.',
            'activo.boolean' => 'El campo activo debe ser verdadero o falso.'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validación personalizada para DNI
            if ($this->dni) {
                // Remover espacios y puntos
                $dni_limpio = preg_replace('/[\s\.]/', '', $this->dni);
                
                // Validar que sea solo números y tenga longitud apropiada
                if (!preg_match('/^[0-9]{7,8}$/', $dni_limpio)) {
                    $validator->errors()->add('dni', 'El DNI debe contener solo números y tener entre 7 y 8 dígitos.');
                }
            }

            // Validación de teléfonos
            foreach (['telefono', 'telefono_emergencia'] as $campo) {
                if ($this->$campo) {
                    $telefono_limpio = preg_replace('/[\s\-\(\)]/', '', $this->$campo);
                    if (!preg_match('/^\+?[0-9]{8,15}$/', $telefono_limpio)) {
                        $validator->errors()->add($campo, 'El formato del teléfono no es válido.');
                    }
                }
            }

            // Validación de edad mínima
            if ($this->fecha_nacimiento) {
                $edad = \Carbon\Carbon::parse($this->fecha_nacimiento)->age;
                if ($edad > 120) {
                    $validator->errors()->add('fecha_nacimiento', 'La fecha de nacimiento no puede ser superior a 120 años.');
                }
            }

            // Validación de email de dominio
            if ($this->email) {
                $dominios_bloqueados = ['example.com', 'test.com'];
                $dominio = substr(strrchr($this->email, '@'), 1);
                
                if (in_array($dominio, $dominios_bloqueados)) {
                    $validator->errors()->add('email', 'No se permiten emails de este dominio.');
                }
            }
        });
    }
}
