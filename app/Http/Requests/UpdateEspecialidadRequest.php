<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEspecialidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('especialidad'));
    }

    public function rules(): array
    {
        $especialidadId = $this->route('especialidad')->id;
        
        return [
            'nombre' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('especialidades', 'nombre')->ignore($especialidadId)
            ],
            'descripcion' => 'nullable|string|max:500',
            'activo' => 'boolean'
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio',
            'nombre.unique' => 'Ya existe una especialidad con este nombre',
            'nombre.max' => 'El nombre no puede exceder los 100 caracteres',
            'descripcion.max' => 'La descripción no puede exceder los 500 caracteres',
        ];
    }
}
