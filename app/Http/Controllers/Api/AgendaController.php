<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Turno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * Controlador para gestión de agenda dental
 * 
 * Sistema interno de clínica dental - Solo personal autorizado
 * Gestiona visualización y organización de turnos con auditoría completa
 * 
 * @package App\Http\Controllers\Api
 * @author Sistema Dental Clínico
 * @version 2.0
 */
class AgendaController extends Controller
{
    /**
     * Constructor del controlador de agenda dental
     */
    public function __construct()
    {
        // Middleware de autenticación médica requerido
        $this->middleware('auth:sanctum');
        
        // Solo personal autorizado puede acceder a la agenda
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            
            if (!in_array($user->rol, ['admin', 'secretaria', 'operador', 'doctor'])) {
                return $this->forbiddenResponse('Acceso restringido: Solo personal autorizado puede acceder a la agenda');
            }
            
            return $next($request);
        });
    }

    /**
     * Obtener agenda de turnos por doctor específico
     */
    public function porDoctor(Doctor $doctor, Request $request)
    {
        return $this->handleMedicalAction(function () use ($doctor, $request) {
            $user = $request->user();
            
            // Los doctores solo pueden ver su propia agenda
            if ($user->rol === 'doctor' && $user->doctor_id !== $doctor->id) {
                return $this->forbiddenResponse('Solo puede acceder a su propia agenda');
            }
            
            $validator = Validator::make($request->all(), [
                'fecha' => 'nullable|date',
                'estado' => 'nullable|in:programado,confirmado,realizado,cancelado',
                'incluir_paciente' => 'boolean',
            ], [
                'fecha.date' => 'Formato de fecha inválido',
                'estado.in' => 'Estado de turno no válido',
            ]);
            
            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }
            
            $fecha = $request->get('fecha', now()->format('Y-m-d'));
            $fechaCarbon = Carbon::parse($fecha);
            
            // No mostrar agenda de domingos
            if ($fechaCarbon->isSunday()) {
                return $this->errorResponse('No hay atención dental los domingos', 422);
            }
            
            $query = Turno::with(['paciente', 'doctor.especialidad'])
                ->where('doctor_id', $doctor->id)
                ->where('fecha', $fecha);
            
            // Filtrar por estado si se especifica
            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }
            
            $turnos = $query->orderBy('hora_inicio')->get();
            
            // Ocultar información sensible del paciente según el rol
            if (!$request->boolean('incluir_paciente') && !in_array($user->rol, ['admin', 'doctor'])) {
                $turnos->transform(function ($turno) {
                    $turno->paciente = [
                        'id' => $turno->paciente->id,
                        'nombre' => substr($turno->paciente->nombre, 0, 1) . '***',
                        'apellido' => $turno->paciente->apellido,
                    ];
                    return $turno;
                });
            }
            
            $this->logMedicalActivity('Consulta de agenda por doctor', 'agenda', null, $request, [
                'doctor_id' => $doctor->id,
                'doctor_name' => "{$doctor->nombre} {$doctor->apellido}",
                'requested_date' => $fecha,
                'filter_status' => $request->estado,
                'appointments_count' => $turnos->count(),
                'consulted_by' => $user->name,
            ]);
            
            return $this->successResponse([
                'doctor' => [
                    'id' => $doctor->id,
                    'nombre' => $doctor->nombre,
                    'apellido' => $doctor->apellido,
                    'especialidad' => $doctor->especialidad->nombre,
                ],
                'fecha' => $fecha,
                'fecha_formatted' => $fechaCarbon->format('d/m/Y'),
                'dia_semana' => $fechaCarbon->locale('es')->dayName,
                'turnos' => $turnos,
                'resumen' => [
                    'total' => $turnos->count(),
                    'programados' => $turnos->where('estado', 'programado')->count(),
                    'confirmados' => $turnos->where('estado', 'confirmado')->count(),
                    'realizados' => $turnos->where('estado', 'realizado')->count(),
                    'cancelados' => $turnos->where('estado', 'cancelado')->count(),
                ],
            ], 'Agenda del doctor obtenida exitosamente');
        }, 'consulta de agenda por doctor');
    }

    /**
     * Obtener agenda de turnos por fecha específica
     */
    public function porFecha(Request $request)
    {
        return $this->handleMedicalAction(function () use ($request) {
            $user = $request->user();
            
            $validator = Validator::make($request->all(), [
                'fecha' => 'required|date',
                'doctor_id' => 'nullable|exists:doctores,id',
                'especialidad_id' => 'nullable|exists:especialidades,id',
                'estado' => 'nullable|in:programado,confirmado,realizado,cancelado',
            ], [
                'fecha.required' => 'La fecha es obligatoria',
                'fecha.date' => 'Formato de fecha inválido',
                'doctor_id.exists' => 'El doctor especificado no existe',
                'especialidad_id.exists' => 'La especialidad especificada no existe',
                'estado.in' => 'Estado de turno no válido',
            ]);
            
            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }
            
            $fecha = $request->fecha;
            $fechaCarbon = Carbon::parse($fecha);
            
            // No mostrar agenda de domingos
            if ($fechaCarbon->isSunday()) {
                return $this->errorResponse('No hay atención dental los domingos', 422);
            }
            
            $query = Turno::with(['paciente', 'doctor.especialidad'])
                ->where('fecha', $fecha);
            
            // Si es doctor, solo mostrar sus turnos
            if ($user->rol === 'doctor') {
                $query->where('doctor_id', $user->doctor_id);
            }
            
            // Filtros adicionales
            if ($request->filled('doctor_id')) {
                $query->where('doctor_id', $request->doctor_id);
            }
            
            if ($request->filled('especialidad_id')) {
                $query->whereHas('doctor', function ($q) use ($request) {
                    $q->where('especialidad_id', $request->especialidad_id);
                });
            }
            
            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }
            
            $turnos = $query->orderBy('hora_inicio')
                           ->orderBy('doctor_id')
                           ->get();
            
            // Agrupar turnos por doctor
            $turnosPorDoctor = $turnos->groupBy('doctor_id')->map(function ($turnosDoctor) {
                $doctor = $turnosDoctor->first()->doctor;
                return [
                    'doctor' => [
                        'id' => $doctor->id,
                        'nombre' => $doctor->nombre,
                        'apellido' => $doctor->apellido,
                        'especialidad' => $doctor->especialidad->nombre,
                    ],
                    'turnos' => $turnosDoctor->values(),
                    'total_turnos' => $turnosDoctor->count(),
                ];
            })->values();
            
            $this->logMedicalActivity('Consulta de agenda por fecha', 'agenda', null, $request, [
                'requested_date' => $fecha,
                'filter_doctor_id' => $request->doctor_id,
                'filter_specialty_id' => $request->especialidad_id,
                'filter_status' => $request->estado,
                'total_appointments' => $turnos->count(),
                'doctors_with_appointments' => $turnosPorDoctor->count(),
                'consulted_by' => $user->name,
            ]);
            
            return $this->successResponse([
                'fecha' => $fecha,
                'fecha_formatted' => $fechaCarbon->format('d/m/Y'),
                'dia_semana' => $fechaCarbon->locale('es')->dayName,
                'agenda_por_doctor' => $turnosPorDoctor,
                'resumen_general' => [
                    'total_turnos' => $turnos->count(),
                    'doctores_con_turnos' => $turnosPorDoctor->count(),
                    'programados' => $turnos->where('estado', 'programado')->count(),
                    'confirmados' => $turnos->where('estado', 'confirmado')->count(),
                    'realizados' => $turnos->where('estado', 'realizado')->count(),
                    'cancelados' => $turnos->where('estado', 'cancelado')->count(),
                ],
            ], 'Agenda por fecha obtenida exitosamente');
        }, 'consulta de agenda por fecha');
    }

    /**
     * Obtener disponibilidad de horarios para doctor dental específico
     */
    public function disponibilidad(Request $request)
    {
        return $this->handleMedicalAction(function () use ($request) {
            $user = $request->user();
            
            $validator = Validator::make($request->all(), [
                'doctor_id' => 'required|exists:doctores,id',
                'fecha' => 'required|date|after_or_equal:today',
                'duracion_consulta' => 'nullable|integer|min:15|max:120',
            ], [
                'doctor_id.required' => 'El doctor es obligatorio',
                'doctor_id.exists' => 'El doctor especificado no existe',
                'fecha.required' => 'La fecha es obligatoria',
                'fecha.after_or_equal' => 'La fecha debe ser hoy o posterior',
                'duracion_consulta.min' => 'La duración mínima de consulta es 15 minutos',
                'duracion_consulta.max' => 'La duración máxima de consulta es 120 minutos',
            ]);
            
            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }
            
            $doctorId = $request->doctor_id;
            $fecha = $request->fecha;
            $fechaCarbon = Carbon::parse($fecha);
            $duracionConsulta = $request->get('duracion_consulta', 30); // 30 minutos por defecto
            
            // No permitir consultas los domingos
            if ($fechaCarbon->isSunday()) {
                return $this->errorResponse('No hay atención dental los domingos', 422);
            }
            
            $doctor = Doctor::with('especialidad')->findOrFail($doctorId);
            
            // Obtener turnos ocupados
            $turnosOcupados = Turno::where('doctor_id', $doctorId)
                ->where('fecha', $fecha)
                ->whereIn('estado', ['programado', 'confirmado'])
                ->pluck('hora_inicio')
                ->toArray();
            
            // Obtener horarios del doctor para este día
            $diaSemana = $fechaCarbon->dayOfWeek === 0 ? 7 : $fechaCarbon->dayOfWeek; // Convertir domingo de 0 a 7
            
            $horariosDoctor = $doctor->horarios()
                ->where('dia_semana', $diaSemana)
                ->where('activo', true)
                ->orderBy('hora_inicio')
                ->get();
            
            if ($horariosDoctor->isEmpty()) {
                return $this->successResponse([
                    'doctor' => [
                        'id' => $doctor->id,
                        'nombre' => $doctor->nombre,
                        'apellido' => $doctor->apellido,
                        'especialidad' => $doctor->especialidad->nombre,
                    ],
                    'fecha' => $fecha,
                    'fecha_formatted' => $fechaCarbon->format('d/m/Y'),
                    'horarios_disponibles' => [],
                    'mensaje' => 'El doctor no tiene horarios configurados para este día',
                ], 'Disponibilidad consultada - Sin horarios configurados');
            }
            
            $horariosDisponibles = [];
            
            foreach ($horariosDoctor as $horario) {
                $horaInicio = Carbon::createFromFormat('H:i', $horario->hora_inicio);
                $horaFin = Carbon::createFromFormat('H:i', $horario->hora_fin);
                
                // Generar slots cada X minutos según duración de consulta
                while ($horaInicio->addMinutes($duracionConsulta) <= $horaFin) {
                    $horaSlot = $horaInicio->copy()->subMinutes($duracionConsulta)->format('H:i');
                    
                    $disponible = !in_array($horaSlot, $turnosOcupados);
                    
                    $horariosDisponibles[] = [
                        'hora' => $horaSlot,
                        'hora_formatted' => $horaInicio->copy()->subMinutes($duracionConsulta)->format('H:i'),
                        'disponible' => $disponible,
                        'turno' => $horario->turno,
                        'duracion_minutos' => $duracionConsulta,
                    ];
                }
                
                // Resetear hora inicio para siguiente horario
                $horaInicio = Carbon::createFromFormat('H:i', $horario->hora_inicio);
            }
            
            // Agrupar por turno (mañana/tarde)
            $horariosPorTurno = collect($horariosDisponibles)
                ->groupBy('turno')
                ->map(function ($horarios, $turno) {
                    return [
                        'turno' => $turno,
                        'horarios' => $horarios->values(),
                        'total_slots' => $horarios->count(),
                        'slots_disponibles' => $horarios->where('disponible', true)->count(),
                        'slots_ocupados' => $horarios->where('disponible', false)->count(),
                    ];
                });
            
            $this->logMedicalActivity('Consulta de disponibilidad de doctor', 'agenda', null, $request, [
                'doctor_id' => $doctorId,
                'doctor_name' => "{$doctor->nombre} {$doctor->apellido}",
                'requested_date' => $fecha,
                'consultation_duration' => $duracionConsulta,
                'total_slots' => count($horariosDisponibles),
                'available_slots' => collect($horariosDisponibles)->where('disponible', true)->count(),
                'consulted_by' => $user->name,
            ]);
            
            return $this->successResponse([
                'doctor' => [
                    'id' => $doctor->id,
                    'nombre' => $doctor->nombre,
                    'apellido' => $doctor->apellido,
                    'especialidad' => $doctor->especialidad->nombre,
                ],
                'fecha' => $fecha,
                'fecha_formatted' => $fechaCarbon->format('d/m/Y'),
                'dia_semana' => $fechaCarbon->locale('es')->dayName,
                'duracion_consulta' => $duracionConsulta,
                'horarios_por_turno' => $horariosPorTurno,
                'resumen' => [
                    'total_slots' => count($horariosDisponibles),
                    'slots_disponibles' => collect($horariosDisponibles)->where('disponible', true)->count(),
                    'slots_ocupados' => collect($horariosDisponibles)->where('disponible', false)->count(),
                ],
            ], 'Disponibilidad del doctor obtenida exitosamente');
        }, 'consulta de disponibilidad de doctor');
    }

    /**
     * Generar reporte PDF de agenda dental (solo personal administrativo)
     */
    public function generarPDF(Request $request)
    {
        return $this->handleMedicalAction(function () use ($request) {
            $user = $request->user();
            
            // Solo personal administrativo puede generar reportes
            if (!in_array($user->rol, ['admin', 'secretaria'])) {
                return $this->forbiddenResponse('Solo personal administrativo puede generar reportes');
            }
            
            $validator = Validator::make($request->all(), [
                'doctor_id' => 'required|exists:doctores,id',
                'fecha' => 'nullable|date',
                'incluir_cancelados' => 'boolean',
                'incluir_realizados' => 'boolean',
            ], [
                'doctor_id.required' => 'El doctor es obligatorio para generar el reporte',
                'doctor_id.exists' => 'El doctor especificado no existe',
                'fecha.date' => 'Formato de fecha inválido',
            ]);
            
            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }
            
            $doctorId = $request->doctor_id;
            $fecha = $request->get('fecha', now()->format('Y-m-d'));
            $fechaCarbon = Carbon::parse($fecha);
            
            try {
                $doctor = Doctor::with('especialidad')->findOrFail($doctorId);
                
                $query = Turno::with(['paciente'])
                    ->where('doctor_id', $doctorId)
                    ->where('fecha', $fecha);
                
                // Filtrar estados según configuración
                $estados = ['programado', 'confirmado'];
                
                if ($request->boolean('incluir_realizados')) {
                    $estados[] = 'realizado';
                }
                
                if ($request->boolean('incluir_cancelados')) {
                    $estados[] = 'cancelado';
                }
                
                $turnos = $query->whereIn('estado', $estados)
                               ->orderBy('hora_inicio')
                               ->get();
                
                $reporteData = [
                    'clinica' => [
                        'nombre' => config('app.name', 'Clínica Dental'),
                        'direccion' => 'Dirección de la Clínica',
                        'telefono' => 'Teléfono de la Clínica',
                        'email' => 'contacto@clinicadental.com',
                    ],
                    'doctor' => [
                        'id' => $doctor->id,
                        'nombre_completo' => "{$doctor->nombre} {$doctor->apellido}",
                        'especialidad' => $doctor->especialidad->nombre,
                        'matricula' => $doctor->matricula,
                    ],
                    'fecha' => $fecha,
                    'fecha_formatted' => $fechaCarbon->format('d/m/Y'),
                    'dia_semana' => $fechaCarbon->locale('es')->dayName,
                    'turnos' => $turnos->map(function ($turno) {
                        return [
                            'hora' => $turno->hora_inicio,
                            'paciente' => [
                                'nombre_completo' => "{$turno->paciente->nombre} {$turno->paciente->apellido}",
                                'dni' => $turno->paciente->dni,
                                'telefono' => $turno->paciente->telefono,
                            ],
                            'estado' => $turno->estado,
                            'observaciones' => $turno->observaciones_consulta,
                        ];
                    }),
                    'estadisticas' => [
                        'total_turnos' => $turnos->count(),
                        'programados' => $turnos->where('estado', 'programado')->count(),
                        'confirmados' => $turnos->where('estado', 'confirmado')->count(),
                        'realizados' => $turnos->where('estado', 'realizado')->count(),
                        'cancelados' => $turnos->where('estado', 'cancelado')->count(),
                    ],
                    'generado_por' => $user->name,
                    'fecha_generacion' => now()->format('d/m/Y H:i'),
                ];
                
                $this->logMedicalActivity('Reporte PDF de agenda generado', 'agenda', null, $request, [
                    'doctor_id' => $doctorId,
                    'doctor_name' => "{$doctor->nombre} {$doctor->apellido}",
                    'report_date' => $fecha,
                    'appointments_included' => $turnos->count(),
                    'include_cancelled' => $request->boolean('incluir_cancelados'),
                    'include_completed' => $request->boolean('incluir_realizados'),
                    'generated_by' => $user->name,
                    'security_level' => 'medium',
                ]);
                
                // Por ahora devolvemos JSON estructurado para el PDF
                // Después se puede implementar la generación real del PDF
                return $this->successResponse($reporteData, 'Datos del reporte generados exitosamente');
                
            } catch (\Exception $e) {
                Log::error('Error generando reporte PDF de agenda dental', [
                    'error' => $e->getMessage(),
                    'doctor_id' => $doctorId,
                    'fecha' => $fecha,
                    'user_id' => $user->id,
                    'context' => 'dental_agenda_pdf_report',
                ]);
                
                return $this->errorResponse('Error generando reporte: ' . $e->getMessage());
            }
        }, 'generación de reporte PDF de agenda');
    }
}
