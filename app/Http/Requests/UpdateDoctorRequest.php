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
            'nombre' => 'sometimes|required|string|max:100',
            'apellido' => 'sometimes|required|string|max:100',
            'especialidad_id' => 'sometimes|required|exists:especialidades,id',
            'matricula' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('doctores', 'matricula')->ignore($doctorId)
            ],
            'telefono' => 'nullable|string|max:20',
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('doctores', 'email')->ignore($doctorId)
            ],
            'activo' => 'boolean'
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio',
            'apellido.required' => 'El apellido es obligatorio',
            'especialidad_id.required' => 'La especialidad es obligatoria',
            'especialidad_id.exists' => 'La especialidad seleccionada no existe',
            'matricula.required' => 'La matrícula es obligatoria',
            'matricula.unique' => 'Ya existe un doctor con esta matrícula',
            'email.email' => 'El formato del email no es válido',
            'email.unique' => 'Ya existe un doctor con este email',
        ];
    }
}
