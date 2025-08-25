<?php

namespace App\Repositories;

use App\Models\Especialidad;
use App\Models\Doctor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Repository empresarial para el modelo Especialidad
 * Maneja operaciones optimizadas con cache y validaciones médicas
 * Incluye funcionalidades específicas para gestión de especialidades médicas
 */
class EspecialidadRepository
{
    protected Especialidad $model;
    protected int $cacheMinutes = 60;
    protected string $cachePrefix = 'especialidad_';

    public function __construct(Especialidad $model)
    {
        $this->model = $model;
    }

    /**
     * Crear una nueva especialidad con validaciones empresariales
     */
    public function create(array $data): Especialidad
    {
        try {
            DB::beginTransaction();

            // Validar unicidad del nombre
            if ($this->existsByName($data['nombre'])) {
                throw new \Exception("Ya existe una especialidad con este nombre");
            }

            // Validar código único si se proporciona
            if (isset($data['codigo']) && $this->existsByCode($data['codigo'])) {
                throw new \Exception("Ya existe una especialidad con este código");
            }

            $especialidad = $this->model->create($data);

            // Limpiar cache relacionado
            $this->clearRelatedCache();

            DB::commit();

            Log::info('Especialidad creada exitosamente', [
                'especialidad_id' => $especialidad->id,
                'nombre' => $especialidad->nombre,
                'codigo' => $especialidad->codigo ?? 'N/A'
            ]);

            return $especialidad;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear especialidad', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Buscar especialidad por ID con cache
     */
    public function findById(int $id): ?Especialidad
    {
        return Cache::remember(
            $this->cachePrefix . "id_{$id}",
            $this->cacheMinutes,
            function () use ($id) {
                return $this->model->find($id);
            }
        );
    }

    /**
     * Buscar especialidad por nombre con cache
     */
    public function findByName(string $nombre): ?Especialidad
    {
        return Cache::remember(
            $this->cachePrefix . "name_" . md5($nombre),
            $this->cacheMinutes,
            function () use ($nombre) {
                return $this->model->where('nombre', $nombre)->first();
            }
        );
    }

    /**
     * Buscar especialidad por código con cache
     */
    public function findByCode(string $codigo): ?Especialidad
    {
        return Cache::remember(
            $this->cachePrefix . "code_{$codigo}",
            $this->cacheMinutes,
            function () use ($codigo) {
                return $this->model->where('codigo', $codigo)->first();
            }
        );
    }

    /**
     * Actualizar una especialidad con auditoría
     */
    public function update(int $id, array $data): bool
    {
        try {
            DB::beginTransaction();

            $especialidad = $this->findById($id);
            if (!$especialidad) {
                throw new \Exception("Especialidad no encontrada con ID: {$id}");
            }

            // Validar unicidad del nombre (excluyendo la especialidad actual)
            if (isset($data['nombre']) && $this->existsByName($data['nombre'], $id)) {
                throw new \Exception("Ya existe otra especialidad con este nombre");
            }

            // Validar código único si se proporciona (excluyendo la especialidad actual)
            if (isset($data['codigo']) && $this->existsByCode($data['codigo'], $id)) {
                throw new \Exception("Ya existe otra especialidad con este código");
            }

            $updated = $especialidad->update($data);

            // Limpiar cache específico
            $this->clearEspecialidadCache($id);
            $this->clearRelatedCache();

            DB::commit();

            Log::info('Especialidad actualizada exitosamente', [
                'especialidad_id' => $id,
                'updated_fields' => array_keys($data)
            ]);

            return $updated;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar especialidad', [
                'especialidad_id' => $id,
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Eliminar una especialidad (soft delete) con validaciones
     */
    public function delete(int $id): bool
    {
        try {
            DB::beginTransaction();

            $especialidad = $this->findById($id);
            if (!$especialidad) {
                throw new \Exception("Especialidad no encontrada con ID: {$id}");
            }

            // Verificar que no tenga doctores asociados activos
            $activeDoctors = Doctor::where('especialidad_id', $id)
                ->where('activo', true)
                ->exists();

            if ($activeDoctors) {
                throw new \Exception("No se puede eliminar la especialidad porque tiene doctores activos asociados");
            }

            $deleted = $especialidad->delete();

            // Limpiar cache
            $this->clearEspecialidadCache($id);
            $this->clearRelatedCache();

            DB::commit();

            Log::warning('Especialidad eliminada', [
                'especialidad_id' => $id,
                'nombre' => $especialidad->nombre
            ]);

            return $deleted;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar especialidad', [
                'especialidad_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtener todas las especialidades activas
     */
    public function findActive(): Collection
    {
        return Cache::remember(
            $this->cachePrefix . 'all_active',
            $this->cacheMinutes,
            function () {
                return $this->model->where('activa', true)
                    ->orderBy('nombre')
                    ->get();
            }
        );
    }

    /**
     * Obtener especialidades paginadas con filtros
     */
    public function getPaginated(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->model->newQuery();

        // Aplicar filtros
        $query = $this->applyFilters($query, $filters);

        return $query->orderBy('nombre')
                    ->paginate($perPage);
    }

    /**
     * Buscar especialidades por múltiples criterios
     */
    public function search(array $criteria): Collection
    {
        $query = $this->model->newQuery();

        if (!empty($criteria['search'])) {
            $search = $criteria['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%")
                  ->orWhere('codigo', 'like', "%{$search}%");
            });
        }

        return $this->applyFilters($query, $criteria)
                   ->orderBy('nombre')
                   ->get();
    }

    /**
     * Obtener especialidades con sus doctores
     */
    public function findWithDoctors(int $id): ?Especialidad
    {
        return Cache::remember(
            $this->cachePrefix . "with_doctors_{$id}",
            30,
            function () use ($id) {
                return $this->model->with([
                    'doctors' => function ($query) {
                        $query->where('activo', true)
                              ->orderBy('apellido')
                              ->orderBy('nombre');
                    }
                ])->find($id);
            }
        );
    }

    /**
     * Obtener estadísticas de especialidades
     */
    public function getStatistics(): array
    {
        return Cache::remember(
            $this->cachePrefix . 'statistics',
            120, // 2 horas
            function () {
                $total = $this->model->count();
                $activas = $this->model->where('activa', true)->count();

                // Estadísticas por doctores asociados
                $withDoctors = $this->model->whereHas('doctors')->count();
                $withActiveDoctors = $this->model->whereHas('doctors', function ($query) {
                    $query->where('activo', true);
                })->count();

                // Top especialidades por cantidad de doctores
                $topByDoctors = $this->model->withCount(['doctors' => function ($query) {
                    $query->where('activo', true);
                }])
                ->orderBy('doctors_count', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($especialidad) {
                    return [
                        'nombre' => $especialidad->nombre,
                        'doctors_count' => $especialidad->doctors_count
                    ];
                });

                return [
                    'total' => $total,
                    'activas' => $activas,
                    'inactivas' => $total - $activas,
                    'con_doctores' => $withDoctors,
                    'con_doctores_activos' => $withActiveDoctors,
                    'sin_doctores' => $total - $withDoctors,
                    'tasa_utilizacion' => $total > 0 ? ($withActiveDoctors / $total) * 100 : 0,
                    'top_por_doctores' => $topByDoctors,
                ];
            }
        );
    }

    /**
     * Obtener especialidades más solicitadas (por turnos)
     */
    public function getMostRequested(int $limit = 10, \Carbon\Carbon $startDate = null): Collection
    {
        $startDate = $startDate ?? now()->subMonths(3);
        $cacheKey = $this->cachePrefix . "most_requested_{$limit}_{$startDate->format('Y-m-d')}";

        return Cache::remember($cacheKey, 60, function () use ($limit, $startDate) {
            return $this->model->select('especialidads.*')
                ->selectRaw('COUNT(turnos.id) as turnos_count')
                ->join('doctors', 'especialidads.id', '=', 'doctors.especialidad_id')
                ->join('turnos', 'doctors.id', '=', 'turnos.doctor_id')
                ->where('turnos.fecha', '>=', $startDate->format('Y-m-d'))
                ->whereIn('turnos.estado', ['programado', 'confirmado', 'completado'])
                ->groupBy('especialidads.id')
                ->orderBy('turnos_count', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Verificar si existe una especialidad por nombre
     */
    public function existsByName(string $nombre, int $excludeId = null): bool
    {
        $query = $this->model->where('nombre', $nombre);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Verificar si existe una especialidad por código
     */
    public function existsByCode(string $codigo, int $excludeId = null): bool
    {
        $query = $this->model->where('codigo', $codigo);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Activar/desactivar especialidad
     */
    public function toggleStatus(int $id): bool
    {
        try {
            DB::beginTransaction();

            $especialidad = $this->findById($id);
            if (!$especialidad) {
                throw new \Exception("Especialidad no encontrada con ID: {$id}");
            }

            $newStatus = !$especialidad->activa;

            // Si se está desactivando, verificar que no tenga doctores activos
            if (!$newStatus) {
                $activeDoctors = Doctor::where('especialidad_id', $id)
                    ->where('activo', true)
                    ->exists();

                if ($activeDoctors) {
                    throw new \Exception("No se puede desactivar la especialidad porque tiene doctores activos asociados");
                }
            }

            $updated = $especialidad->update(['activa' => $newStatus]);

            // Limpiar cache
            $this->clearEspecialidadCache($id);
            $this->clearRelatedCache();

            DB::commit();

            Log::info('Estado de especialidad cambiado', [
                'especialidad_id' => $id,
                'new_status' => $newStatus ? 'activa' : 'inactiva'
            ]);

            return $updated;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al cambiar estado de especialidad', [
                'especialidad_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
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
        if (isset($filters['activa'])) {
            $query->where('activa', $filters['activa']);
        }

        if (isset($filters['codigo'])) {
            $query->where('codigo', 'like', "%{$filters['codigo']}%");
        }

        if (isset($filters['con_doctores'])) {
            if ($filters['con_doctores']) {
                $query->whereHas('doctors');
            } else {
                $query->whereDoesntHave('doctors');
            }
        }

        if (isset($filters['con_doctores_activos'])) {
            if ($filters['con_doctores_activos']) {
                $query->whereHas('doctors', function ($q) {
                    $q->where('activo', true);
                });
            }
        }

        return $query;
    }

    /**
     * Limpiar cache específico de la especialidad
     */
    private function clearEspecialidadCache(int $especialidadId): void
    {
        $especialidad = $this->model->find($especialidadId);
        if ($especialidad) {
            $keys = [
                $this->cachePrefix . "id_{$especialidadId}",
                $this->cachePrefix . "name_" . md5($especialidad->nombre),
                $this->cachePrefix . "with_doctors_{$especialidadId}",
            ];

            if ($especialidad->codigo) {
                $keys[] = $this->cachePrefix . "code_{$especialidad->codigo}";
            }

            foreach ($keys as $key) {
                Cache::forget($key);
            }
        }
    }

    /**
     * Limpiar cache relacionado
     */
    private function clearRelatedCache(): void
    {
        Cache::forget($this->cachePrefix . 'all_active');
        Cache::forget($this->cachePrefix . 'statistics');
        
        // Limpiar cache de especialidades más solicitadas
        $patterns = [
            $this->cachePrefix . 'most_requested_*'
        ];

        foreach ($patterns as $pattern) {
            // En un entorno real, usar un sistema que soporte patterns
            // Por ahora limpiamos manualmente algunos keys comunes
            for ($i = 1; $i <= 30; $i++) {
                $date = now()->subDays($i)->format('Y-m-d');
                Cache::forget($this->cachePrefix . "most_requested_10_{$date}");
            }
        }
    }
}
