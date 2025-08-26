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
use Carbon\Carbon;

/**
 * Controlador de turnos para clínica dental
 * Maneja programación, cancelación y gestión de citas dentales
 * Solo accesible por personal autorizado de la clínica
 */
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
     * Listar turnos de la clínica dental con filtros avanzados
     */
    public function index(Request $request)
    {
        return $this->handleMedicalAction(function () use ($request) {
            $user = $request->user();
            $query = Turno::with(['paciente', 'doctor.especialidad']);

            // Filtros por rol de usuario
            if ($user->rol === 'doctor' && $user->doctor_id) {
                // Los doctores solo ven sus propios turnos
                $query->where('doctor_id', $user->doctor_id);
            }

            // Filtros de búsqueda
            if ($request->has('doctor_id') && in_array($user->rol, ['admin', 'secretaria'])) {
                $query->where('doctor_id', $request->doctor_id);
            }

            if ($request->has('paciente_id')) {
                $query->where('paciente_id', $request->paciente_id);
            }

            if ($request->has('fecha')) {
                $query->where('fecha', $request->fecha);
            } else {
                // Por defecto mostrar turnos desde hoy
                $query->where('fecha', '>=', now()->toDateString());
            }

            if ($request->has('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->has('especialidad_id')) {
                $query->whereHas('doctor', function ($q) use ($request) {
                    $q->where('especialidad_id', $request->especialidad_id);
                });
            }

            if ($request->has('fecha_desde') && $request->has('fecha_hasta')) {
                $query->whereBetween('fecha', [$request->fecha_desde, $request->fecha_hasta]);
            }

            // Búsqueda por paciente
            if ($request->has('search_paciente')) {
                $search = $request->search_paciente;
                $query->whereHas('paciente', function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('apellido', 'like', "%{$search}%")
                      ->orWhere('dni', 'like', "%{$search}%");
                });
            }

            // Ordenamiento específico para clínica dental
            $query->orderBy('fecha', 'asc')
                  ->orderBy('hora_inicio', 'asc');

            $perPage = min($request->get('per_page', 20), 50);
            $turnos = $query->paginate($perPage);

            $this->logMedicalActivity('Consulta de agenda de turnos', 'turnos', null, $request, [
                'filters_applied' => $request->only(['doctor_id', 'fecha', 'estado', 'especialidad_id']),
                'total_results' => $turnos->total(),
                'user_role' => $user->rol,
            ]);

            return $this->paginatedResponse($turnos, 'Agenda de turnos de la clínica obtenida');
        }, 'consulta de agenda');
    }

    /**
     * Programar nuevo turno dental
     */
    public function store(StoreTurnoRequest $request)
    {
        return $this->handleMedicalAction(function () use ($request) {
            $user = $request->user();
            
            // Solo secretarias y admins pueden programar turnos
            if (!in_array($user->rol, ['admin', 'secretaria'])) {
                return $this->forbiddenResponse('Solo administradores y secretarias pueden programar turnos');
            }

            // Validar horarios de clínica dental
            if (!$this->validateBusinessHours()) {
                return $this->outsideBusinessHoursResponse();
            }

            // Validar disponibilidad del doctor
            $validation = $this->appointmentValidationService->validateAppointment(
                $request->doctor_id,
                $request->fecha,
                $request->hora_inicio,
                $request->hora_fin
            );

            if (!$validation['valid']) {
                return $this->errorResponse($validation['message'], 422);
            }

            // Validar que la fecha no sea domingo
            $fecha = Carbon::parse($request->fecha);
            if ($fecha->isSunday()) {
                return $this->errorResponse('No se pueden programar turnos los domingos en la clínica', 422);
            }

            $turno = $this->turnoService->createTurno($request->validated());

            $this->logMedicalActivity('Nuevo turno dental programado', 'turnos', $turno->id, $request, [
                'patient_dni' => $turno->paciente->dni,
                'doctor_id' => $turno->doctor_id,
                'appointment_date' => $turno->fecha,
                'appointment_time' => $turno->hora_inicio,
                'scheduled_by' => $user->name,
                'motivo' => $turno->motivo_consulta,
            ]);

            return $this->successResponse(
                $turno->load(['paciente', 'doctor.especialidad']),
                'Turno dental programado exitosamente',
                201
            );
        }, 'programación de turno dental');
    }

    /**
     * Mostrar detalles del turno dental
     */
    public function show(Turno $turno)
    {
        return $this->handleMedicalAction(function () use ($turno) {
            $user = request()->user();
            
            // Los doctores solo pueden ver sus propios turnos
            if ($user->rol === 'doctor' && $user->doctor_id !== $turno->doctor_id) {
                return $this->forbiddenResponse('Solo puede ver sus propios turnos');
            }

            $turnoData = $turno->load([
                'paciente.historiaClinica', 
                'doctor.especialidad'
            ]);

            $this->logMedicalActivity('Consulta de detalle de turno', 'turnos', $turno->id, request(), [
                'patient_dni' => $turno->paciente->dni,
                'appointment_date' => $turno->fecha,
                'viewed_by' => $user->name,
            ]);

            return $this->successResponse($turnoData, 'Detalles del turno dental obtenidos');
        }, 'consulta de turno');
    }

    /**
     * Actualizar turno dental existente
     */
    public function update(UpdateTurnoRequest $request, Turno $turno)
    {
        return $this->handleMedicalAction(function () use ($request, $turno) {
            $user = $request->user();
            
            // Validar permisos según rol
            if ($user->rol === 'doctor' && $user->doctor_id !== $turno->doctor_id) {
                return $this->forbiddenResponse('Solo puede modificar sus propios turnos');
            }
            
            if ($user->rol === 'operador') {
                return $this->forbiddenResponse('Operadores no pueden modificar turnos');
            }

            // No permitir modificar turnos ya realizados
            if ($turno->estado === 'realizado') {
                return $this->errorResponse('No se puede modificar un turno ya realizado', 422);
            }

            $originalData = $turno->toArray();

            // Si se cambian datos críticos, validar disponibilidad
            if ($request->hasAny(['doctor_id', 'fecha', 'hora_inicio', 'hora_fin'])) {
                $validation = $this->appointmentValidationService->validateAppointment(
                    $request->doctor_id ?? $turno->doctor_id,
                    $request->fecha ?? $turno->fecha,
                    $request->hora_inicio ?? $turno->hora_inicio,
                    $request->hora_fin ?? $turno->hora_fin,
                    $turno->id
                );

                if (!$validation['valid']) {
                    return $this->errorResponse($validation['message'], 422);
                }
            }

            $turno = $this->turnoService->updateTurno($turno, $request->validated());

            // Log de cambios importantes
            $changes = array_diff_assoc($turno->toArray(), $originalData);
            unset($changes['updated_at']);

            $this->logMedicalActivity('Turno dental actualizado', 'turnos', $turno->id, $request, [
                'patient_dni' => $turno->paciente->dni,
                'updated_by' => $user->name,
                'fields_changed' => array_keys($changes),
                'changes' => $changes,
            ]);

            return $this->successResponse(
                $turno->load(['paciente', 'doctor.especialidad']),
                'Turno dental actualizado exitosamente'
            );
        }, 'actualización de turno dental');
    }

    /**
     * Cancelar turno dental (solo admins)
     */
    public function destroy(Turno $turno)
    {
        return $this->handleMedicalAction(function () use ($turno) {
            $user = request()->user();
            
            // Solo admins pueden eliminar turnos definitivamente
            if ($user->rol !== 'admin') {
                return $this->forbiddenResponse('Solo administradores pueden eliminar turnos definitivamente');
            }

            if ($turno->estado === 'realizado') {
                return $this->errorResponse('No se puede eliminar un turno ya realizado', 422);
            }

            $this->logMedicalActivity('Turno dental eliminado definitivamente', 'turnos', $turno->id, request(), [
                'patient_dni' => $turno->paciente->dni,
                'appointment_date' => $turno->fecha,
                'deleted_by' => $user->name,
                'original_state' => $turno->estado,
                'security_level' => 'high',
            ]);

            $this->turnoService->deleteTurno($turno);

            return $this->successResponse(null, 'Turno dental eliminado definitivamente');
        }, 'eliminación definitiva de turno');
    }

    /**
     * Cancelar turno dental
     */
    public function cancelar(Turno $turno, Request $request)
    {
        return $this->handleMedicalAction(function () use ($turno, $request) {
            $user = $request->user();
            
            // Solo secretarias y admins pueden cancelar turnos
            if (!in_array($user->rol, ['admin', 'secretaria'])) {
                return $this->forbiddenResponse('Solo administradores y secretarias pueden cancelar turnos');
            }

            $validator = Validator::make($request->all(), [
                'motivo_cancelacion' => 'required|string|max:500',
                'notificar_paciente' => 'boolean',
            ], [
                'motivo_cancelacion.required' => 'Debe especificar el motivo de cancelación del turno dental',
                'motivo_cancelacion.max' => 'El motivo no puede exceder 500 caracteres',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            if ($turno->estado === 'cancelado') {
                return $this->errorResponse('El turno dental ya está cancelado', 422);
            }

            if ($turno->estado === 'realizado') {
                return $this->errorResponse('No se puede cancelar un turno dental que ya fue realizado', 422);
            }

            $turno->update([
                'estado' => 'cancelado',
                'motivo_cancelacion' => $request->motivo_cancelacion,
                'fecha_cancelacion' => now(),
                'cancelado_por' => $user->id,
            ]);

            $this->logMedicalActivity('Turno dental cancelado', 'turnos', $turno->id, $request, [
                'patient_dni' => $turno->paciente->dni,
                'appointment_date' => $turno->fecha,
                'cancelled_by' => $user->name,
                'cancellation_reason' => $request->motivo_cancelacion,
                'notify_patient' => $request->boolean('notificar_paciente', false),
            ]);

            return $this->successResponse(
                $turno->load(['paciente', 'doctor.especialidad']),
                'Turno dental cancelado exitosamente'
            );
        }, 'cancelación de turno dental');
    }

    /**
     * Marcar turno como realizado (solo doctores)
     */
    public function realizar(Turno $turno, Request $request)
    {
        return $this->handleMedicalAction(function () use ($turno, $request) {
            $user = $request->user();
            
            // Solo el doctor asignado puede marcar como realizado
            if ($user->rol !== 'doctor' || $user->doctor_id !== $turno->doctor_id) {
                return $this->forbiddenResponse('Solo el doctor asignado puede marcar el turno como realizado');
            }

            $validator = Validator::make($request->all(), [
                'observaciones_consulta' => 'nullable|string|max:2000',
                'tratamiento_realizado' => 'nullable|string|max:1500',
                'proxima_cita' => 'nullable|date|after:today',
                'precio_consulta' => 'nullable|numeric|min:0|max:999999.99',
            ], [
                'observaciones_consulta.max' => 'Las observaciones no pueden exceder 2000 caracteres',
                'tratamiento_realizado.max' => 'El tratamiento no puede exceder 1500 caracteres',
                'proxima_cita.after' => 'La próxima cita debe ser en el futuro',
                'precio_consulta.numeric' => 'El precio debe ser un número válido',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            if ($turno->estado === 'realizado') {
                return $this->errorResponse('El turno dental ya está marcado como realizado', 422);
            }

            if ($turno->estado === 'cancelado') {
                return $this->errorResponse('No se puede realizar un turno dental cancelado', 422);
            }

            $turno->update([
                'estado' => 'realizado',
                'observaciones_consulta' => $request->observaciones_consulta,
                'tratamiento_realizado' => $request->tratamiento_realizado,
                'fecha_realizacion' => now(),
                'proxima_cita' => $request->proxima_cita,
                'precio_consulta' => $request->precio_consulta,
            ]);

            $this->logMedicalActivity('Consulta dental realizada', 'turnos', $turno->id, $request, [
                'patient_dni' => $turno->paciente->dni,
                'doctor_id' => $user->doctor_id,
                'consultation_date' => $turno->fecha,
                'treatment_provided' => !empty($request->tratamiento_realizado),
                'next_appointment' => $request->proxima_cita,
                'consultation_fee' => $request->precio_consulta,
            ]);

            return $this->successResponse(
                $turno->load(['paciente', 'doctor.especialidad']),
                'Consulta dental completada exitosamente'
            );
        }, 'finalización de consulta dental');
    }

    /**
     * Confirmar turno dental (solo secretarias y admins)
     */
    public function confirmar(Turno $turno, Request $request)
    {
        return $this->handleMedicalAction(function () use ($turno, $request) {
            $user = $request->user();
            
            // Solo secretarias y admins pueden confirmar turnos
            if (!in_array($user->rol, ['admin', 'secretaria'])) {
                return $this->forbiddenResponse('Solo administradores y secretarias pueden confirmar turnos');
            }

            if ($turno->estado === 'confirmado') {
                return $this->errorResponse('El turno dental ya está confirmado', 422);
            }

            if ($turno->estado === 'cancelado') {
                return $this->errorResponse('No se puede confirmar un turno dental cancelado', 422);
            }

            if ($turno->estado === 'realizado') {
                return $this->errorResponse('El turno dental ya fue realizado', 422);
            }

            $turno->update([
                'estado' => 'confirmado',
                'fecha_confirmacion' => now(),
                'confirmado_por' => $user->id,
            ]);

            $this->logMedicalActivity('Turno dental confirmado', 'turnos', $turno->id, $request, [
                'patient_dni' => $turno->paciente->dni,
                'appointment_date' => $turno->fecha,
                'confirmed_by' => $user->name,
                'confirmation_date' => now(),
            ]);

            return $this->successResponse(
                $turno->load(['paciente', 'doctor.especialidad']),
                'Turno dental confirmado exitosamente'
            );
        }, 'confirmación de turno dental');
    }

    /**
     * Obtener slots disponibles para turnos dentales
     */
    public function slotsDisponibles(Request $request)
    {
        return $this->handleMedicalAction(function () use ($request) {
            $user = $request->user();
            
            // Solo personal autorizado puede ver slots disponibles
            if (!in_array($user->rol, ['admin', 'secretaria', 'operador'])) {
                return $this->forbiddenResponse('No tiene permisos para consultar la disponibilidad de turnos');
            }

            $validator = Validator::make($request->all(), [
                'doctor_id' => 'required|exists:users,doctor_id',
                'fecha' => 'required|date|after_or_equal:today',
                'especialidad_id' => 'nullable|exists:especialidades,id',
            ], [
                'doctor_id.required' => 'Debe especificar el doctor',
                'doctor_id.exists' => 'El doctor especificado no existe',
                'fecha.required' => 'Debe especificar la fecha',
                'fecha.after_or_equal' => 'La fecha debe ser hoy o posterior',
                'especialidad_id.exists' => 'La especialidad especificada no existe',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            $fecha = Carbon::parse($request->fecha);
            
            // No permitir turnos los domingos
            if ($fecha->isSunday()) {
                return $this->errorResponse('No se programan turnos dentales los domingos', 422);
            }

            // Verificar que la fecha esté dentro del horario de atención
            if (!$this->isBusinessDay($fecha)) {
                return $this->errorResponse('La fecha seleccionada no está disponible para turnos dentales', 422);
            }

            try {
                $slots = $this->turnoService->getAvailableSlots(
                    $request->doctor_id,
                    $request->fecha,
                    $request->especialidad_id
                );

                $this->logMedicalActivity('Consulta de slots disponibles', 'turnos', null, $request, [
                    'doctor_id' => $request->doctor_id,
                    'requested_date' => $request->fecha,
                    'specialty_id' => $request->especialidad_id,
                    'slots_found' => count($slots),
                    'consulted_by' => $user->name,
                ]);

                return $this->successResponse($slots, 'Slots disponibles obtenidos exitosamente');

            } catch (\Exception $e) {
                Log::error('Error obteniendo slots disponibles para turnos dentales', [
                    'error' => $e->getMessage(),
                    'doctor_id' => $request->doctor_id,
                    'fecha' => $request->fecha,
                    'user_id' => $user->id,
                    'context' => 'dental_appointment_slots',
                ]);

                return $this->errorResponse('Error al obtener slots disponibles: ' . $e->getMessage());
            }
        }, 'consulta de slots disponibles');
    }

    /**
     * Verificar si es día laborable en la clínica dental
     */
    private function isBusinessDay(Carbon $date): bool
    {
        // Lunes a Viernes: 8:00-18:00, Sábados: 8:00-13:00, Domingos: cerrado
        if ($date->isSunday()) {
            return false;
        }

        $hour = $date->hour;
        
        if ($date->isSaturday()) {
            return $hour >= 8 && $hour < 13;
        }
        
        // Lunes a Viernes
        return $hour >= 8 && $hour < 18;
    }
}
