<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Doctor::class);
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'especialidad_id' => 'required|exists:especialidades,id',
            'matricula' => 'required|string|max:50|unique:doctores,matricula',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255|unique:doctores,email',
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
