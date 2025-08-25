<?php

namespace App\Repositories;

use App\Models\Doctor;
use App\Models\DoctorContract;
use App\Models\Especialidad;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

/**
 * Repository empresarial para el modelo Doctor
 * Maneja operaciones de base de datos optimizadas con cache y auditoría
 * Incluye funcionalidades específicas para el sector médico
 */
class DoctorRepository
{
    protected Doctor $model;
    protected int $cacheMinutes = 30;
    protected string $cachePrefix = 'doctor_';

    public function __construct(Doctor $model)
    {
        $this->model = $model;
    }

    /**
     * Obtener todos los doctores activos con especialidad (con cache)
     */
    public function getAllActive(): Collection
    {
        return Cache::remember(
            $this->cachePrefix . 'all_active',
            $this->cacheMinutes,
            function () {
                return $this->model->with(['especialidad', 'user'])
                    ->where('activo', true)
                    ->orderBy('apellido')
                    ->orderBy('nombre')
                    ->get();
            }
        );
    }

    /**
     * Obtener doctores activos paginados con filtros avanzados
     */
    public function getPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->model->with(['especialidad', 'user', 'contracts'])
            ->where('activo', true);

        // Aplicar filtros
        $query = $this->applyFilters($query, $filters);

        return $query->orderBy('apellido')
                    ->orderBy('nombre')
                    ->paginate($perPage);
    }

    /**
     * Buscar doctores por especialidad con cache
     */
    public function findByEspecialidad(int $especialidadId): Collection
    {
        return Cache::remember(
            $this->cachePrefix . "especialidad_{$especialidadId}",
            $this->cacheMinutes,
            function () use ($especialidadId) {
                return $this->model->with(['especialidad', 'user'])
                    ->where('especialidad_id', $especialidadId)
                    ->where('activo', true)
                    ->orderBy('apellido')
                    ->orderBy('nombre')
                    ->get();
            }
        );
    }

    /**
     * Obtener doctor con contratos activos optimizado
     */
    public function findWithActiveContracts(int $id): ?Doctor
    {
        return Cache::remember(
            $this->cachePrefix . "active_contracts_{$id}",
            15, // 15 minutos para datos de contratos
            function () use ($id) {
                return $this->model->with([
                    'especialidad',
                    'user',
                    'contracts' => function ($query) {
                        $query->where('is_active', true)
                              ->where('start_date', '<=', now())
                              ->where(function ($q) {
                                  $q->whereNull('end_date')
                                    ->orWhere('end_date', '>=', now());
                              })
                              ->orderBy('start_date', 'desc');
                    }
                ])->find($id);
            }
        );
    }

    /**
     * Obtener doctor con horarios y disponibilidad
     */
    public function findWithSchedules(int $id): ?Doctor
    {
        return Cache::remember(
            $this->cachePrefix . "schedules_{$id}",
            60, // 1 hora para horarios
            function () use ($id) {
                return $this->model->with([
                    'especialidad',
                    'user',
                    'scheduleSlots' => function ($query) {
                        $query->where('is_active', true)
                              ->orderBy('day_of_week')
                              ->orderBy('start_time');
                    },
                    'contracts' => function ($query) {
                        $query->where('is_active', true)
                              ->latest('start_date');
                    }
                ])->find($id);
            }
        );
    }

    /**
     * Crear nuevo doctor con validaciones empresariales
     */
    public function create(array $data): Doctor
    {
        try {
            DB::beginTransaction();

            // Validar que no exista otro doctor con la misma matrícula
            if ($this->findByMatricula($data['matricula'])) {
                throw new \Exception("Ya existe un doctor con la matrícula: {$data['matricula']}");
            }

            // Crear el doctor
            $doctor = $this->model->create($data);

            // Limpiar cache relacionado
            $this->clearRelatedCache();

            DB::commit();

            Log::info('Doctor creado exitosamente', [
                'doctor_id' => $doctor->id,
                'matricula' => $doctor->matricula,
                'especialidad_id' => $doctor->especialidad_id
            ]);

            return $doctor;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear doctor', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Actualizar doctor con auditoría
     */
    public function update(int $id, array $data): bool
    {
        try {
            DB::beginTransaction();

            $doctor = $this->findById($id);
            if (!$doctor) {
                throw new \Exception("Doctor no encontrado con ID: {$id}");
            }

            // Validar matrícula única (excluyendo el doctor actual)
            if (isset($data['matricula'])) {
                $existing = $this->model->where('matricula', $data['matricula'])
                                      ->where('id', '!=', $id)
                                      ->first();
                if ($existing) {
                    throw new \Exception("Ya existe otro doctor con la matrícula: {$data['matricula']}");
                }
            }

            $updated = $doctor->update($data);

            // Limpiar cache específico y relacionado
            $this->clearDoctorCache($id);
            $this->clearRelatedCache();

            DB::commit();

            Log::info('Doctor actualizado exitosamente', [
                'doctor_id' => $id,
                'updated_fields' => array_keys($data)
            ]);

            return $updated;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar doctor', [
                'doctor_id' => $id,
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Encontrar doctor por ID con cache
     */
    public function findById(int $id): ?Doctor
    {
        return Cache::remember(
            $this->cachePrefix . "id_{$id}",
            $this->cacheMinutes,
            function () use ($id) {
                return $this->model->with(['especialidad', 'user'])->find($id);
            }
        );
    }

    /**
     * Encontrar doctor por matrícula
     */
    public function findByMatricula(string $matricula): ?Doctor
    {
        return Cache::remember(
            $this->cachePrefix . "matricula_{$matricula}",
            $this->cacheMinutes,
            function () use ($matricula) {
                return $this->model->with(['especialidad', 'user'])
                    ->where('matricula', $matricula)
                    ->first();
            }
        );
    }

    /**
     * Verificar si doctor tiene contratos activos
     */
    public function hasActiveContracts(int $doctorId): bool
    {
        return Cache::remember(
            $this->cachePrefix . "has_contracts_{$doctorId}",
            15,
            function () use ($doctorId) {
                return DoctorContract::where('doctor_id', $doctorId)
                    ->where('is_active', true)
                    ->where('start_date', '<=', now())
                    ->where(function ($query) {
                        $query->whereNull('end_date')
                              ->orWhere('end_date', '>=', now());
                    })
                    ->exists();
            }
        );
    }

    /**
     * Obtener doctores con turnos en un rango de fechas
     */
    public function findWithAppointmentsInRange(Carbon $startDate, Carbon $endDate): Collection
    {
        return $this->model->with([
                'especialidad',
                'user',
                'turnos' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('fecha', [$startDate, $endDate])
                          ->whereIn('estado', [Turno::ESTADO_PROGRAMADO, Turno::ESTADO_CONFIRMADO, Turno::ESTADO_COMPLETADO])
                          ->orderBy('fecha')
                          ->orderBy('hora');
                }
            ])
            ->where('activo', true)
            ->get();
    }

    /**
     * Obtener estadísticas del doctor
     */
    public function getStatistics(int $doctorId, Carbon $startDate = null, Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth();
        $endDate = $endDate ?? now()->endOfMonth();

        $cacheKey = $this->cachePrefix . "stats_{$doctorId}_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}";

        return Cache::remember($cacheKey, 30, function () use ($doctorId, $startDate, $endDate) {
            $turnos = Turno::where('doctor_id', $doctorId)
                ->whereBetween('fecha', [$startDate, $endDate]);

            return [
                'total_turnos' => $turnos->count(),
                'turnos_completados' => $turnos->where('estado', Turno::ESTADO_COMPLETADO)->count(),
                'turnos_cancelados' => $turnos->where('estado', Turno::ESTADO_CANCELADO)->count(),
                'turnos_no_asistio' => $turnos->where('estado', Turno::ESTADO_NO_ASISTIO)->count(),
                'pacientes_unicos' => $turnos->distinct('paciente_id')->count(),
                'tasa_completacion' => $this->calculateCompletionRate($doctorId, $startDate, $endDate),
                'promedio_duracion_turno' => $this->calculateAverageDuration($doctorId, $startDate, $endDate),
            ];
        });
    }

    /**
     * Buscar doctores disponibles en fecha y hora específica
     */
    public function findAvailableForDateTime(Carbon $dateTime, int $durationMinutes = 30): Collection
    {
        $dayOfWeek = $dateTime->dayOfWeek;
        $time = $dateTime->format('H:i:s');
        $endTime = $dateTime->addMinutes($durationMinutes)->format('H:i:s');

        return $this->model->with(['especialidad', 'user'])
            ->where('activo', true)
            ->whereHas('scheduleSlots', function ($query) use ($dayOfWeek, $time, $endTime) {
                $query->where('day_of_week', $dayOfWeek)
                      ->where('is_active', true)
                      ->where('start_time', '<=', $time)
                      ->where('end_time', '>=', $endTime);
            })
            ->whereDoesntHave('turnos', function ($query) use ($dateTime) {
                $query->where('fecha', $dateTime->format('Y-m-d'))
                      ->where('hora', $dateTime->format('H:i:s'))
                      ->whereIn('estado', [Turno::ESTADO_PROGRAMADO, Turno::ESTADO_CONFIRMADO]);
            })
            ->get();
    }

    /**
     * Obtener doctores por múltiples criterios de búsqueda
     */
    public function search(array $criteria): Collection
    {
        $query = $this->model->with(['especialidad', 'user'])
            ->where('activo', true);

        if (!empty($criteria['search'])) {
            $search = $criteria['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('apellido', 'like', "%{$search}%")
                  ->orWhere('matricula', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('email', 'like', "%{$search}%");
                  });
            });
        }

        return $this->applyFilters($query, $criteria)
                   ->orderBy('apellido')
                   ->orderBy('nombre')
                   ->get();
    }

    /**
     * Activar/desactivar doctor
     */
    public function toggleStatus(int $id): bool
    {
        try {
            DB::beginTransaction();

            $doctor = $this->findById($id);
            if (!$doctor) {
                throw new \Exception("Doctor no encontrado con ID: {$id}");
            }

            $newStatus = !$doctor->activo;
            $updated = $doctor->update(['activo' => $newStatus]);

            // Limpiar cache
            $this->clearDoctorCache($id);
            $this->clearRelatedCache();

            DB::commit();

            Log::info('Estado del doctor cambiado', [
                'doctor_id' => $id,
                'new_status' => $newStatus ? 'activo' : 'inactivo'
            ]);

            return $updated;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al cambiar estado del doctor', [
                'doctor_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Eliminar doctor (soft delete) con validaciones
     */
    public function delete(int $id): bool
    {
        try {
            DB::beginTransaction();

            $doctor = $this->findById($id);
            if (!$doctor) {
                throw new \Exception("Doctor no encontrado con ID: {$id}");
            }

            // Verificar que no tenga turnos futuros
            $futureTurnos = Turno::where('doctor_id', $id)
                ->where('fecha', '>=', now()->format('Y-m-d'))
                ->whereIn('estado', [Turno::ESTADO_PROGRAMADO, Turno::ESTADO_CONFIRMADO])
                ->exists();

            if ($futureTurnos) {
                throw new \Exception("No se puede eliminar el doctor porque tiene turnos programados");
            }

            $deleted = $doctor->delete();

            // Limpiar cache
            $this->clearDoctorCache($id);
            $this->clearRelatedCache();

            DB::commit();

            Log::warning('Doctor eliminado', [
                'doctor_id' => $id,
                'matricula' => $doctor->matricula
            ]);

            return $deleted;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar doctor', [
                'doctor_id' => $id,
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
        if (isset($filters['especialidad_id'])) {
            $query->where('especialidad_id', $filters['especialidad_id']);
        }

        if (isset($filters['activo'])) {
            $query->where('activo', $filters['activo']);
        }

        if (isset($filters['has_contracts'])) {
            if ($filters['has_contracts']) {
                $query->whereHas('contracts', function ($q) {
                    $q->where('is_active', true)
                      ->where('start_date', '<=', now())
                      ->where(function ($query) {
                          $query->whereNull('end_date')
                                ->orWhere('end_date', '>=', now());
                      });
                });
            } else {
                $query->whereDoesntHave('contracts', function ($q) {
                    $q->where('is_active', true)
                      ->where('start_date', '<=', now())
                      ->where(function ($query) {
                          $query->whereNull('end_date')
                                ->orWhere('end_date', '>=', now());
                      });
                });
            }
        }

        return $query;
    }

    /**
     * Calcular tasa de completación de turnos
     */
    private function calculateCompletionRate(int $doctorId, Carbon $startDate, Carbon $endDate): float
    {
        $total = Turno::where('doctor_id', $doctorId)
            ->whereBetween('fecha', [$startDate, $endDate])
            ->count();

        if ($total === 0) {
            return 0;
        }

        $completados = Turno::where('doctor_id', $doctorId)
            ->whereBetween('fecha', [$startDate, $endDate])
            ->where('estado', Turno::ESTADO_COMPLETADO)
            ->count();

        return ($completados / $total) * 100;
    }

    /**
     * Calcular duración promedio de turnos
     */
    private function calculateAverageDuration(int $doctorId, Carbon $startDate, Carbon $endDate): float
    {
        return Turno::where('doctor_id', $doctorId)
            ->whereBetween('fecha', [$startDate, $endDate])
            ->where('estado', Turno::ESTADO_COMPLETADO)
            ->avg('duracion_minutos') ?? 0;
    }

    /**
     * Limpiar cache específico del doctor
     */
    private function clearDoctorCache(int $doctorId): void
    {
        $keys = [
            $this->cachePrefix . "id_{$doctorId}",
            $this->cachePrefix . "active_contracts_{$doctorId}",
            $this->cachePrefix . "schedules_{$doctorId}",
            $this->cachePrefix . "has_contracts_{$doctorId}",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Limpiar cache relacionado
     */
    private function clearRelatedCache(): void
    {
        Cache::forget($this->cachePrefix . 'all_active');
        
        // Limpiar cache de especialidades
        $especialidades = Especialidad::pluck('id');
        foreach ($especialidades as $especialidadId) {
            Cache::forget($this->cachePrefix . "especialidad_{$especialidadId}");
        }
    }
}
