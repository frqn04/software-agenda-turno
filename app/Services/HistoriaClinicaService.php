<?php

namespace App\Services;

use App\Models\Paciente;
use App\Models\HistoriaClinica;
use App\Models\Evolucion;
use App\Models\Turno;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/**
 * Servicio de gestión de historia clínica
 * Maneja el historial médico completo de los pacientes
 */
class HistoriaClinicaService
{
    /**
     * Crear nueva historia clínica
     */
    public function create(int $pacienteId, array $data): HistoriaClinica
    {
        // Verificar que el paciente existe
        $paciente = Paciente::find($pacienteId);
        if (!$paciente) {
            throw ValidationException::withMessages([
                'paciente_id' => 'El paciente no existe.'
            ]);
        }

        // Verificar que no existe historia clínica previa
        if ($paciente->historiaClinica) {
            throw ValidationException::withMessages([
                'paciente_id' => 'El paciente ya tiene una historia clínica.'
            ]);
        }

        return DB::transaction(function () use ($pacienteId, $data) {
            $historiaData = array_merge($data, [
                'paciente_id' => $pacienteId,
                'fecha_apertura' => now(),
                'numero_historia' => $this->generateHistoryNumber(),
            ]);

            $historia = HistoriaClinica::create($historiaData);

            // Log de auditoría
            AuditService::logMedicalDataAccess(
                'historia_clinica_creation',
                $pacienteId,
                'Creación de historia clínica'
            );

            return $historia;
        });
    }

    /**
     * Agregar evolución a la historia clínica
     */
    public function addEvolucion(int $historiaId, array $data): Evolucion
    {
        $historia = HistoriaClinica::find($historiaId);
        if (!$historia) {
            throw ValidationException::withMessages([
                'historia_id' => 'La historia clínica no existe.'
            ]);
        }

        return DB::transaction(function () use ($historiaId, $data, $historia) {
            $evolucionData = array_merge($data, [
                'historia_clinica_id' => $historiaId,
                'fecha_evolucion' => $data['fecha_evolucion'] ?? now(),
                'numero_evolucion' => $this->getNextEvolutionNumber($historiaId),
            ]);

            $evolucion = Evolucion::create($evolucionData);

            // Actualizar fecha de última modificación de la historia
            $historia->update(['updated_at' => now()]);

            // Log de auditoría
            AuditService::logMedicalDataAccess(
                'evolucion_creation',
                $historia->paciente_id,
                'Agregó evolución médica'
            );

            return $evolucion;
        });
    }

