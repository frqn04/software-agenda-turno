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
                'regex:/^[a-zA-ZÀ-ÿ\s\-\.]+$/',
                Rule::unique('especialidades', 'nombre')->ignore($especialidadId),
                function ($attribute, $value, $fail) {
                    // Verificar si tiene doctores asignados antes de cambiar nombre
                    $especialidad = \App\Models\Especialidad::find($this->route('especialidad')->id);
                    if ($especialidad && $especialidad->doctores()->count() > 0 && $especialidad->nombre !== $value) {
                        \Log::info("Cambio de nombre en especialidad con doctores asignados: {$especialidad->nombre} -> {$value}");
                    }
                }
            ],
            'descripcion' => [
                'nullable',
                'string',
                'max:500',
                'regex:/^[a-zA-ZÀ-ÿ0-9\s\.\,\-\(\)]+$/'
            ],
            'activo' => [
                'boolean',
                function ($attribute, $value, $fail) {
                    // No permitir desactivar si tiene doctores activos
                    if (!$value) {
                        $especialidad = \App\Models\Especialidad::find($this->route('especialidad')->id);
                        if ($especialidad && $especialidad->doctores()->where('activo', true)->count() > 0) {
                            $fail('No se puede desactivar una especialidad con doctores activos asignados.');
                        }
                    }
                }
            ],
            'codigo' => [
                'nullable',
                'string',
                'max:10',
                'regex:/^[A-Z0-9]+$/',
                Rule::unique('especialidades', 'codigo')->ignore($especialidadId)
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la especialidad dental es obligatorio.',
            'nombre.unique' => 'Ya existe esta especialidad en la clínica dental.',
            'nombre.max' => 'El nombre de la especialidad no puede exceder los 100 caracteres.',
            'nombre.regex' => 'El nombre solo puede contener letras, espacios, guiones y puntos.',
            'descripcion.max' => 'La descripción no puede exceder los 500 caracteres.',
            'descripcion.regex' => 'La descripción contiene caracteres no permitidos.',
            'codigo.unique' => 'Ya existe una especialidad con este código.',
            'codigo.regex' => 'El código debe contener solo letras mayúsculas y números.',
            'codigo.max' => 'El código no puede exceder los 10 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre de la especialidad',
            'descripcion' => 'descripción',
            'codigo' => 'código de especialidad',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => ucwords(strtolower(strip_tags(trim($this->nombre)))),
            'descripcion' => strip_tags(trim($this->descripcion)),
            'codigo' => strtoupper(strip_tags(trim($this->codigo))),
        ]);
    }
}
