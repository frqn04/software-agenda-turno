<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\DoctorContract;
use App\Models\DoctorScheduleSlot;
use App\Services\DoctorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * Controlador para gestión de doctores dentales
 * 
 * Sistema interno de clínica dental - Solo personal autorizado
 * Gestiona información médica confidencial con auditoría completa
 * 
 * @package App\Http\Controllers\Api
 * @author Sistema Dental Clínico
 * @version 2.0
 */
class DoctorController extends Controller
{
    protected $doctorService;

    /**
     * Constructor del controlador de doctores dentales
     */
    public function __construct(DoctorService $doctorService)
    {
        $this->doctorService = $doctorService;
        
        // Middleware de autenticación médica requerido
        $this->middleware('auth:sanctum');
        
        // Solo personal administrativo puede gestionar doctores
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            
            if (!in_array($user->rol, ['admin', 'secretaria'])) {
                return $this->forbiddenResponse('Acceso restringido: Solo personal administrativo autorizado');
            }
            
            return $next($request);
        });
    }

    /**
     * Listar doctores activos de la clínica dental
     */
    public function index(Request $request)
    {
        return $this->handleMedicalAction(function () use ($request) {
            $user = $request->user();
            
            // Filtros de búsqueda para clínica dental
            $query = Doctor::with(['especialidad', 'user', 'contratos'])
                ->where('activo', true);
            
            if ($request->filled('especialidad_id')) {
                $query->where('especialidad_id', $request->especialidad_id);
            }
            
            if ($request->filled('buscar')) {
                $buscar = $request->buscar;
                $query->where(function ($q) use ($buscar) {
                    $q->where('nombre', 'like', "%{$buscar}%")
                      ->orWhere('apellido', 'like', "%{$buscar}%")
                      ->orWhere('matricula', 'like', "%{$buscar}%");
                });
            }
            
            $doctores = $query->orderBy('apellido')
                             ->orderBy('nombre')
                             ->paginate($request->get('per_page', 15));
            
            $this->logMedicalActivity('Consulta de doctores dentales', 'doctores', null, $request, [
                'total_doctors' => $doctores->total(),
                'search_filters' => $request->only(['especialidad_id', 'buscar']),
                'consulted_by' => $user->name,
            ]);
            
            return $this->successResponse($doctores, 'Doctores dentales obtenidos exitosamente');
        }, 'consulta de doctores dentales');
    }

    /**
     * Crear nuevo doctor dental (solo administradores)
     */
    public function store(Request $request)
    {
        return $this->handleMedicalAction(function () use ($request) {
            $user = $request->user();
            
            // Solo administradores pueden crear doctores
            if ($user->rol !== 'admin') {
                return $this->forbiddenResponse('Solo administradores pueden registrar nuevos doctores');
            }
            
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:100|regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/',
                'apellido' => 'required|string|max:100|regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/',
                'especialidad_id' => 'required|exists:especialidades,id',
                'matricula' => 'required|string|max:50|unique:doctores,matricula|regex:/^[A-Z0-9\-]+$/',
                'telefono' => 'nullable|string|max:20|regex:/^[\+\-\(\)\s\d]+$/',
                'email' => 'nullable|email|max:255|unique:doctores,email',
                'direccion' => 'nullable|string|max:300',
                'fecha_nacimiento' => 'nullable|date|before:-18 years',
                'documento' => 'nullable|string|max:20|unique:doctores,documento',
                'observaciones' => 'nullable|string|max:1000',
                'activo' => 'boolean',
            ], [
                'nombre.regex' => 'El nombre solo puede contener letras y espacios',
                'apellido.regex' => 'El apellido solo puede contener letras y espacios',
                'matricula.unique' => 'Ya existe un doctor con esta matrícula',
                'matricula.regex' => 'La matrícula debe contener solo letras mayúsculas, números y guiones',
                'email.unique' => 'Ya existe un doctor con este email',
                'fecha_nacimiento.before' => 'El doctor debe ser mayor de 18 años',
                'documento.unique' => 'Ya existe un doctor con este documento',
            ]);
            
            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }
            
            try {
                $doctorData = $request->all();
                $doctorData['created_by'] = $user->id;
                $doctorData['activo'] = $request->boolean('activo', true);
                
                $doctor = Doctor::create($doctorData);
                
                $this->logMedicalActivity('Nuevo doctor dental registrado', 'doctores', $doctor->id, $request, [
                    'doctor_name' => "{$doctor->nombre} {$doctor->apellido}",
                    'medical_license' => $doctor->matricula,
                    'specialty_id' => $doctor->especialidad_id,
                    'registered_by' => $user->name,
                    'security_level' => 'high',
                ]);
                
                return $this->successResponse(
                    $doctor->load(['especialidad', 'user']),
                    'Doctor dental registrado exitosamente',
                    201
                );
                
            } catch (\Exception $e) {
                Log::error('Error registrando doctor dental', [
                    'error' => $e->getMessage(),
                    'data' => $request->except(['password']),
                    'user_id' => $user->id,
                    'context' => 'dental_doctor_creation',
                ]);
                
                return $this->errorResponse('Error al registrar doctor dental: ' . $e->getMessage());
            }
        }, 'registro de doctor dental');
    }
    /**
     * Mostrar información detallada de doctor dental
     */
    public function show(Doctor $doctor, Request $request)
    {
        return $this->handleMedicalAction(function () use ($doctor, $request) {
            $user = $request->user();
            
            $this->logMedicalActivity('Consulta de doctor dental', 'doctores', $doctor->id, $request, [
                'doctor_name' => "{$doctor->nombre} {$doctor->apellido}",
                'medical_license' => $doctor->matricula,
                'consulted_by' => $user->name,
            ]);
            
            return $this->successResponse(
                $doctor->load(['especialidad', 'user', 'contratos', 'horarios']),
                'Información del doctor dental obtenida exitosamente'
            );
        }, 'consulta de doctor dental');
    }

    /**
     * Actualizar información de doctor dental (solo administradores)
     */
    public function update(Request $request, Doctor $doctor)
    {
        return $this->handleMedicalAction(function () use ($request, $doctor) {
            $user = $request->user();
            
            // Solo administradores pueden actualizar doctores
            if ($user->rol !== 'admin') {
                return $this->forbiddenResponse('Solo administradores pueden actualizar información de doctores');
            }
            
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:100|regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/',
                'apellido' => 'required|string|max:100|regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/',
                'especialidad_id' => 'required|exists:especialidades,id',
                'matricula' => 'required|string|max:50|unique:doctores,matricula,' . $doctor->id . '|regex:/^[A-Z0-9\-]+$/',
                'telefono' => 'nullable|string|max:20|regex:/^[\+\-\(\)\s\d]+$/',
                'email' => 'nullable|email|max:255|unique:doctores,email,' . $doctor->id,
                'direccion' => 'nullable|string|max:300',
                'fecha_nacimiento' => 'nullable|date|before:-18 years',
                'documento' => 'nullable|string|max:20|unique:doctores,documento,' . $doctor->id,
                'observaciones' => 'nullable|string|max:1000',
                'activo' => 'boolean',
            ], [
                'nombre.regex' => 'El nombre solo puede contener letras y espacios',
                'apellido.regex' => 'El apellido solo puede contener letras y espacios',
                'matricula.unique' => 'Ya existe otro doctor con esta matrícula',
                'matricula.regex' => 'La matrícula debe contener solo letras mayúsculas, números y guiones',
                'email.unique' => 'Ya existe otro doctor con este email',
                'fecha_nacimiento.before' => 'El doctor debe ser mayor de 18 años',
                'documento.unique' => 'Ya existe otro doctor con este documento',
            ]);
            
            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }
            
            $originalData = $doctor->toArray();
            $doctor->update($request->all());
            
            $this->logMedicalActivity('Doctor dental actualizado', 'doctores', $doctor->id, $request, [
                'doctor_name' => "{$doctor->nombre} {$doctor->apellido}",
                'medical_license' => $doctor->matricula,
                'updated_by' => $user->name,
                'changes' => array_diff_assoc($request->all(), $originalData),
                'security_level' => 'medium',
            ]);
            
            return $this->successResponse(
                $doctor->load(['especialidad', 'user']),
                'Doctor dental actualizado exitosamente'
            );
        }, 'actualización de doctor dental');
    }

    /**
     * Desactivar doctor dental (solo administradores)
     */
    public function destroy(Doctor $doctor, Request $request)
    {
        return $this->handleMedicalAction(function () use ($doctor, $request) {
            $user = $request->user();
            
            // Solo administradores pueden desactivar doctores
            if ($user->rol !== 'admin') {
                return $this->forbiddenResponse('Solo administradores pueden desactivar doctores');
            }
            
            // Verificar si tiene turnos programados o pendientes
            $turnosPendientes = $doctor->turnos()
                ->whereIn('estado', ['programado', 'confirmado'])
                ->count();
            
            if ($turnosPendientes > 0) {
                return $this->errorResponse(
                    "No se puede desactivar el doctor porque tiene {$turnosPendientes} turnos programados o confirmados",
                    422
                );
            }
            
            $doctor->update(['activo' => false]);
            
            $this->logMedicalActivity('Doctor dental desactivado', 'doctores', $doctor->id, $request, [
                'doctor_name' => "{$doctor->nombre} {$doctor->apellido}",
                'medical_license' => $doctor->matricula,
                'deactivated_by' => $user->name,
                'security_level' => 'high',
            ]);
            
            return $this->successResponse(null, 'Doctor dental desactivado exitosamente');
        }, 'desactivación de doctor dental');
    }

    /**
     * Obtener contratos del doctor dental
     */
    public function contratos(Doctor $doctor, Request $request)
    {
        return $this->handleMedicalAction(function () use ($doctor, $request) {
            $user = $request->user();
            
            $contratos = $doctor->contratos()
                ->orderBy('fecha_inicio', 'desc')
                ->get();
            
            $this->logMedicalActivity('Consulta de contratos de doctor', 'doctor_contracts', null, $request, [
                'doctor_id' => $doctor->id,
                'doctor_name' => "{$doctor->nombre} {$doctor->apellido}",
                'contracts_count' => $contratos->count(),
                'consulted_by' => $user->name,
            ]);
            
            return $this->successResponse($contratos, 'Contratos del doctor obtenidos exitosamente');
        }, 'consulta de contratos de doctor');
    }

    /**
     * Crear contrato para doctor dental (solo administradores)
     */
    public function storeContrato(Request $request, Doctor $doctor)
    {
        return $this->handleMedicalAction(function () use ($request, $doctor) {
            $user = $request->user();
            
            // Solo administradores pueden crear contratos
            if ($user->rol !== 'admin') {
                return $this->forbiddenResponse('Solo administradores pueden crear contratos');
            }
            
            $validator = Validator::make($request->all(), [
                'fecha_inicio' => 'required|date|after_or_equal:today',
                'fecha_fin' => 'required|date|after:fecha_inicio',
                'tipo_contrato' => 'required|in:eventual,permanente,suplente,guardia',
                'salario_base' => 'nullable|numeric|min:0|max:9999999.99',
                'porcentaje_comision' => 'nullable|numeric|min:0|max:100',
                'observaciones' => 'nullable|string|max:1000',
                'activo' => 'boolean',
            ], [
                'fecha_inicio.after_or_equal' => 'La fecha de inicio debe ser hoy o posterior',
                'fecha_fin.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
                'tipo_contrato.in' => 'Tipo de contrato no válido para clínica dental',
                'salario_base.max' => 'El salario base no puede exceder $9,999,999.99',
                'porcentaje_comision.max' => 'El porcentaje de comisión no puede exceder 100%',
            ]);
            
            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }
            
            // Verificar solapamiento de contratos activos
            $solapamiento = $doctor->contratos()
                ->where('activo', true)
                ->where(function ($query) use ($request) {
                    $query->whereBetween('fecha_inicio', [$request->fecha_inicio, $request->fecha_fin])
                          ->orWhereBetween('fecha_fin', [$request->fecha_inicio, $request->fecha_fin])
                          ->orWhere(function ($q) use ($request) {
                              $q->where('fecha_inicio', '<=', $request->fecha_inicio)
                                ->where('fecha_fin', '>=', $request->fecha_fin);
                          });
                })
                ->exists();
            
            if ($solapamiento) {
                return $this->errorResponse('El período del contrato se superpone con otro contrato activo', 422);
            }
            
            $contratoData = $request->all();
            $contratoData['created_by'] = $user->id;
            $contratoData['activo'] = $request->boolean('activo', true);
            
            $contrato = $doctor->contratos()->create($contratoData);
            
            $this->logMedicalActivity('Contrato de doctor creado', 'doctor_contracts', $contrato->id, $request, [
                'doctor_id' => $doctor->id,
                'doctor_name' => "{$doctor->nombre} {$doctor->apellido}",
                'contract_type' => $contrato->tipo_contrato,
                'contract_period' => "{$contrato->fecha_inicio} - {$contrato->fecha_fin}",
                'created_by' => $user->name,
                'security_level' => 'high',
            ]);
            
            return $this->successResponse($contrato, 'Contrato creado exitosamente', 201);
        }, 'creación de contrato de doctor');
    }

    /**
     * Obtener horarios del doctor dental
     */
    public function horarios(Doctor $doctor, Request $request)
    {
        return $this->handleMedicalAction(function () use ($doctor, $request) {
            $user = $request->user();
            
            $horarios = $doctor->horarios()
                ->where('activo', true)
                ->orderBy('dia_semana')
                ->orderBy('hora_inicio')
                ->get();
            
            $this->logMedicalActivity('Consulta de horarios de doctor', 'doctor_schedules', null, $request, [
                'doctor_id' => $doctor->id,
                'doctor_name' => "{$doctor->nombre} {$doctor->apellido}",
                'schedules_count' => $horarios->count(),
                'consulted_by' => $user->name,
            ]);
            
            return $this->successResponse($horarios, 'Horarios del doctor obtenidos exitosamente');
        }, 'consulta de horarios de doctor');
    }

    /**
     * Crear horario para doctor dental (solo administradores)
     */
    public function storeHorario(Request $request, Doctor $doctor)
    {
        return $this->handleMedicalAction(function () use ($request, $doctor) {
            $user = $request->user();
            
            // Solo administradores pueden crear horarios
            if ($user->rol !== 'admin') {
                return $this->forbiddenResponse('Solo administradores pueden crear horarios');
            }
            
            $validator = Validator::make($request->all(), [
                'dia_semana' => 'required|integer|between:1,6', // 1=Lunes, 6=Sábado (no domingos)
                'hora_inicio' => 'required|date_format:H:i',
                'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
                'turno' => 'required|in:mañana,tarde,completo',
                'duracion_consulta' => 'nullable|integer|min:15|max:120', // minutos
                'observaciones' => 'nullable|string|max:500',
                'activo' => 'boolean',
            ], [
                'dia_semana.between' => 'Día de semana no válido (1=Lunes a 6=Sábado)',
                'hora_inicio.date_format' => 'Formato de hora de inicio inválido (HH:MM)',
                'hora_fin.date_format' => 'Formato de hora de fin inválido (HH:MM)',
                'hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio',
                'turno.in' => 'Turno no válido para clínica dental',
                'duracion_consulta.min' => 'La duración mínima de consulta es 15 minutos',
                'duracion_consulta.max' => 'La duración máxima de consulta es 120 minutos',
            ]);
            
            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }
            
            // Verificar horarios de clínica dental
            $horaInicio = Carbon::createFromFormat('H:i', $request->hora_inicio);
            $horaFin = Carbon::createFromFormat('H:i', $request->hora_fin);
            
            // Horarios permitidos: L-V 8:00-18:00, S 8:00-13:00
            if ($request->dia_semana <= 5) { // Lunes a Viernes
                if ($horaInicio->hour < 8 || $horaFin->hour > 18) {
                    return $this->errorResponse('Horario fuera del rango permitido L-V: 8:00-18:00', 422);
                }
            } else { // Sábado
                if ($horaInicio->hour < 8 || $horaFin->hour > 13) {
                    return $this->errorResponse('Horario fuera del rango permitido Sábado: 8:00-13:00', 422);
                }
            }
            
            // Verificar solapamiento de horarios
            $solapamiento = $doctor->horarios()
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
            
            if ($solapamiento) {
                return $this->errorResponse('El horario se superpone con otro horario existente del doctor', 422);
            }
            
            $horarioData = $request->all();
            $horarioData['created_by'] = $user->id;
            $horarioData['duracion_consulta'] = $request->get('duracion_consulta', 30); // 30 min por defecto
            $horarioData['activo'] = $request->boolean('activo', true);
            
            $horario = $doctor->horarios()->create($horarioData);
            
            $diasSemana = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'];
            
            $this->logMedicalActivity('Horario de doctor creado', 'doctor_schedules', $horario->id, $request, [
                'doctor_id' => $doctor->id,
                'doctor_name' => "{$doctor->nombre} {$doctor->apellido}",
                'schedule_day' => $diasSemana[$request->dia_semana],
                'schedule_time' => "{$request->hora_inicio} - {$request->hora_fin}",
                'shift_type' => $request->turno,
                'created_by' => $user->name,
                'security_level' => 'medium',
            ]);
            
            return $this->successResponse($horario, 'Horario creado exitosamente', 201);
        }, 'creación de horario de doctor');
    }

    /**
     * Obtener slots disponibles para doctor dental en fecha específica
     */
    public function slotsDisponibles(Doctor $doctor, Request $request)
    {
        return $this->handleMedicalAction(function () use ($doctor, $request) {
            $user = $request->user();
            
            // Solo personal autorizado puede consultar slots
            if (!in_array($user->rol, ['admin', 'secretaria', 'operador'])) {
                return $this->forbiddenResponse('No tiene permisos para consultar slots disponibles');
            }
            
            $validator = Validator::make($request->all(), [
                'fecha' => 'required|date|after_or_equal:today',
                'especialidad_id' => 'nullable|exists:especialidades,id',
            ], [
                'fecha.required' => 'Debe especificar la fecha',
                'fecha.after_or_equal' => 'La fecha debe ser hoy o posterior',
                'especialidad_id.exists' => 'La especialidad especificada no existe',
            ]);
            
            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }
            
            $fecha = Carbon::parse($request->fecha);
            
            // No permitir consultas los domingos
            if ($fecha->isSunday()) {
                return $this->errorResponse('No hay atención dental los domingos', 422);
            }
            
            try {
                $slots = $this->doctorService->getAvailableSlots(
                    $doctor->id,
                    $request->fecha,
                    $request->especialidad_id
                );
                
                $this->logMedicalActivity('Consulta de slots disponibles de doctor', 'doctor_schedules', null, $request, [
                    'doctor_id' => $doctor->id,
                    'doctor_name' => "{$doctor->nombre} {$doctor->apellido}",
                    'requested_date' => $request->fecha,
                    'specialty_id' => $request->especialidad_id,
                    'slots_found' => count($slots),
                    'consulted_by' => $user->name,
                ]);
                
                return $this->successResponse($slots, 'Slots disponibles obtenidos exitosamente');
                
            } catch (\Exception $e) {
                Log::error('Error obteniendo slots disponibles de doctor dental', [
                    'error' => $e->getMessage(),
                    'doctor_id' => $doctor->id,
                    'fecha' => $request->fecha,
                    'user_id' => $user->id,
                    'context' => 'dental_doctor_slots',
                ]);
                
                return $this->errorResponse('Error al obtener slots disponibles: ' . $e->getMessage());
            }
        }, 'consulta de slots de doctor');
    }
}