    /**
     * Obtener historia clínica completa de un paciente
     */
    public function getHistoriaCompleta(int $pacienteId): ?array
    {
        // Log de acceso a datos médicos
        AuditService::logMedicalDataAccess(
            'historia_clinica_access',
            $pacienteId,
            'Consulta de historia clínica completa'
        );

        $paciente = Paciente::with([
            'historiaClinica.evoluciones' => function ($query) {
                $query->orderBy('fecha_evolucion', 'desc');
            },
            'turnos' => function ($query) {
                $query->with(['doctor.especialidad'])
                      ->whereIn('estado', ['completado'])
                      ->orderBy('fecha', 'desc');
            }
        ])->find($pacienteId);

        if (!$paciente) {
            return null;
        }

        return [
            'paciente' => [
                'id' => $paciente->id,
                'nombre' => $paciente->nombre,
                'apellido' => $paciente->apellido,
                'dni' => $paciente->dni,
                'fecha_nacimiento' => $paciente->fecha_nacimiento,
                'edad' => $paciente->fecha_nacimiento ? Carbon::parse($paciente->fecha_nacimiento)->age : null,
                'telefono' => $paciente->telefono,
                'email' => $paciente->email,
                'direccion' => $paciente->direccion,
            ],
            'historia_clinica' => $paciente->historiaClinica ? [
                'id' => $paciente->historiaClinica->id,
                'numero_historia' => $paciente->historiaClinica->numero_historia,
                'fecha_apertura' => $paciente->historiaClinica->fecha_apertura,
                'antecedentes_personales' => $paciente->historiaClinica->antecedentes_personales,
                'antecedentes_familiares' => $paciente->historiaClinica->antecedentes_familiares,
                'alergias' => $paciente->historiaClinica->alergias,
                'medicamentos_actuales' => $paciente->historiaClinica->medicamentos_actuales,
                'observaciones' => $paciente->historiaClinica->observaciones,
            ] : null,
            'evoluciones' => $paciente->historiaClinica ? 
                $paciente->historiaClinica->evoluciones->map(function ($evolucion) {
                    return [
                        'id' => $evolucion->id,
                        'numero_evolucion' => $evolucion->numero_evolucion,
                        'fecha_evolucion' => $evolucion->fecha_evolucion,
                        'motivo_consulta' => $evolucion->motivo_consulta,
                        'anamnesis' => $evolucion->anamnesis,
                        'examen_fisico' => $evolucion->examen_fisico,
                        'diagnosticos' => $evolucion->diagnosticos,
                        'tratamiento' => $evolucion->tratamiento,
                        'indicaciones' => $evolucion->indicaciones,
                        'observaciones' => $evolucion->observaciones,
                        'doctor_id' => $evolucion->doctor_id,
                        'doctor' => $evolucion->doctor ? [
                            'nombre' => $evolucion->doctor->nombre,
                            'apellido' => $evolucion->doctor->apellido,
                            'especialidad' => $evolucion->doctor->especialidad->nombre ?? null,
                        ] : null,
                    ];
                })->toArray() : [],
            'consultas_anteriores' => $paciente->turnos->map(function ($turno) {
                return [
                    'fecha' => $turno->fecha,
                    'hora' => $turno->hora_inicio,
                    'doctor' => $turno->doctor->nombre . ' ' . $turno->doctor->apellido,
                    'especialidad' => $turno->doctor->especialidad->nombre ?? 'Sin especialidad',
                    'motivo' => $turno->motivo_consulta,
                ];
            })->toArray(),
        ];
    }

    /**
     * Buscar historias clínicas por criterios
     */
    public function search(array $criteria): array
    {
        $query = HistoriaClinica::with(['paciente']);

        if (isset($criteria['paciente_dni'])) {
            $query->whereHas('paciente', function ($q) use ($criteria) {
                $q->where('dni', 'like', '%' . $criteria['paciente_dni'] . '%');
            });
        }

        if (isset($criteria['paciente_nombre'])) {
            $query->whereHas('paciente', function ($q) use ($criteria) {
                $q->where('nombre', 'like', '%' . $criteria['paciente_nombre'] . '%')
                  ->orWhere('apellido', 'like', '%' . $criteria['paciente_nombre'] . '%');
            });
        }

        if (isset($criteria['numero_historia'])) {
            $query->where('numero_historia', 'like', '%' . $criteria['numero_historia'] . '%');
        }

        if (isset($criteria['fecha_desde'])) {
            $query->where('fecha_apertura', '>=', $criteria['fecha_desde']);
        }

        if (isset($criteria['fecha_hasta'])) {
            $query->where('fecha_apertura', '<=', $criteria['fecha_hasta']);
        }

        return $query->orderBy('fecha_apertura', 'desc')
                     ->paginate(20)
                     ->toArray();
    }

    /**
     * Actualizar historia clínica
     */
    public function updateHistoria(int $historiaId, array $data): HistoriaClinica
    {
        $historia = HistoriaClinica::find($historiaId);
        if (!$historia) {
            throw ValidationException::withMessages([
                'historia_id' => 'La historia clínica no existe.'
            ]);
        }

        $oldData = $historia->toArray();
        $historia->update($data);

        // Log de auditoría
        AuditService::logActivity(
            'update',
            'historia_clinica',
            $historiaId,
            null,
            $oldData,
            $data
        );

        AuditService::logMedicalDataAccess(
            'historia_clinica_update',
            $historia->paciente_id,
            'Actualización de historia clínica'
        );

        return $historia;
    }

