<?php

namespace App\Repositories;

use App\Models\LogAuditoria;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

/**
 * Repository empresarial para el modelo LogAuditoria
 * Maneja operaciones optimizadas con cache y análisis de auditoría
 * Incluye funcionalidades específicas para gestión de logs del sistema médico
 */
class LogAuditoriaRepository
{
    protected LogAuditoria $model;
    protected int $cacheMinutes = 60;
    protected string $cachePrefix = 'log_auditoria_';

    public function __construct(LogAuditoria $model)
    {
        $this->model = $model;
    }

    /**
     * Crear un nuevo log de auditoría
     */
    public function create(array $data): LogAuditoria
    {
        try {
            // Asegurar que tenga timestamp
            $data['fecha_hora'] = $data['fecha_hora'] ?? now();
            
            // Serializar datos si es array
            if (isset($data['datos']) && is_array($data['datos'])) {
                $data['datos'] = json_encode($data['datos']);
            }

            $log = $this->model->create($data);

            // Solo limpiar cache de estadísticas para evitar overhead
            $this->clearStatisticsCache();

            return $log;

        } catch (\Exception $e) {
            Log::error('Error al crear log de auditoría', [
                'error' => $e->getMessage(),
                'data' => array_except($data, ['datos'])
            ]);
            throw $e;
        }
    }

    /**
     * Buscar log por ID con cache
     */
    public function findById(int $id): ?LogAuditoria
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
     * Obtener logs paginados con filtros
     */
    public function getPaginated(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = $this->model->newQuery();

        // Aplicar filtros
        $query = $this->applyFilters($query, $filters);

        return $query->orderBy('fecha_hora', 'desc')
                    ->paginate($perPage);
    }

    /**
     * Buscar logs por usuario
     */
    public function findByUser(int $userId, int $limit = 100): Collection
    {
        return Cache::remember(
            $this->cachePrefix . "user_{$userId}_limit_{$limit}",
            30, // Cache más corto para logs
            function () use ($userId, $limit) {
                return $this->model->where('user_id', $userId)
                    ->orderBy('fecha_hora', 'desc')
                    ->limit($limit)
                    ->get();
            }
        );
    }

    /**
     * Buscar logs por acción
     */
    public function findByAction(string $accion, int $limit = 100): Collection
    {
        return Cache::remember(
            $this->cachePrefix . "action_" . md5($accion) . "_limit_{$limit}",
            30,
            function () use ($accion, $limit) {
                return $this->model->where('accion', $accion)
                    ->orderBy('fecha_hora', 'desc')
                    ->limit($limit)
                    ->get();
            }
        );
    }

    /**
     * Buscar logs por entidad
     */
    public function findByEntity(string $entidad, int $entidadId = null, int $limit = 100): Collection
    {
        $cacheKey = $this->cachePrefix . "entity_" . md5($entidad) . 
                   ($entidadId ? "_{$entidadId}" : '') . "_limit_{$limit}";

        return Cache::remember(
            $cacheKey,
            30,
            function () use ($entidad, $entidadId, $limit) {
                $query = $this->model->where('entidad', $entidad);
                
                if ($entidadId) {
                    $query->where('entidad_id', $entidadId);
                }
                
                return $query->orderBy('fecha_hora', 'desc')
                            ->limit($limit)
                            ->get();
            }
        );
    }

    /**
     * Buscar logs recientes (últimas N horas)
     */
    public function getRecent(int $hours = 24): Collection
    {
        return Cache::remember(
            $this->cachePrefix . "recent_{$hours}",
            10, // Cache muy corto para datos recientes
            function () use ($hours) {
                return $this->model->where('fecha_hora', '>=', now()->subHours($hours))
                    ->orderBy('fecha_hora', 'desc')
                    ->get();
            }
        );
    }

    /**
     * Buscar logs por rango de fechas
     */
    public function findByDateRange(Carbon $startDate, Carbon $endDate): Collection
    {
        return Cache::remember(
            $this->cachePrefix . "date_range_{$startDate->format('Y-m-d-H')}_{$endDate->format('Y-m-d-H')}",
            60,
            function () use ($startDate, $endDate) {
                return $this->model->whereBetween('fecha_hora', [$startDate, $endDate])
                    ->orderBy('fecha_hora', 'desc')
                    ->get();
            }
        );
    }

