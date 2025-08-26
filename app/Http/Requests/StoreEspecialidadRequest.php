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
            'nombre' => [
                'required',
                'string',
                'max:100',
                'unique:especialidades,nombre',
                'regex:/^[a-zA-ZÀ-ÿ\s\-\.]+$/',
                function ($attribute, $value, $fail) {
                    // Validar especialidades dentales comunes
                    $especialidadesDentales = [
                        'odontologia general', 'endodoncia', 'periodoncia', 'ortodoncia',
                        'cirugia oral', 'implantologia', 'odontopediatria', 'protesis dental',
                        'estetica dental', 'radiologia oral'
                    ];
                    
                    if (!in_array(strtolower($value), $especialidadesDentales)) {
                        \Log::info("Nueva especialidad dental registrada: {$value}");
                    }
                }
            ],
            'descripcion' => [
                'nullable',
                'string',
                'max:500',
                'regex:/^[a-zA-ZÀ-ÿ0-9\s\.\,\-\(\)]+$/'
            ],
            'activo' => 'boolean',
            'codigo' => [
                'nullable',
                'string',
                'max:10',
                'unique:especialidades,codigo',
                'regex:/^[A-Z0-9]+$/'
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
