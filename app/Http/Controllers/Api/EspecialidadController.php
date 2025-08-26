<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Especialidad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Controlador para gestión de especialidades dentales
 * 
 * Sistema interno de clínica dental - Solo personal administrativo autorizado
 * Gestiona especialidades odontológicas con auditoría completa
 * 
 * @package App\Http\Controllers\Api
 * @author Sistema Dental Clínico
 * @version 2.0
 */
class EspecialidadController extends Controller
{
    /**
     * Constructor del controlador de especialidades dentales
     */
    public function __construct()
    {
        // Middleware de autenticación médica requerido
        $this->middleware('auth:sanctum');
        
        // Solo personal administrativo puede gestionar especialidades (excepto consulta)
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            
            // Para métodos de consulta (GET), permitir más roles
            if ($request->isMethod('get')) {
                if (!in_array($user->rol, ['admin', 'secretaria', 'operador', 'doctor'])) {
                    return $this->forbiddenResponse('Acceso restringido: Solo personal autorizado');
                }
            } else {
                // Para métodos de modificación, solo admin
                if ($user->rol !== 'admin') {
                    return $this->forbiddenResponse('Solo administradores pueden gestionar especialidades');
                }
            }
            
            return $next($request);
        });
    }

    /**
     * Listar especialidades dentales activas
     */
    public function index(Request $request)
    {
        return $this->handleMedicalAction(function () use ($request) {
            $user = $request->user();
            
            $query = Especialidad::where('activo', true);
            
            // Filtro de búsqueda
            if ($request->filled('buscar')) {
                $buscar = $request->buscar;
                $query->where(function ($q) use ($buscar) {
                    $q->where('nombre', 'like', "%{$buscar}%")
                      ->orWhere('descripcion', 'like', "%{$buscar}%");
                });
            }
            
            // Incluir conteo de doctores si se solicita
            if ($request->boolean('with_doctors_count')) {
                $query->withCount(['doctores' => function ($q) {
                    $q->where('activo', true);
                }]);
            }
            
            $especialidades = $query->orderBy('nombre')->get();
            
            $this->logMedicalActivity('Consulta de especialidades dentales', 'especialidades', null, $request, [
                'total_specialties' => $especialidades->count(),
                'search_term' => $request->buscar,
                'include_doctors_count' => $request->boolean('with_doctors_count'),
                'consulted_by' => $user->name,
            ]);
            
            return $this->successResponse($especialidades, 'Especialidades dentales obtenidas exitosamente');
        }, 'consulta de especialidades dentales');
    }

    /**
     * Crear nueva especialidad dental (solo administradores)
     */
    public function store(Request $request)
    {
        return $this->handleMedicalAction(function () use ($request) {
            $user = $request->user();
            
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:100|unique:especialidades,nombre|regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s\-]+$/',
                'descripcion' => 'nullable|string|max:1000',
                'codigo' => 'nullable|string|max:10|unique:especialidades,codigo|regex:/^[A-Z0-9]+$/',
                'duracion_consulta_default' => 'nullable|integer|min:15|max:120', // minutos
                'precio_base' => 'nullable|numeric|min:0|max:999999.99',
                'requiere_historia_clinica' => 'boolean',
                'activo' => 'boolean',
            ], [
                'nombre.required' => 'El nombre de la especialidad es obligatorio',
                'nombre.unique' => 'Ya existe una especialidad con este nombre',
                'nombre.regex' => 'El nombre solo puede contener letras, espacios y guiones',
                'codigo.unique' => 'Ya existe una especialidad con este código',
                'codigo.regex' => 'El código debe contener solo letras mayúsculas y números',
                'duracion_consulta_default.min' => 'La duración mínima de consulta es 15 minutos',
                'duracion_consulta_default.max' => 'La duración máxima de consulta es 120 minutos',
                'precio_base.max' => 'El precio base no puede exceder $999,999.99',
            ]);
            
            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }
            
            try {
                $especialidadData = $request->all();
                $especialidadData['created_by'] = $user->id;
                $especialidadData['activo'] = $request->boolean('activo', true);
                $especialidadData['requiere_historia_clinica'] = $request->boolean('requiere_historia_clinica', true);
                $especialidadData['duracion_consulta_default'] = $request->get('duracion_consulta_default', 30);
                
                $especialidad = Especialidad::create($especialidadData);
                
                $this->logMedicalActivity('Nueva especialidad dental creada', 'especialidades', $especialidad->id, $request, [
                    'specialty_name' => $especialidad->nombre,
                    'specialty_code' => $especialidad->codigo,
                    'default_duration' => $especialidad->duracion_consulta_default,
                    'requires_medical_history' => $especialidad->requiere_historia_clinica,
                    'created_by' => $user->name,
                    'security_level' => 'medium',
                ]);
                
                return $this->successResponse($especialidad, 'Especialidad dental creada exitosamente', 201);
                
            } catch (\Exception $e) {
                Log::error('Error creando especialidad dental', [
                    'error' => $e->getMessage(),
                    'data' => $request->except(['password']),
                    'user_id' => $user->id,
                    'context' => 'dental_specialty_creation',
                ]);
                
                return $this->errorResponse('Error al crear especialidad dental: ' . $e->getMessage());
            }
        }, 'creación de especialidad dental');
    }

    /**
     * Mostrar información detallada de especialidad dental
     */
    public function show(Especialidad $especialidad, Request $request)
    {
        return $this->handleMedicalAction(function () use ($especialidad, $request) {
            $user = $request->user();
            
            // Cargar doctores activos de la especialidad si se solicita
            if ($request->boolean('with_doctors')) {
                $especialidad->load(['doctores' => function ($query) {
                    $query->where('activo', true)->with('user');
                }]);
            }
            
            $this->logMedicalActivity('Consulta de especialidad dental', 'especialidades', $especialidad->id, $request, [
                'specialty_name' => $especialidad->nombre,
                'specialty_code' => $especialidad->codigo,
                'include_doctors' => $request->boolean('with_doctors'),
                'consulted_by' => $user->name,
            ]);
            
            return $this->successResponse($especialidad, 'Información de especialidad dental obtenida exitosamente');
        }, 'consulta de especialidad dental');
    }

    /**
     * Actualizar especialidad dental (solo administradores)
     */
    public function update(Request $request, Especialidad $especialidad)
    {
        return $this->handleMedicalAction(function () use ($request, $especialidad) {
            $user = $request->user();
            
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:100|unique:especialidades,nombre,' . $especialidad->id . '|regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s\-]+$/',
                'descripcion' => 'nullable|string|max:1000',
                'codigo' => 'nullable|string|max:10|unique:especialidades,codigo,' . $especialidad->id . '|regex:/^[A-Z0-9]+$/',
                'duracion_consulta_default' => 'nullable|integer|min:15|max:120',
                'precio_base' => 'nullable|numeric|min:0|max:999999.99',
                'requiere_historia_clinica' => 'boolean',
                'activo' => 'boolean',
            ], [
                'nombre.unique' => 'Ya existe otra especialidad con este nombre',
                'nombre.regex' => 'El nombre solo puede contener letras, espacios y guiones',
                'codigo.unique' => 'Ya existe otra especialidad con este código',
                'codigo.regex' => 'El código debe contener solo letras mayúsculas y números',
                'duracion_consulta_default.min' => 'La duración mínima de consulta es 15 minutos',
                'duracion_consulta_default.max' => 'La duración máxima de consulta es 120 minutos',
                'precio_base.max' => 'El precio base no puede exceder $999,999.99',
            ]);
            
            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }
            
            $originalData = $especialidad->toArray();
            $especialidad->update($request->all());
            
            $this->logMedicalActivity('Especialidad dental actualizada', 'especialidades', $especialidad->id, $request, [
                'specialty_name' => $especialidad->nombre,
                'specialty_code' => $especialidad->codigo,
                'updated_by' => $user->name,
                'changes' => array_diff_assoc($request->all(), $originalData),
                'security_level' => 'medium',
            ]);
            
            return $this->successResponse($especialidad, 'Especialidad dental actualizada exitosamente');
        }, 'actualización de especialidad dental');
    }

    /**
     * Desactivar especialidad dental (solo administradores)
     */
    public function destroy(Especialidad $especialidad, Request $request)
    {
        return $this->handleMedicalAction(function () use ($especialidad, $request) {
            $user = $request->user();
            
            // Verificar si tiene doctores activos asociados
            $doctoresActivos = $especialidad->doctores()
                ->where('activo', true)
                ->count();
            
            if ($doctoresActivos > 0) {
                return $this->errorResponse(
                    "No se puede desactivar la especialidad porque tiene {$doctoresActivos} doctores activos asociados",
                    422
                );
            }
            
            // Verificar si tiene turnos programados
            $turnosProgramados = $especialidad->turnos()
                ->whereIn('estado', ['programado', 'confirmado'])
                ->count();
            
            if ($turnosProgramados > 0) {
                return $this->errorResponse(
                    "No se puede desactivar la especialidad porque tiene {$turnosProgramados} turnos programados",
                    422
                );
            }
            
            $especialidad->update(['activo' => false]);
            
            $this->logMedicalActivity('Especialidad dental desactivada', 'especialidades', $especialidad->id, $request, [
                'specialty_name' => $especialidad->nombre,
                'specialty_code' => $especialidad->codigo,
                'deactivated_by' => $user->name,
                'security_level' => 'high',
            ]);
            
            return $this->successResponse(null, 'Especialidad dental desactivada exitosamente');
        }, 'desactivación de especialidad dental');
    }

    /**
     * Obtener doctores de una especialidad dental específica
     */
    public function doctores(Especialidad $especialidad, Request $request)
    {
        return $this->handleMedicalAction(function () use ($especialidad, $request) {
            $user = $request->user();
            
            $query = $especialidad->doctores()->where('activo', true);
            
            // Incluir información de horarios si se solicita
            if ($request->boolean('with_schedules')) {
                $query->with(['horarios' => function ($q) {
                    $q->where('activo', true)->orderBy('dia_semana')->orderBy('hora_inicio');
                }]);
            }
            
            // Incluir información de usuario si se solicita
            if ($request->boolean('with_user')) {
                $query->with('user:id,name,email');
            }
            
            $doctores = $query->orderBy('apellido')
                            ->orderBy('nombre')
                            ->get();
            
            $this->logMedicalActivity('Consulta de doctores por especialidad', 'especialidades', $especialidad->id, $request, [
                'specialty_name' => $especialidad->nombre,
                'doctors_count' => $doctores->count(),
                'include_schedules' => $request->boolean('with_schedules'),
                'include_user_info' => $request->boolean('with_user'),
                'consulted_by' => $user->name,
            ]);
            
            return $this->successResponse($doctores, 'Doctores de la especialidad obtenidos exitosamente');
        }, 'consulta de doctores por especialidad');
    }
}