    /**
     * Buscar logs por múltiples criterios
     */
    public function search(array $criteria): Collection
    {
        $query = $this->model->newQuery();

        if (!empty($criteria['search'])) {
            $search = $criteria['search'];
            $query->where(function ($q) use ($search) {
                $q->where('accion', 'like', "%{$search}%")
                  ->orWhere('entidad', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%")
                  ->orWhere('ip', 'like', "%{$search}%")
                  ->orWhere('user_agent', 'like', "%{$search}%");
            });
        }

        return $this->applyFilters($query, $criteria)
                   ->orderBy('fecha_hora', 'desc')
                   ->get();
    }

    /**
     * Obtener estadísticas de auditoría
     */
    public function getStatistics(Carbon $startDate = null, Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();
        
        $cacheKey = $this->cachePrefix . "stats_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}";

        return Cache::remember($cacheKey, 120, function () use ($startDate, $endDate) {
            $baseQuery = $this->model->whereBetween('fecha_hora', [$startDate, $endDate]);

            $total = $baseQuery->count();
            $hoy = $this->model->whereDate('fecha_hora', now())->count();
            $esta_semana = $this->model->whereBetween('fecha_hora', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count();

            // Estadísticas por acción
            $porAccion = $this->model->select('accion', DB::raw('COUNT(*) as total'))
                ->whereBetween('fecha_hora', [$startDate, $endDate])
                ->groupBy('accion')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get()
                ->pluck('total', 'accion')
                ->toArray();

            // Estadísticas por entidad
            $porEntidad = $this->model->select('entidad', DB::raw('COUNT(*) as total'))
                ->whereBetween('fecha_hora', [$startDate, $endDate])
                ->groupBy('entidad')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get()
                ->pluck('total', 'entidad')
                ->toArray();

            // Estadísticas por usuario (top 10)
            $porUsuario = $this->model->select('user_id', DB::raw('COUNT(*) as total'))
                ->whereBetween('fecha_hora', [$startDate, $endDate])
                ->whereNotNull('user_id')
                ->groupBy('user_id')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get()
                ->pluck('total', 'user_id')
                ->toArray();

            // Actividad por hora del día
            $porHora = $this->model->select(DB::raw('HOUR(fecha_hora) as hora, COUNT(*) as total'))
                ->whereBetween('fecha_hora', [$startDate, $endDate])
                ->groupBy('hora')
                ->orderBy('hora')
                ->get()
                ->pluck('total', 'hora')
                ->toArray();

            // IPs más activas
            $topIps = $this->model->select('ip', DB::raw('COUNT(*) as total'))
                ->whereBetween('fecha_hora', [$startDate, $endDate])
                ->whereNotNull('ip')
                ->groupBy('ip')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get()
                ->pluck('total', 'ip')
                ->toArray();

            // Promedio de eventos por día
            $days = $startDate->diffInDays($endDate) + 1;
            $promedioPorDia = $days > 0 ? $total / $days : 0;

            return [
                'total' => $total,
                'hoy' => $hoy,
                'esta_semana' => $esta_semana,
                'promedio_por_dia' => round($promedioPorDia, 2),
                'por_accion' => $porAccion,
                'por_entidad' => $porEntidad,
                'por_usuario' => $porUsuario,
                'por_hora' => $porHora,
                'top_ips' => $topIps,
            ];
        });
    }

    /**
     * Obtener actividad reciente por usuario
     */
    public function getUserActivity(int $userId, int $days = 7): array
    {
        return Cache::remember(
            $this->cachePrefix . "user_activity_{$userId}_{$days}",
            30,
            function () use ($userId, $days) {
                $startDate = now()->subDays($days);
                
                $logs = $this->model->where('user_id', $userId)
                    ->where('fecha_hora', '>=', $startDate)
                    ->orderBy('fecha_hora', 'desc')
                    ->get();

                $activityByDay = [];
                $actionsSummary = [];

                foreach ($logs as $log) {
                    $day = $log->fecha_hora->format('Y-m-d');
                    
                    if (!isset($activityByDay[$day])) {
                        $activityByDay[$day] = 0;
                    }
                    $activityByDay[$day]++;

                    if (!isset($actionsSummary[$log->accion])) {
                        $actionsSummary[$log->accion] = 0;
                    }
                    $actionsSummary[$log->accion]++;
                }

                return [
                    'total_eventos' => $logs->count(),
                    'por_dia' => $activityByDay,
                    'resumen_acciones' => $actionsSummary,
                    'primera_actividad' => $logs->last()?->fecha_hora,
                    'ultima_actividad' => $logs->first()?->fecha_hora,
                ];
            }
        );
    }

    /**
     * Detectar actividad sospechosa
     */
    public function getSuspiciousActivity(int $hours = 24): Collection
    {
        return Cache::remember(
            $this->cachePrefix . "suspicious_{$hours}",
            15, // Cache corto para seguridad
            function () use ($hours) {
                $startTime = now()->subHours($hours);
                
                // Detectar múltiples intentos de login fallidos
                $suspiciousIps = $this->model->select('ip', DB::raw('COUNT(*) as attempts'))
                    ->where('accion', 'login_failed')
                    ->where('fecha_hora', '>=', $startTime)
                    ->groupBy('ip')
                    ->having('attempts', '>=', 5)
                    ->pluck('ip')
                    ->toArray();

                // Detectar usuarios con actividad inusual
                $suspiciousUsers = $this->model->select('user_id', DB::raw('COUNT(*) as actions'))
                    ->where('fecha_hora', '>=', $startTime)
                    ->whereNotNull('user_id')
                    ->groupBy('user_id')
                    ->having('actions', '>=', 100) // Más de 100 acciones en 24h
                    ->pluck('user_id')
                    ->toArray();

                return $this->model->where('fecha_hora', '>=', $startTime)
                    ->where(function ($query) use ($suspiciousIps, $suspiciousUsers) {
                        $query->whereIn('ip', $suspiciousIps)
                              ->orWhereIn('user_id', $suspiciousUsers)
                              ->orWhere('accion', 'like', '%failed%')
                              ->orWhere('accion', 'like', '%error%')
                              ->orWhere('accion', 'like', '%unauthorized%');
                    })
                    ->orderBy('fecha_hora', 'desc')
                    ->get();
            }
        );
    }

    /**
     * Limpiar logs antiguos
     */
    public function cleanOldLogs(int $daysToKeep = 365): int
    {
        try {
            $cutoffDate = now()->subDays($daysToKeep);
            
            $deleted = $this->model->where('fecha_hora', '<', $cutoffDate)->delete();

            // Limpiar todo el cache después de la limpieza
            $this->clearAllCache();

            Log::info('Logs antiguos limpiados', [
                'cutoff_date' => $cutoffDate,
                'deleted_count' => $deleted
            ]);

            return $deleted;

        } catch (\Exception $e) {
            Log::error('Error al limpiar logs antiguos', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtener tendencias de actividad
     */
    public function getActivityTrends(int $days = 30): array
    {
        return Cache::remember(
            $this->cachePrefix . "trends_{$days}",
            120,
            function () use ($days) {
                $startDate = now()->subDays($days);
                
                $dailyActivity = $this->model->select(
                        DB::raw('DATE(fecha_hora) as fecha'),
                        DB::raw('COUNT(*) as total')
                    )
                    ->where('fecha_hora', '>=', $startDate)
                    ->groupBy('fecha')
                    ->orderBy('fecha')
                    ->get()
                    ->pluck('total', 'fecha')
                    ->toArray();

                $hourlyActivity = $this->model->select(
                        DB::raw('HOUR(fecha_hora) as hora'),
                        DB::raw('COUNT(*) as total')
                    )
                    ->where('fecha_hora', '>=', $startDate)
                    ->groupBy('hora')
                    ->orderBy('hora')
                    ->get()
                    ->pluck('total', 'hora')
                    ->toArray();

                return [
                    'actividad_diaria' => $dailyActivity,
                    'actividad_por_hora' => $hourlyActivity,
                ];
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
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['accion'])) {
            if (is_array($filters['accion'])) {
                $query->whereIn('accion', $filters['accion']);
            } else {
                $query->where('accion', $filters['accion']);
            }
        }

        if (isset($filters['entidad'])) {
            $query->where('entidad', $filters['entidad']);
        }

        if (isset($filters['entidad_id'])) {
            $query->where('entidad_id', $filters['entidad_id']);
        }

        if (isset($filters['ip'])) {
            $query->where('ip', $filters['ip']);
        }

        if (isset($filters['fecha_desde'])) {
            $query->where('fecha_hora', '>=', $filters['fecha_desde']);
        }

        if (isset($filters['fecha_hasta'])) {
            $query->where('fecha_hora', '<=', $filters['fecha_hasta']);
        }

        if (isset($filters['nivel'])) {
            $query->where('nivel', $filters['nivel']);
        }

        if (isset($filters['acciones_criticas'])) {
            if ($filters['acciones_criticas']) {
                $query->where(function ($q) {
                    $q->where('accion', 'like', '%delete%')
                      ->orWhere('accion', 'like', '%destroy%')
                      ->orWhere('accion', 'like', '%failed%')
                      ->orWhere('accion', 'like', '%error%')
                      ->orWhere('nivel', 'critical');
                });
            }
        }

        return $query;
    }

    /**
     * Limpiar cache específico de estadísticas
     */
    private function clearStatisticsCache(): void
    {
        // Solo limpiar estadísticas para evitar overhead en logs frecuentes
        $patterns = [
            $this->cachePrefix . 'stats_',
            $this->cachePrefix . 'trends_',
            $this->cachePrefix . 'suspicious_',
            $this->cachePrefix . 'recent_'
        ];

        // Limpiar algunos keys importantes
        Cache::forget($this->cachePrefix . 'recent_24');
        
        // Limpiar estadísticas del día actual
        $today = now()->format('Y-m-d');
        Cache::forget($this->cachePrefix . "stats_{$today}_{$today}");
    }

    /**
     * Limpiar todo el cache (usar solo en limpieza masiva)
     */
    private function clearAllCache(): void
    {
        // En un entorno real, implementar limpieza por patterns
        // Por ahora, limpiar keys comunes manualmente
        $commonKeys = [
            'recent_24', 'recent_12', 'recent_6',
            'suspicious_24', 'suspicious_12',
            'trends_30', 'trends_7'
        ];

        foreach ($commonKeys as $key) {
            Cache::forget($this->cachePrefix . $key);
        }

        // Limpiar estadísticas de los últimos 30 días
        for ($i = 0; $i < 30; $i++) {
            $date = now()->subDays($i)->format('Y-m-d');
            Cache::forget($this->cachePrefix . "stats_{$date}_{$date}");
        }
    }
}
