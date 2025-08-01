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
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('doctores')->ignore($doctorId)
            ],
            'telefono' => 'nullable|string|max:20',
            'especialidad_id' => 'required|integer|exists:especialidades,id',
            'matricula' => 'required|string|max:50',
            'activo' => 'nullable|boolean'
        ];

        // Reglas para actualización
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['nombre'] = 'sometimes|required|string|max:100';
            $rules['apellido'] = 'sometimes|required|string|max:100';
            $rules['email'] = [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('doctores')->ignore($doctorId)
            ];
            $rules['especialidad_id'] = 'sometimes|required|integer|exists:especialidades,id';
            $rules['matricula'] = 'sometimes|required|string|max:50';
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
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email debe ser válido.',
            'email.max' => 'El email no puede exceder 255 caracteres.',
            'email.unique' => 'Ya existe un doctor con este email.',
            'telefono.string' => 'El teléfono debe ser texto.',
            'telefono.max' => 'El teléfono no puede exceder 20 caracteres.',
            'especialidad_id.required' => 'La especialidad es obligatoria.',
            'especialidad_id.integer' => 'La especialidad debe ser un número.',
            'especialidad_id.exists' => 'La especialidad seleccionada no existe.',
            'matricula.required' => 'La matrícula es obligatoria.',
            'matricula.string' => 'La matrícula debe ser texto.',
            'matricula.max' => 'La matrícula no puede exceder 50 caracteres.',
            'activo.boolean' => 'El campo activo debe ser verdadero o falso.'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validación personalizada para matrícula
            if ($this->matricula) {
                // Ejemplo: validar formato de matrícula (solo números y letras)
                if (!preg_match('/^[A-Za-z0-9]+$/', $this->matricula)) {
                    $validator->errors()->add('matricula', 'La matrícula solo puede contener letras y números.');
                }
            }

            // Validación de teléfono
            if ($this->telefono) {
                // Remover espacios y guiones para validación
                $telefono_limpio = preg_replace('/[\s\-\(\)]/', '', $this->telefono);
                if (!preg_match('/^\+?[0-9]{8,15}$/', $telefono_limpio)) {
                    $validator->errors()->add('telefono', 'El formato del teléfono no es válido.');
                }
            }
        });
    }
}