    /**
     * Actualizar evolución
     */
    public function updateEvolucion(int $evolucionId, array $data): Evolucion
    {
        $evolucion = Evolucion::find($evolucionId);
        if (!$evolucion) {
            throw ValidationException::withMessages([
                'evolucion_id' => 'La evolución no existe.'
            ]);
        }

        $oldData = $evolucion->toArray();
        $evolucion->update($data);

        // Log de auditoría
        AuditService::logActivity(
            'update',
            'evoluciones',
            $evolucionId,
            null,
            $oldData,
            $data
        );

        AuditService::logMedicalDataAccess(
            'evolucion_update',
            $evolucion->historiaClinica->paciente_id,
            'Actualización de evolución médica'
        );

        return $evolucion;
    }

    /**
     * Obtener resumen estadístico de una historia clínica
     */
    public function getResumenEstadistico(int $historiaId): array
    {
        $historia = HistoriaClinica::with(['evoluciones', 'paciente.turnos'])->find($historiaId);
        
        if (!$historia) {
            return [];
        }

        $evoluciones = $historia->evoluciones;
        $turnos = $historia->paciente->turnos;

        return [
            'total_evoluciones' => $evoluciones->count(),
            'primera_consulta' => $evoluciones->min('fecha_evolucion'),
            'ultima_consulta' => $evoluciones->max('fecha_evolucion'),
            'total_consultas' => $turnos->whereIn('estado', ['completado'])->count(),
            'consultas_canceladas' => $turnos->where('estado', 'cancelado')->count(),
            'especialidades_consultadas' => $turnos->pluck('doctor.especialidad.nombre')->unique()->filter()->count(),
            'diagnosticos_frecuentes' => $this->getDiagnosticosFrecuentes($evoluciones),
            'medicamentos_frecuentes' => $this->getMedicamentosFrecuentes($evoluciones),
        ];
    }

    /**
     * Generar número de historia clínica único
     */
    private function generateHistoryNumber(): string
    {
        $year = date('Y');
        $lastNumber = HistoriaClinica::whereYear('fecha_apertura', $year)
                                    ->max('numero_historia');
        
        if ($lastNumber) {
            $lastSequence = (int) substr($lastNumber, -6);
            $newSequence = $lastSequence + 1;
        } else {
            $newSequence = 1;
        }

        return $year . '-' . str_pad($newSequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Obtener siguiente número de evolución
     */
    private function getNextEvolutionNumber(int $historiaId): int
    {
        return Evolucion::where('historia_clinica_id', $historiaId)->max('numero_evolucion') + 1;
    }

    /**
     * Obtener diagnósticos más frecuentes
     */
    private function getDiagnosticosFrecuentes($evoluciones): array
    {
        $diagnosticos = [];
        
        foreach ($evoluciones as $evolucion) {
            if ($evolucion->diagnosticos) {
                $diagArray = is_string($evolucion->diagnosticos) ? 
                    json_decode($evolucion->diagnosticos, true) : 
                    $evolucion->diagnosticos;
                
                if (is_array($diagArray)) {
                    foreach ($diagArray as $diag) {
                        $diagnosticos[] = $diag;
                    }
                }
            }
        }

        return array_count_values($diagnosticos);
    }

    /**
     * Obtener medicamentos más frecuentes
     */
    private function getMedicamentosFrecuentes($evoluciones): array
    {
        $medicamentos = [];
        
        foreach ($evoluciones as $evolucion) {
            if ($evolucion->tratamiento) {
                // Extraer medicamentos del tratamiento (lógica simplificada)
                $medicamentos[] = $evolucion->tratamiento;
            }
        }

        return array_count_values($medicamentos);
    }
}
