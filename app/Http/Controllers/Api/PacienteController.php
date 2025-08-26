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

/**
 * Controlador de pacientes para clínica dental
 * Maneja registro, actualización y historia clínica dental
 * Solo accesible por personal autorizado de la clínica
 */
class PacienteController extends Controller
{
    /**
     * Listar pacientes de la clínica dental con paginación
     */
    public function index(Request $request)
    {
        return $this->handleMedicalAction(function () use ($request) {
            $query = Paciente::where('activo', true);

            // Filtros de búsqueda para clínica dental
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('apellido', 'like', "%{$search}%")
                      ->orWhere('dni', 'like', "%{$search}%")
                      ->orWhere('telefono', 'like', "%{$search}%");
                });
            }

            if ($request->has('obra_social')) {
                $query->where('obra_social', 'like', "%{$request->obra_social}%");
            }

            // Ordenamiento
            $query->orderBy('apellido')->orderBy('nombre');

            // Paginación
            $perPage = min($request->get('per_page', 15), 50); // Máximo 50 por página
            $pacientes = $query->paginate($perPage);

            $this->logMedicalActivity('Consulta de lista de pacientes', 'pacientes', null, $request, [
                'total_results' => $pacientes->total(),
                'search_term' => $request->search,
                'filters' => $request->only(['obra_social']),
            ]);

