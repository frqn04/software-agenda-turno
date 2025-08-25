<?php

namespace App\Repositories;

use App\Models\Evolucion;
use App\Models\HistoriaClinica;
use App\Models\Doctor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

/**
 * Repository empresarial para el modelo Evolucion
 * Maneja operaciones optimizadas con cache, validaciones médicas y auditoría
 * Incluye funcionalidades específicas para gestión de evoluciones médicas
 */
class EvolucionRepository
{
    protected Evolucion $model;
    protected int $cacheMinutes = 20;
    protected string $cachePrefix = 'evolucion_';

    public function __construct(Evolucion $model)
    {
        $this->model = $model;
    }

    /**
     * Crear una nueva evolución con validaciones empresariales
     */
    public function create(array $data): Evolucion
    {
        try {
            DB::beginTransaction();

            // Validar que la historia clínica existe y está activa
            $historia = HistoriaClinica::find($data['historia_clinica_id']);
            if (!$historia) {
                throw new \Exception("Historia clínica no encontrada");
            }

            if ($historia->fecha_cierre) {
                throw new \Exception("No se puede agregar evoluciones a una historia clínica cerrada");
            }

            // Validar que el doctor existe y está activo
            $doctor = Doctor::find($data['doctor_id']);
            if (!$doctor || !$doctor->activo) {
                throw new \Exception("Doctor no válido o inactivo");
            }

            // Establecer fecha y hora actual si no se proporciona
            $data['fecha'] = $data['fecha'] ?? now()->format('Y-m-d');
            $data['hora'] = $data['hora'] ?? now()->format('H:i:s');

            // Validar que no exista otra evolución del mismo doctor en la misma fecha y hora
            if ($this->existsByDoctorAndDateTime($data['doctor_id'], $data['historia_clinica_id'], $data['fecha'], $data['hora'])) {
                throw new \Exception("Ya existe una evolución del mismo doctor en esta fecha y hora");
            }

            $evolucion = $this->model->create($data);

            // Actualizar fecha de última evolución en la historia clínica
            $historia->update(['ultima_evolucion' => now()]);

            // Limpiar cache relacionado
            $this->clearRelatedCache($data['historia_clinica_id'], $data['doctor_id']);

            DB::commit();

            Log::info('Evolución creada exitosamente', [
                'evolucion_id' => $evolucion->id,
                'historia_clinica_id' => $data['historia_clinica_id'],
                'doctor_id' => $data['doctor_id'],
                'fecha' => $data['fecha']
            ]);

            return $evolucion;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear evolución', [
                'error' => $e->getMessage(),
                'data' => array_except($data, ['evolucion', 'observaciones_privadas'])
            ]);
            throw $e;
        }
    }

    /**
     * Buscar evolución por ID con cache
     */
    public function findById(int $id): ?Evolucion
    {
        return Cache::remember(
            $this->cachePrefix . "id_{$id}",
            $this->cacheMinutes,
            function () use ($id) {
                return $this->model->with(['doctor.especialidad', 'historiaClinica.paciente'])->find($id);
            }
        );
    }

    /**
     * Actualizar una evolución con auditoría
     */
    public function update(int $id, array $data): bool
    {
        try {
            DB::beginTransaction();

            $evolucion = $this->findById($id);
            if (!$evolucion) {
                throw new \Exception("Evolución no encontrada con ID: {$id}");
            }

            // Verificar que la historia clínica no esté cerrada
            if ($evolucion->historiaClinica->fecha_cierre) {
                throw new \Exception("No se puede modificar evoluciones de una historia clínica cerrada");
            }

            // No permitir cambio de historia clínica o doctor
            if (isset($data['historia_clinica_id']) && $data['historia_clinica_id'] != $evolucion->historia_clinica_id) {
                throw new \Exception("No se puede cambiar la historia clínica de una evolución existente");
            }

            if (isset($data['doctor_id']) && $data['doctor_id'] != $evolucion->doctor_id) {
                throw new \Exception("No se puede cambiar el doctor de una evolución existente");
            }

            // Validar cambio de fecha/hora si se proporciona
            if (isset($data['fecha']) || isset($data['hora'])) {
                $newFecha = $data['fecha'] ?? $evolucion->fecha;
                $newHora = $data['hora'] ?? $evolucion->hora;
                
                if ($this->existsByDoctorAndDateTime($evolucion->doctor_id, $evolucion->historia_clinica_id, $newFecha, $newHora, $id)) {
                    throw new \Exception("Ya existe otra evolución del mismo doctor en esta fecha y hora");
                }
            }

            $updated = $evolucion->update($data);

            // Limpiar cache específico
            $this->clearEvolucionCache($id);
            $this->clearRelatedCache($evolucion->historia_clinica_id, $evolucion->doctor_id);

            DB::commit();

            Log::info('Evolución actualizada exitosamente', [
                'evolucion_id' => $id,
                'updated_fields' => array_keys($data)
            ]);

            return $updated;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar evolución', [
                'evolucion_id' => $id,
                'error' => $e->getMessage(),
                'data' => array_except($data, ['evolucion', 'observaciones_privadas'])
            ]);
            throw $e;
        }
    }

    /**
     * Eliminar una evolución (soft delete) con validaciones
     */
    public function delete(int $id): bool
    {
        try {
            DB::beginTransaction();

            $evolucion = $this->findById($id);
            if (!$evolucion) {
                throw new \Exception("Evolución no encontrada con ID: {$id}");
            }

            // Verificar que la historia clínica no esté cerrada
            if ($evolucion->historiaClinica->fecha_cierre) {
                throw new \Exception("No se puede eliminar evoluciones de una historia clínica cerrada");
            }

            // Solo permitir eliminar evoluciones recientes (últimas 24 horas)
            $evolutionDateTime = Carbon::parse($evolucion->fecha . ' ' . $evolucion->hora);
            if ($evolutionDateTime->lt(now()->subHours(24))) {
                throw new \Exception("Solo se pueden eliminar evoluciones creadas en las últimas 24 horas");
            }

            $deleted = $evolucion->delete();

            // Limpiar cache
            $this->clearEvolucionCache($id);
            $this->clearRelatedCache($evolucion->historia_clinica_id, $evolucion->doctor_id);

            DB::commit();

            Log::warning('Evolución eliminada', [
                'evolucion_id' => $id,
                'historia_clinica_id' => $evolucion->historia_clinica_id
            ]);

            return $deleted;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar evolución', [
                'evolucion_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtener evoluciones paginadas con filtros
     */
    public function getPaginated(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->model->with(['doctor.especialidad', 'historiaClinica.paciente']);

        // Aplicar filtros
        $query = $this->applyFilters($query, $filters);

        return $query->orderBy('fecha', 'desc')
                    ->orderBy('hora', 'desc')
                    ->paginate($perPage);
    }

    /**
     * Buscar evoluciones por historia clínica
     */
    public function findByHistoriaClinica(int $historiaClinicaId, int $limit = 20): Collection
    {
        return Cache::remember(
            $this->cachePrefix . "historia_{$historiaClinicaId}_limit_{$limit}",
            $this->cacheMinutes,
            function () use ($historiaClinicaId, $limit) {
                return $this->model->with(['doctor.especialidad'])
                    ->where('historia_clinica_id', $historiaClinicaId)
                    ->orderBy('fecha', 'desc')
                    ->orderBy('hora', 'desc')
                    ->limit($limit)
                    ->get();
            }
        );
    }

    /**
     * Buscar evoluciones por doctor
     */
    public function findByDoctor(int $doctorId, int $limit = 20): Collection
    {
        return Cache::remember(
            $this->cachePrefix . "doctor_{$doctorId}_limit_{$limit}",
            $this->cacheMinutes,
            function () use ($doctorId, $limit) {
                return $this->model->with(['historiaClinica.paciente'])
                    ->where('doctor_id', $doctorId)
                    ->orderBy('fecha', 'desc')
                    ->orderBy('hora', 'desc')
                    ->limit($limit)
                    ->get();
            }
        );
    }

    /**
     * Buscar evoluciones por múltiples criterios
     */
    public function search(array $criteria): Collection
    {
        $query = $this->model->with(['doctor.especialidad', 'historiaClinica.paciente']);

        if (!empty($criteria['search'])) {
            $search = $criteria['search'];
            $query->where(function ($q) use ($search) {
                $q->where('evolucion', 'like', "%{$search}%")
                  ->orWhere('diagnostico', 'like', "%{$search}%")
                  ->orWhere('tratamiento', 'like', "%{$search}%")
                  ->orWhereHas('doctor', function ($subQ) use ($search) {
                      $subQ->where('nombre', 'like', "%{$search}%")
                           ->orWhere('apellido', 'like', "%{$search}%");
                  })
                  ->orWhereHas('historiaClinica.paciente', function ($subQ) use ($search) {
                      $subQ->where('nombre', 'like', "%{$search}%")
                           ->orWhere('apellido', 'like', "%{$search}%")
                           ->orWhere('dni', 'like', "%{$search}%");
                  });
            });
        }

        return $this->applyFilters($query, $criteria)
                   ->orderBy('fecha', 'desc')
                   ->orderBy('hora', 'desc')
                   ->get();
    }

    /**
     * Obtener evoluciones recientes (últimos N días)
     */
    public function getRecent(int $days = 7): Collection
    {
        return Cache::remember(
            $this->cachePrefix . "recent_{$days}",
            10, // 10 minutos para datos recientes
            function () use ($days) {
                return $this->model->with(['doctor.especialidad', 'historiaClinica.paciente'])
                    ->where('fecha', '>=', now()->subDays($days)->format('Y-m-d'))
                    ->orderBy('fecha', 'desc')
                    ->orderBy('hora', 'desc')
                    ->get();
            }
        );
    }

    /**
     * Obtener evoluciones de hoy
     */
    public function getToday(): Collection
    {
        return Cache::remember(
            $this->cachePrefix . 'today_' . now()->format('Y-m-d'),
            5, // 5 minutos para datos del día
            function () {
                return $this->model->with(['doctor.especialidad', 'historiaClinica.paciente'])
                    ->where('fecha', now()->format('Y-m-d'))
                    ->orderBy('hora', 'desc')
                    ->get();
            }
        );
    }

    /**
     * Obtener estadísticas de evoluciones
     */
    public function getStatistics(Carbon $startDate = null, Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth();
        $endDate = $endDate ?? now()->endOfMonth();
        
        $cacheKey = $this->cachePrefix . "stats_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}";

        return Cache::remember($cacheKey, 60, function () use ($startDate, $endDate) {
            $baseQuery = $this->model->whereBetween('fecha', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);

            $total = $baseQuery->count();
            $hoy = $this->model->where('fecha', now()->format('Y-m-d'))->count();
            $esta_semana = $this->model->whereBetween('fecha', [
                now()->startOfWeek()->format('Y-m-d'),
                now()->endOfWeek()->format('Y-m-d')
            ])->count();

            // Estadísticas por doctor
            $porDoctor = DB::table('evolucions')
                ->join('doctors', 'evolucions.doctor_id', '=', 'doctors.id')
                ->select('doctors.nombre', 'doctors.apellido', DB::raw('COUNT(*) as total'))
                ->whereBetween('evolucions.fecha', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->groupBy('doctors.id', 'doctors.nombre', 'doctors.apellido')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'doctor' => $item->nombre . ' ' . $item->apellido,
                        'total' => $item->total
                    ];
                })
                ->toArray();

            // Estadísticas por especialidad
            $porEspecialidad = DB::table('evolucions')
                ->join('doctors', 'evolucions.doctor_id', '=', 'doctors.id')
                ->join('especialidads', 'doctors.especialidad_id', '=', 'especialidads.id')
                ->select('especialidads.nombre', DB::raw('COUNT(*) as total'))
                ->whereBetween('evolucions.fecha', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->groupBy('especialidads.id', 'especialidads.nombre')
                ->orderBy('total', 'desc')
                ->get()
                ->toArray();

            // Promedio de evoluciones por día
            $days = $startDate->diffInDays($endDate) + 1;
            $promedioPorDia = $days > 0 ? $total / $days : 0;

            return [
                'total' => $total,
                'hoy' => $hoy,
                'esta_semana' => $esta_semana,
                'promedio_por_dia' => round($promedioPorDia, 2),
                'por_doctor' => $porDoctor,
                'por_especialidad' => $porEspecialidad,
            ];
        });
    }

    /**
     * Obtener evoluciones por rango de fechas
     */
    public function findByDateRange(Carbon $startDate, Carbon $endDate): Collection
    {
        return Cache::remember(
            $this->cachePrefix . "date_range_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}",
            30,
            function () use ($startDate, $endDate) {
                return $this->model->with(['doctor.especialidad', 'historiaClinica.paciente'])
                    ->whereBetween('fecha', [
                        $startDate->format('Y-m-d'),
                        $endDate->format('Y-m-d')
                    ])
                    ->orderBy('fecha', 'desc')
                    ->orderBy('hora', 'desc')
                    ->get();
            }
        );
    }

    /**
     * Verificar si existe una evolución por doctor, historia y fecha/hora
     */
    public function existsByDoctorAndDateTime(int $doctorId, int $historiaClinicaId, string $fecha, string $hora, int $excludeId = null): bool
    {
        $query = $this->model->where('doctor_id', $doctorId)
            ->where('historia_clinica_id', $historiaClinicaId)
            ->where('fecha', $fecha)
            ->where('hora', $hora);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Obtener última evolución de una historia clínica
     */
    public function getLastByHistoriaClinica(int $historiaClinicaId): ?Evolucion
    {
        return Cache::remember(
            $this->cachePrefix . "last_historia_{$historiaClinicaId}",
            15,
            function () use ($historiaClinicaId) {
                return $this->model->with(['doctor.especialidad'])
                    ->where('historia_clinica_id', $historiaClinicaId)
                    ->orderBy('fecha', 'desc')
                    ->orderBy('hora', 'desc')
                    ->first();
            }
        );
    }

    /**
     * Obtener modelo base para queries personalizadas
     */
    public function query(): Builder
    {
        return $this->model->newQuery();
    }

    /**
     * Aplicar filtros a la query
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (isset($filters['doctor_id'])) {
            $query->where('doctor_id', $filters['doctor_id']);
        }

        if (isset($filters['historia_clinica_id'])) {
            $query->where('historia_clinica_id', $filters['historia_clinica_id']);
        }

        if (isset($filters['fecha_desde'])) {
            $query->where('fecha', '>=', $filters['fecha_desde']);
        }

        if (isset($filters['fecha_hasta'])) {
            $query->where('fecha', '<=', $filters['fecha_hasta']);
        }

        if (isset($filters['especialidad_id'])) {
            $query->whereHas('doctor', function ($q) use ($filters) {
                $q->where('especialidad_id', $filters['especialidad_id']);
            });
        }

        if (isset($filters['paciente_id'])) {
            $query->whereHas('historiaClinica', function ($q) use ($filters) {
                $q->where('paciente_id', $filters['paciente_id']);
            });
        }

        if (isset($filters['tipo_evolucion'])) {
            $query->where('tipo_evolucion', $filters['tipo_evolucion']);
        }

        if (isset($filters['con_diagnostico'])) {
            if ($filters['con_diagnostico']) {
                $query->whereNotNull('diagnostico');
            } else {
                $query->whereNull('diagnostico');
            }
        }

        if (isset($filters['con_tratamiento'])) {
            if ($filters['con_tratamiento']) {
                $query->whereNotNull('tratamiento');
            } else {
                $query->whereNull('tratamiento');
            }
        }

        return $query;
    }

    /**
     * Limpiar cache específico de la evolución
     */
    private function clearEvolucionCache(int $evolucionId): void
    {
        $evolucion = $this->model->find($evolucionId);
        if ($evolucion) {
            $keys = [
                $this->cachePrefix . "id_{$evolucionId}",
                $this->cachePrefix . "last_historia_{$evolucion->historia_clinica_id}",
            ];

            foreach ($keys as $key) {
                Cache::forget($key);
            }
        }
    }

    /**
     * Limpiar cache relacionado
     */
    private function clearRelatedCache(int $historiaClinicaId, int $doctorId): void
    {
        $cacheKeys = [
            $this->cachePrefix . "historia_{$historiaClinicaId}_limit_20",
            $this->cachePrefix . "doctor_{$doctorId}_limit_20",
            $this->cachePrefix . "recent_7",
            $this->cachePrefix . "today_" . now()->format('Y-m-d'),
            $this->cachePrefix . "last_historia_{$historiaClinicaId}",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        // Limpiar estadísticas del mes actual
        $currentMonth = now()->startOfMonth()->format('Y-m-d');
        $endMonth = now()->endOfMonth()->format('Y-m-d');
        Cache::forget($this->cachePrefix . "stats_{$currentMonth}_{$endMonth}");

        // Limpiar rangos de fechas comunes
        for ($i = 1; $i <= 7; $i++) {
            $date = now()->subDays($i)->format('Y-m-d');
            $today = now()->format('Y-m-d');
            Cache::forget($this->cachePrefix . "date_range_{$date}_{$today}");
        }
    }
}
