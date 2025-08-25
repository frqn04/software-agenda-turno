<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Especialidad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EspecialidadController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $especialidades = Especialidad::where('activo', true)
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $especialidades
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:100|unique:especialidades,nombre',
            'descripcion' => 'nullable|string|max:500',
            'activo' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        $especialidad = Especialidad::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Especialidad creada exitosamente',
            'data' => $especialidad
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Especialidad $especialidad)
    {
        return response()->json([
            'success' => true,
            'data' => $especialidad
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Especialidad $especialidad)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:100|unique:especialidades,nombre,' . $especialidad->id,
            'descripcion' => 'nullable|string|max:500',
            'activo' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        $especialidad->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Especialidad actualizada exitosamente',
            'data' => $especialidad
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Especialidad $especialidad)
    {
        // Verificar si tiene doctores asociados
        if ($especialidad->doctores()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la especialidad porque tiene doctores asociados'
            ], 400);
        }

        $especialidad->delete();

        return response()->json([
            'success' => true,
            'message' => 'Especialidad eliminada exitosamente'
        ]);
    }
}