            return $this->paginatedResponse($pacientes, 'Lista de pacientes de la clínica obtenida');
        }, 'consulta de pacientes');
    }

    /**
     * Registrar nuevo paciente en la clínica dental
     */
    public function store(StorePacienteRequest $request)
    {
        return $this->handleMedicalAction(function () use ($request) {
            // Solo secretarias y admins pueden registrar pacientes
            $user = $request->user();
            if (!in_array($user->rol, ['admin', 'secretaria'])) {
                return $this->forbiddenResponse('Solo administradores y secretarias pueden registrar pacientes');
            }

            $paciente = Paciente::create($request->validated());

            $this->logMedicalActivity('Nuevo paciente registrado en clínica', 'pacientes', $paciente->id, $request, [
                'patient_dni' => $paciente->dni,
                'patient_name' => $paciente->nombre . ' ' . $paciente->apellido,
                'registered_by' => $user->name,
                'obra_social' => $paciente->obra_social,
            ]);

            return $this->successResponse(
                $paciente->load(['turnos' => function ($query) {
                    $query->where('fecha', '>=', now())->orderBy('fecha');
                }]),
                'Paciente registrado exitosamente en la clínica dental',
                201
            );
        }, 'registro de nuevo paciente');
    }

    /**
     * Mostrar información detallada del paciente
     */
    public function show(Paciente $paciente)
    {
        return $this->handleMedicalAction(function () use ($paciente) {
            $pacienteData = $paciente->load([
                'turnos' => function ($query) {
                    $query->with(['doctor.especialidad'])
                          ->orderBy('fecha', 'desc')
                          ->limit(10);
                },
                'historiaClinica.evoluciones' => function ($query) {
                    $query->orderBy('fecha', 'desc')->limit(5);
                }
            ]);

            $this->logMedicalActivity('Consulta de información de paciente', 'pacientes', $paciente->id, request(), [
                'patient_dni' => $paciente->dni,
                'view_type' => 'detailed',
            ]);

            return $this->successResponse($pacienteData, 'Información del paciente obtenida');
        }, 'consulta de información de paciente');
    }

    /**
     * Actualizar información del paciente
     */
    public function update(UpdatePacienteRequest $request, Paciente $paciente)
    {
        return $this->handleMedicalAction(function () use ($request, $paciente) {
            $user = $request->user();
            
            // Solo secretarias y admins pueden actualizar pacientes
            if (!in_array($user->rol, ['admin', 'secretaria'])) {
                return $this->forbiddenResponse('Solo administradores y secretarias pueden actualizar información de pacientes');
            }

            $originalData = $paciente->toArray();
            $paciente->update($request->validated());

            // Log de cambios importantes
            $changes = $paciente->getChanges();
            unset($changes['updated_at']);

            $this->logMedicalActivity('Información de paciente actualizada', 'pacientes', $paciente->id, $request, [
                'patient_dni' => $paciente->dni,
                'updated_by' => $user->name,
                'fields_changed' => array_keys($changes),
                'changes' => $changes,
            ]);

            return $this->successResponse(
                $paciente->fresh(),
                'Información del paciente actualizada exitosamente'
            );
        }, 'actualización de información de paciente');
    }

    /**
     * Desactivar paciente (soft delete médico)
     */
    public function destroy(Paciente $paciente)
    {
        return $this->handleMedicalAction(function () use ($paciente) {
            $user = request()->user();
            
            // Solo admins pueden desactivar pacientes
            if ($user->rol !== 'admin') {
                return $this->forbiddenResponse('Solo administradores pueden desactivar pacientes');
            }

            // Verificar que no tenga turnos pendientes
            $turnosPendientes = $paciente->turnos()->where('fecha', '>=', now())->count();
            if ($turnosPendientes > 0) {
                return $this->errorResponse(
                    'No se puede desactivar un paciente con turnos pendientes. Cancele los turnos primero.',
                    422
                );
            }

            $paciente->update(['activo' => false]);

            $this->logMedicalActivity('Paciente desactivado en clínica', 'pacientes', $paciente->id, request(), [
                'patient_dni' => $paciente->dni,
                'patient_name' => $paciente->nombre . ' ' . $paciente->apellido,
                'deactivated_by' => $user->name,
                'reason' => 'Administrative deactivation',
            ]);

            return $this->successResponse(null, 'Paciente desactivado exitosamente');
        }, 'desactivación de paciente');
    }

    /**
     * Obtener historia clínica dental del paciente
     */
    public function historiaClinica(Paciente $paciente)
    {
        return $this->handleMedicalAction(function () use ($paciente) {
            $user = request()->user();
            
            // Solo doctores, admins y secretarias pueden ver historias clínicas
            if (!in_array($user->rol, ['admin', 'doctor', 'secretaria'])) {
                return $this->forbiddenResponse('Sin permisos para acceder a historia clínica médica');
            }

            $historiaClinica = HistoriaClinica::where('paciente_id', $paciente->id)
                ->with(['evoluciones' => function ($query) {
                    $query->with('doctor')->orderBy('fecha', 'desc');
                }, 'doctor'])
                ->first();

            $this->logMedicalActivity('Acceso a historia clínica dental', 'pacientes', $paciente->id, request(), [
                'patient_dni' => $paciente->dni,
                'accessed_by' => $user->name,
                'access_type' => 'medical_history_view',
                'has_history' => $historiaClinica !== null,
            ]);

            return $this->successResponse([
                'paciente' => $paciente,
                'historia_clinica' => $historiaClinica,
                'total_evoluciones' => $historiaClinica?->evoluciones->count() ?? 0,
            ], 'Historia clínica dental obtenida');
        }, 'consulta de historia clínica');
    }

    /**
     * Crear historia clínica dental para nuevo paciente
     */
    public function storeHistoriaClinica(Request $request, Paciente $paciente)
    {
        return $this->handleMedicalAction(function () use ($request, $paciente) {
            $user = $request->user();
            
            // Solo doctores pueden crear historias clínicas
            if ($user->rol !== 'doctor') {
                return $this->forbiddenResponse('Solo doctores pueden crear historias clínicas');
            }

            $validator = Validator::make($request->all(), [
                'fecha_apertura' => 'required|date|before_or_equal:today',
                'observaciones_generales' => 'nullable|string|max:2000',
                'antecedentes_personales' => 'nullable|string|max:1500',
                'antecedentes_familiares' => 'nullable|string|max:1500',
                'medicamentos_actuales' => 'nullable|string|max:1000',
                'alergias' => 'nullable|string|max:1000',
                'patologias_previas' => 'nullable|string|max:1500',
                'habitos' => 'nullable|string|max:1000',
            ], [
                'fecha_apertura.required' => 'La fecha de apertura es obligatoria',
                'fecha_apertura.before_or_equal' => 'La fecha no puede ser futura',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            // Verificar que no exista ya una historia clínica
            if (HistoriaClinica::where('paciente_id', $paciente->id)->exists()) {
                return $this->errorResponse('El paciente ya tiene una historia clínica registrada', 422);
            }

            $historiaClinica = HistoriaClinica::create([
                'paciente_id' => $paciente->id,
                'doctor_id' => $user->doctor_id,
                ...$request->all()
            ]);

            $this->logMedicalActivity('Historia clínica dental creada', 'historias_clinicas', $historiaClinica->id, $request, [
                'patient_dni' => $paciente->dni,
                'doctor_id' => $user->doctor_id,
                'created_by' => $user->name,
            ]);

            return $this->successResponse(
                $historiaClinica->load(['doctor', 'paciente']),
                'Historia clínica dental creada exitosamente',
                201
            );
        }, 'creación de historia clínica');
    }

    /**
     * Agregar evolución dental al paciente
     */
    public function storeEvolucion(Request $request, Paciente $paciente)
    {
        return $this->handleMedicalAction(function () use ($request, $paciente) {
            $user = $request->user();
            
            // Solo doctores pueden agregar evoluciones
            if ($user->rol !== 'doctor') {
                return $this->forbiddenResponse('Solo doctores pueden registrar evoluciones dentales');
            }

            $validator = Validator::make($request->all(), [
                'historia_clinica_id' => 'required|exists:historias_clinicas,id',
                'fecha' => 'required|date|before_or_equal:today',
                'evolucion' => 'required|string|max:3000',
                'tratamiento' => 'nullable|string|max:2000',
                'observaciones' => 'nullable|string|max:1500',
                'diagnostico' => 'nullable|string|max:1000',
                'proximo_control' => 'nullable|date|after:today',
                'piezas_dentales' => 'nullable|string|max:500',
            ], [
                'historia_clinica_id.required' => 'Debe especificar la historia clínica',
                'historia_clinica_id.exists' => 'La historia clínica no existe',
                'fecha.required' => 'La fecha de evolución es obligatoria',
                'fecha.before_or_equal' => 'La fecha no puede ser futura',
                'evolucion.required' => 'Debe ingresar la evolución dental',
                'evolucion.max' => 'La evolución no puede exceder 3000 caracteres',
                'proximo_control.after' => 'El próximo control debe ser en el futuro',
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            $evolucion = Evolucion::create([
                'doctor_id' => $user->doctor_id,
                ...$request->all()
            ]);

            $this->logMedicalActivity('Evolución dental registrada', 'evoluciones', $evolucion->id, $request, [
                'patient_dni' => $paciente->dni,
                'doctor_id' => $user->doctor_id,
                'historia_clinica_id' => $request->historia_clinica_id,
                'treatment_provided' => !empty($request->tratamiento),
                'next_control_date' => $request->proximo_control,
            ]);

            return $this->successResponse(
                $evolucion->load(['doctor']),
                'Evolución dental registrada exitosamente',
                201
            );
        }, 'registro de evolución dental');
    }
}
