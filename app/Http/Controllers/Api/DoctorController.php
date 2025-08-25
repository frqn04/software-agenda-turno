<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\DoctorContract;
use App\Models\DoctorScheduleSlot;
use App\Services\DoctorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DoctorController extends Controller
{
    protected $doctorService;

    public function __construct(DoctorService $doctorService)
    {
        $this->doctorService = $doctorService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $doctores = Doctor::with(['especialidad', 'user'])
            ->where('activo', true)
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $doctores
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'especialidad_id' => 'required|exists:especialidades,id',
            'matricula' => 'required|string|max:50|unique:doctores,matricula',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'activo' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        $doctor = Doctor::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Doctor creado exitosamente',
            'data' => $doctor->load(['especialidad', 'user'])
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Doctor $doctor)
    {
        return response()->json([
            'success' => true,
            'data' => $doctor->load(['especialidad', 'user', 'contratos', 'horarios'])
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Doctor $doctor)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'especialidad_id' => 'required|exists:especialidades,id',
            'matricula' => 'required|string|max:50|unique:doctores,matricula,' . $doctor->id,
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'activo' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        $doctor->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Doctor actualizado exitosamente',
            'data' => $doctor->load(['especialidad', 'user'])
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Doctor $doctor)
    {
        // Verificar si tiene turnos programados
        if ($doctor->turnos()->where('estado', 'programado')->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el doctor porque tiene turnos programados'
            ], 400);
        }

        $doctor->delete();

        return response()->json([
            'success' => true,
            'message' => 'Doctor eliminado exitosamente'
        ]);
    }

    /**
     * Get doctor contracts
     */
    public function contratos(Doctor $doctor)
    {
        $contratos = $doctor->contratos()
            ->orderBy('fecha_inicio', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $contratos
        ]);
    }

    /**
     * Create doctor contract
     */
    public function storeContrato(Request $request, Doctor $doctor)
    {
        $validator = Validator::make($request->all(), [
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
            'tipo_contrato' => 'required|in:eventual,permanente',
            'activo' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        $contrato = $doctor->contratos()->create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Contrato creado exitosamente',
            'data' => $contrato
        ], 201);
    }

    /**
     * Get doctor schedule slots
     */
    public function horarios(Doctor $doctor)
    {
        $horarios = $doctor->horarios()
            ->orderBy('dia_semana')
            ->orderBy('hora_inicio')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $horarios
        ]);
    }

    /**
     * Create doctor schedule slot
     */
    public function storeHorario(Request $request, Doctor $doctor)
    {
        $validator = Validator::make($request->all(), [
            'dia_semana' => 'required|integer|between:1,7',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
            'turno' => 'required|in:mañana,tarde',
            'activo' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verificar solapamiento de horarios
        $overlap = $doctor->horarios()
            ->where('dia_semana', $request->dia_semana)
            ->where('activo', true)
            ->where(function ($query) use ($request) {
                $query->whereBetween('hora_inicio', [$request->hora_inicio, $request->hora_fin])
                      ->orWhereBetween('hora_fin', [$request->hora_inicio, $request->hora_fin])
                      ->orWhere(function ($q) use ($request) {
                          $q->where('hora_inicio', '<=', $request->hora_inicio)
                            ->where('hora_fin', '>=', $request->hora_fin);
                      });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'El horario se superpone con otro horario existente'
            ], 400);
        }

        $horario = $doctor->horarios()->create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Horario creado exitosamente',
            'data' => $horario
        ], 201);
    }

    /**
     * Get available slots for a doctor on a specific date
     */
    public function slotsDisponibles(Doctor $doctor, Request $request)
    {
        $fecha = $request->get('fecha', now()->format('Y-m-d'));
        
        $slots = $this->doctorService->getAvailableSlots($doctor->id, $fecha);

        return response()->json([
            'success' => true,
            'data' => $slots
        ]);
    }
}
