<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Turno;
use App\Http\Requests\StoreTurnoRequest;
use App\Http\Requests\UpdateTurnoRequest;
use App\Services\TurnoService;
use App\Services\AppointmentValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TurnoController extends Controller
{
    protected $turnoService;
    protected $appointmentValidationService;

    public function __construct(TurnoService $turnoService, AppointmentValidationService $appointmentValidationService)
    {
        $this->turnoService = $turnoService;
        $this->appointmentValidationService = $appointmentValidationService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Turno::with(['paciente', 'doctor.especialidad']);

        // Filtros
        if ($request->has('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
        }

        if ($request->has('paciente_id')) {
            $query->where('paciente_id', $request->paciente_id);
        }

        if ($request->has('fecha')) {
            $query->where('fecha', $request->fecha);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('fecha_desde') && $request->has('fecha_hasta')) {
            $query->whereBetween('fecha', [$request->fecha_desde, $request->fecha_hasta]);
        }

        $turnos = $query->orderBy('fecha', 'desc')
                       ->orderBy('hora_inicio')
                       ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $turnos
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTurnoRequest $request)
    {
        try {
            // Validar disponibilidad del doctor
            $validation = $this->appointmentValidationService->validateAppointment(
                $request->doctor_id,
                $request->fecha,
                $request->hora_inicio,
                $request->hora_fin
            );

            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $validation['message']
                ], 400);
            }

            $turno = $this->turnoService->createTurno($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Turno creado exitosamente',
                'data' => $turno->load(['paciente', 'doctor.especialidad'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el turno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Turno $turno)
    {
        return response()->json([
            'success' => true,
            'data' => $turno->load(['paciente', 'doctor.especialidad'])
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTurnoRequest $request, Turno $turno)
    {
        try {
            // Si se cambia doctor, fecha u hora, validar disponibilidad
            if ($request->has(['doctor_id', 'fecha', 'hora_inicio', 'hora_fin'])) {
                $validation = $this->appointmentValidationService->validateAppointment(
                    $request->doctor_id,
                    $request->fecha,
                    $request->hora_inicio,
                    $request->hora_fin,
                    $turno->id // Excluir el turno actual de la validación
                );

                if (!$validation['valid']) {
                    return response()->json([
                        'success' => false,
                        'message' => $validation['message']
                    ], 400);
                }
            }

            $turno = $this->turnoService->updateTurno($turno, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Turno actualizado exitosamente',
                'data' => $turno->load(['paciente', 'doctor.especialidad'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el turno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Turno $turno)
    {
        try {
            $this->turnoService->deleteTurno($turno);

            return response()->json([
                'success' => true,
                'message' => 'Turno eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el turno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel appointment
     */
    public function cancelar(Turno $turno, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'motivo_cancelacion' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($turno->estado === 'cancelado') {
            return response()->json([
                'success' => false,
                'message' => 'El turno ya está cancelado'
            ], 400);
        }

        if ($turno->estado === 'realizado') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede cancelar un turno que ya fue realizado'
            ], 400);
        }

        $turno->update([
            'estado' => 'cancelado',
            'motivo_cancelacion' => $request->motivo_cancelacion,
            'fecha_cancelacion' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Turno cancelado exitosamente',
            'data' => $turno->load(['paciente', 'doctor.especialidad'])
        ]);
    }

    /**
     * Mark appointment as completed
     */
    public function realizar(Turno $turno, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'observaciones' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($turno->estado === 'realizado') {
            return response()->json([
                'success' => false,
                'message' => 'El turno ya está marcado como realizado'
            ], 400);
        }

        if ($turno->estado === 'cancelado') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede realizar un turno cancelado'
            ], 400);
        }

        $turno->update([
            'estado' => 'realizado',
            'observaciones' => $request->observaciones,
            'fecha_realizacion' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Turno marcado como realizado',
            'data' => $turno->load(['paciente', 'doctor.especialidad'])
        ]);
    }

    /**
     * Confirm appointment
     */
    public function confirmar(Turno $turno)
    {
        if ($turno->estado !== 'programado') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden confirmar turnos en estado programado'
            ], 400);
        }

        $turno->update([
            'estado' => 'confirmado',
            'fecha_confirmacion' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Turno confirmado exitosamente',
            'data' => $turno->load(['paciente', 'doctor.especialidad'])
        ]);
    }

    /**
     * Get available time slots for a doctor on a date
     */
    public function slotsDisponibles(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'doctor_id' => 'required|exists:doctores,id',
            'fecha' => 'required|date|after_or_equal:today'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        $slots = $this->turnoService->getAvailableSlots(
            $request->doctor_id,
            $request->fecha
        );

        return response()->json([
            'success' => true,
            'data' => $slots
        ]);
    }
}
