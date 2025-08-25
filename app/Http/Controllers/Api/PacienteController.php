<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Paciente;
use App\Models\HistoriaClinica;
use App\Models\Evolucion;
use App\Http\Requests\StorePacienteRequest;
use App\Http\Requests\UpdatePacienteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PacienteController extends Controller
{
    public function index()
    {
        $pacientes = Paciente::where('activo', true)
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $pacientes
        ]);
    }

    public function store(StorePacienteRequest $request)
    {
        $paciente = Paciente::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Paciente creado exitosamente',
            'data' => $paciente
        ], 201);
    }

    public function show(Paciente $paciente)
    {
        return response()->json([
            'success' => true,
            'data' => $paciente
        ]);
    }

    public function update(UpdatePacienteRequest $request, Paciente $paciente)
    {
        $paciente->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Paciente actualizado exitosamente',
            'data' => $paciente
        ]);
    }

    public function destroy(Paciente $paciente)
    {
        $paciente->delete();

        return response()->json([
            'success' => true,
            'message' => 'Paciente eliminado exitosamente'
        ]);
    }

    public function historiaClinica(Paciente $paciente)
    {
        $historiaClinica = HistoriaClinica::where('paciente_id', $paciente->id)
            ->with(['evoluciones' => function ($query) {
                $query->orderBy('fecha', 'desc');
            }])
            ->first();

        return response()->json([
            'success' => true,
            'paciente' => $paciente,
            'historia_clinica' => $historiaClinica
        ]);
    }

    public function storeHistoriaClinica(Request $request, Paciente $paciente)
    {
        $validator = Validator::make($request->all(), [
            'doctor_id' => 'required|exists:doctores,id',
            'fecha_apertura' => 'required|date',
            'observaciones_generales' => 'nullable|string',
            'antecedentes_personales' => 'nullable|string',
            'antecedentes_familiares' => 'nullable|string',
            'medicamentos_actuales' => 'nullable|string',
            'alergias' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        $historiaClinica = HistoriaClinica::create([
            'paciente_id' => $paciente->id,
            ...$request->all()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Historia clínica creada exitosamente',
            'data' => $historiaClinica
        ], 201);
    }

    public function storeEvolucion(Request $request, Paciente $paciente)
    {
        $validator = Validator::make($request->all(), [
            'historia_clinica_id' => 'required|exists:historias_clinicas,id',
            'fecha' => 'required|date',
            'evolucion' => 'required|string',
            'tratamiento' => 'nullable|string',
            'observaciones' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        $evolucion = Evolucion::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Evolución agregada exitosamente',
            'data' => $evolucion
        ], 201);
    }
}
