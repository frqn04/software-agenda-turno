<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEspecialidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Especialidad::class);
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:100|unique:especialidades,nombre',
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
