<?php

namespace App\Repositories;

use App\Models\HistoriaClinica;
use App\Models\Paciente;
use App\Models\Evolucion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

/**
 * Repository empresarial para el modelo HistoriaClinica
 * Maneja operaciones optimizadas con cache, validaciones médicas y auditoría
 * Incluye funcionalidades específicas para gestión de historias clínicas
 */
class HistoriaClinicaRepository
{
    protected HistoriaClinica $model;
    protected int $cacheMinutes = 30;
    protected string $cachePrefix = 'historia_clinica_';

    public function __construct(HistoriaClinica $model)
    {
        $this->model = $model;
    }

    /**
     * Crear una nueva historia clínica con validaciones empresariales
     */
    public function create(array $data): HistoriaClinica
    {
        try {
            DB::beginTransaction();

            // Validar que el paciente no tenga historia clínica existente
            if (isset($data['paciente_id']) && $this->existsByPaciente($data['paciente_id'])) {
                throw new \Exception("El paciente ya tiene una historia clínica existente");
            }

            // Generar número de historia si no se proporciona
            if (!isset($data['numero_historia'])) {
                $data['numero_historia'] = $this->generateHistoryNumber();
            }

            // Validar unicidad del número de historia
            if ($this->existsByNumber($data['numero_historia'])) {
                throw new \Exception("Ya existe una historia clínica con este número");
            }

            $data['fecha_apertura'] = $data['fecha_apertura'] ?? now();

            $historia = $this->model->create($data);

            // Limpiar cache relacionado
            $this->clearRelatedCache();

            DB::commit();

            Log::info('Historia clínica creada exitosamente', [
                'historia_id' => $historia->id,
                'numero_historia' => $historia->numero_historia,
                'paciente_id' => $historia->paciente_id
            ]);

            return $historia;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear historia clínica', [
                'error' => $e->getMessage(),
                'data' => array_except($data, ['observaciones_confidenciales'])
            ]);
            throw $e;
        }
    }

    /**
     * Buscar historia clínica por ID con cache
     */
    public function findById(int $id): ?HistoriaClinica
    {
        return Cache::remember(
            $this->cachePrefix . "id_{$id}",
            $this->cacheMinutes,
            function () use ($id) {
                return $this->model->with(['paciente'])->find($id);
            }
        );
    }

    /**
     * Buscar historia clínica por número con cache
     */
    public function findByNumber(string $numeroHistoria): ?HistoriaClinica
    {
        return Cache::remember(
            $this->cachePrefix . "number_{$numeroHistoria}",
            $this->cacheMinutes,
            function () use ($numeroHistoria) {
                return $this->model->with(['paciente'])
                    ->where('numero_historia', $numeroHistoria)
                    ->first();
            }
        );
    }

    /**
     * Buscar historia clínica por paciente con cache
     */
    public function findByPaciente(int $pacienteId): ?HistoriaClinica
    {
        return Cache::remember(
            $this->cachePrefix . "paciente_{$pacienteId}",
            $this->cacheMinutes,
            function () use ($pacienteId) {
                return $this->model->with(['paciente'])
                    ->where('paciente_id', $pacienteId)
                    ->first();
            }
        );
    }

    /**
     * Actualizar una historia clínica con auditoría
     */
    public function update(int $id, array $data): bool
    {
        try {
            DB::beginTransaction();

            $historia = $this->findById($id);
            if (!$historia) {
                throw new \Exception("Historia clínica no encontrada con ID: {$id}");
            }

            // Validar número de historia único si se cambia
            if (isset($data['numero_historia']) && $this->existsByNumber($data['numero_historia'], $id)) {
                throw new \Exception("Ya existe otra historia clínica con este número");
            }

            // No permitir cambio de paciente una vez creada
            if (isset($data['paciente_id']) && $data['paciente_id'] != $historia->paciente_id) {
                throw new \Exception("No se puede cambiar el paciente de una historia clínica existente");
            }

            $updated = $historia->update($data);

            // Limpiar cache específico
            $this->clearHistoriaCache($id);
            $this->clearRelatedCache();

            DB::commit();

            Log::info('Historia clínica actualizada exitosamente', [
                'historia_id' => $id,
                'updated_fields' => array_keys($data)
            ]);

            return $updated;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar historia clínica', [
                'historia_id' => $id,
                'error' => $e->getMessage(),
                'data' => array_except($data, ['observaciones_confidenciales'])
            ]);
            throw $e;
        }
    }

    /**
     * Eliminar una historia clínica (soft delete) con validaciones
     */
    public function delete(int $id): bool
    {
        try {
            DB::beginTransaction();

            $historia = $this->findById($id);
            if (!$historia) {
                throw new \Exception("Historia clínica no encontrada con ID: {$id}");
            }

            // Verificar que no tenga evoluciones asociadas
            $hasEvoluciones = Evolucion::where('historia_clinica_id', $id)->exists();
            if ($hasEvoluciones) {
                throw new \Exception("No se puede eliminar la historia clínica porque tiene evoluciones asociadas");
            }

            $deleted = $historia->delete();

            // Limpiar cache
            $this->clearHistoriaCache($id);
            $this->clearRelatedCache();

            DB::commit();

            Log::warning('Historia clínica eliminada', [
                'historia_id' => $id,
                'numero_historia' => $historia->numero_historia
            ]);

            return $deleted;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar historia clínica', [
                'historia_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtener historias clínicas paginadas con filtros
     */
    public function getPaginated(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->model->with(['paciente']);

        // Aplicar filtros
        $query = $this->applyFilters($query, $filters);

        return $query->orderBy('fecha_apertura', 'desc')
                    ->paginate($perPage);
    }

    /**
     * Buscar historias clínicas por múltiples criterios
     */
    public function search(array $criteria): Collection
    {
        $query = $this->model->with(['paciente']);

        if (!empty($criteria['search'])) {
            $search = $criteria['search'];
            $query->where(function ($q) use ($search) {
                $q->where('numero_historia', 'like', "%{$search}%")
                  ->orWhereHas('paciente', function ($subQ) use ($search) {
                      $subQ->where('nombre', 'like', "%{$search}%")
                           ->orWhere('apellido', 'like', "%{$search}%")
                           ->orWhere('dni', 'like', "%{$search}%");
                  });
            });
        }

        return $this->applyFilters($query, $criteria)
                   ->orderBy('fecha_apertura', 'desc')
                   ->get();
    }

    /**
     * Obtener historia clínica con evoluciones completas
     */
    public function findWithEvoluciones(int $id, int $limit = 20): ?HistoriaClinica
    {
        return Cache::remember(
            $this->cachePrefix . "with_evoluciones_{$id}_{$limit}",
            15, // 15 minutos para datos médicos
            function () use ($id, $limit) {
                return $this->model->with([
                    'paciente',
                    'evoluciones' => function ($query) use ($limit) {
                        $query->with(['doctor.especialidad'])
                              ->orderBy('fecha', 'desc')
                              ->orderBy('created_at', 'desc')
                              ->limit($limit);
                    }
                ])->find($id);
            }
        );
    }

    /**
     * Obtener historias clínicas recientes (últimos N días)
     */
    public function getRecent(int $days = 30): Collection
    {
        return Cache::remember(
            $this->cachePrefix . "recent_{$days}",
            30,
            function () use ($days) {
                return $this->model->with(['paciente'])
                    ->where('fecha_apertura', '>=', now()->subDays($days))
                    ->orderBy('fecha_apertura', 'desc')
                    ->get();
            }
        );
    }

    /**
     * Obtener estadísticas de historias clínicas
     */
    public function getStatistics(): array
    {
        return Cache::remember(
            $this->cachePrefix . 'statistics',
            120, // 2 horas
            function () {
                $total = $this->model->count();
                
                // Estadísticas por rango de fechas de apertura
                $este_mes = $this->model->whereMonth('fecha_apertura', now()->month)
                    ->whereYear('fecha_apertura', now()->year)
                    ->count();
                
                $este_año = $this->model->whereYear('fecha_apertura', now()->year)->count();
                
                // Estadísticas por evoluciones
                $con_evoluciones = $this->model->whereHas('evoluciones')->count();
                $sin_evoluciones = $total - $con_evoluciones;
                
                // Promedio de evoluciones por historia
                $promedioEvoluciones = $this->model->withCount('evoluciones')
                    ->get()
                    ->avg('evoluciones_count');

                // Historias más activas (con más evoluciones)
                $mas_activas = $this->model->with(['paciente'])
                    ->withCount('evoluciones')
                    ->orderBy('evoluciones_count', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function ($historia) {
                        return [
                            'numero_historia' => $historia->numero_historia,
                            'paciente' => $historia->paciente->nombre . ' ' . $historia->paciente->apellido,
                            'evoluciones_count' => $historia->evoluciones_count
                        ];
                    });

                return [
                    'total' => $total,
                    'este_mes' => $este_mes,
                    'este_año' => $este_año,
                    'con_evoluciones' => $con_evoluciones,
                    'sin_evoluciones' => $sin_evoluciones,
                    'promedio_evoluciones' => round($promedioEvoluciones, 2),
                    'tasa_actividad' => $total > 0 ? ($con_evoluciones / $total) * 100 : 0,
                    'mas_activas' => $mas_activas,
                ];
            }
        );
    }

    /**
     * Obtener historias clínicas por rango de fechas
     */
    public function findByDateRange(Carbon $startDate, Carbon $endDate): Collection
    {
        return Cache::remember(
            $this->cachePrefix . "date_range_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}",
            60,
            function () use ($startDate, $endDate) {
                return $this->model->with(['paciente'])
                    ->whereBetween('fecha_apertura', [
                        $startDate->format('Y-m-d'),
                        $endDate->format('Y-m-d')
                    ])
                    ->orderBy('fecha_apertura', 'desc')
                    ->get();
            }
        );
    }

    /**
     * Verificar si existe una historia clínica por paciente
     */
    public function existsByPaciente(int $pacienteId): bool
    {
        return $this->model->where('paciente_id', $pacienteId)->exists();
    }

    /**
     * Verificar si existe una historia clínica por número
     */
    public function existsByNumber(string $numeroHistoria, int $excludeId = null): bool
    {
        $query = $this->model->where('numero_historia', $numeroHistoria);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Cerrar historia clínica
     */
    public function close(int $id, string $motivoCierre = null): bool
    {
        try {
            DB::beginTransaction();

            $historia = $this->findById($id);
            if (!$historia) {
                throw new \Exception("Historia clínica no encontrada con ID: {$id}");
            }

            if ($historia->fecha_cierre) {
                throw new \Exception("La historia clínica ya está cerrada");
            }

            $updated = $historia->update([
                'fecha_cierre' => now(),
                'motivo_cierre' => $motivoCierre,
            ]);

            // Limpiar cache
            $this->clearHistoriaCache($id);
            $this->clearRelatedCache();

            DB::commit();

            Log::info('Historia clínica cerrada', [
                'historia_id' => $id,
                'numero_historia' => $historia->numero_historia,
                'motivo' => $motivoCierre
            ]);

            return $updated;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al cerrar historia clínica', [
                'historia_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Reabrir historia clínica
     */
    public function reopen(int $id): bool
    {
        try {
            DB::beginTransaction();

            $historia = $this->findById($id);
            if (!$historia) {
                throw new \Exception("Historia clínica no encontrada con ID: {$id}");
            }

            if (!$historia->fecha_cierre) {
                throw new \Exception("La historia clínica no está cerrada");
            }

            $updated = $historia->update([
                'fecha_cierre' => null,
                'motivo_cierre' => null,
            ]);

            // Limpiar cache
            $this->clearHistoriaCache($id);
            $this->clearRelatedCache();

            DB::commit();

            Log::info('Historia clínica reabierta', [
                'historia_id' => $id,
                'numero_historia' => $historia->numero_historia
            ]);

            return $updated;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al reabrir historia clínica', [
                'historia_id' => $id,
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
     * Generar número único de historia clínica
     */
    private function generateHistoryNumber(): string
    {
        $year = now()->year;
        $lastNumber = $this->model->whereYear('fecha_apertura', $year)
            ->orderBy('numero_historia', 'desc')
            ->value('numero_historia');

        if ($lastNumber) {
            // Extraer la secuencia del último número (formato: YYYY######)
            $lastSequence = (int) substr($lastNumber, -6);
            $newSequence = $lastSequence + 1;
        } else {
            $newSequence = 1;
        }

        return $year . str_pad($newSequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Aplicar filtros a la query
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (isset($filters['fecha_apertura_desde'])) {
            $query->where('fecha_apertura', '>=', $filters['fecha_apertura_desde']);
        }

        if (isset($filters['fecha_apertura_hasta'])) {
            $query->where('fecha_apertura', '<=', $filters['fecha_apertura_hasta']);
        }

        if (isset($filters['cerrada'])) {
            if ($filters['cerrada']) {
                $query->whereNotNull('fecha_cierre');
            } else {
                $query->whereNull('fecha_cierre');
            }
        }

        if (isset($filters['con_evoluciones'])) {
            if ($filters['con_evoluciones']) {
                $query->whereHas('evoluciones');
            } else {
                $query->whereDoesntHave('evoluciones');
            }
        }

        if (isset($filters['paciente_id'])) {
            $query->where('paciente_id', $filters['paciente_id']);
        }

        return $query;
    }

    /**
     * Limpiar cache específico de la historia clínica
     */
    private function clearHistoriaCache(int $historiaId): void
    {
        $historia = $this->model->find($historiaId);
        if ($historia) {
            $keys = [
                $this->cachePrefix . "id_{$historiaId}",
                $this->cachePrefix . "number_{$historia->numero_historia}",
                $this->cachePrefix . "paciente_{$historia->paciente_id}",
                $this->cachePrefix . "with_evoluciones_{$historiaId}_20",
            ];

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
        Cache::forget($this->cachePrefix . 'statistics');
        Cache::forget($this->cachePrefix . 'recent_30');
        
        // Limpiar rangos de fechas comunes
        for ($i = 1; $i <= 30; $i++) {
            $date = now()->subDays($i)->format('Y-m-d');
            $today = now()->format('Y-m-d');
            Cache::forget($this->cachePrefix . "date_range_{$date}_{$today}");
        }
    }
}
