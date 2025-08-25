<?php

namespace App\Repositories;

use App\Models\Paciente;
use App\Models\Turno;
use App\Models\HistoriaClinica;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

/**
 * Repository empresarial para el modelo Paciente
 * Maneja operaciones optimizadas con cache, validaciones médicas y auditoría
 * Incluye funcionalidades específicas para gestión de pacientes médicos
 */
class PacienteRepository
{
    protected Paciente $model;
    protected int $cacheMinutes = 30;
    protected string $cachePrefix = 'paciente_';

    public function __construct(Paciente $model)
    {
        $this->model = $model;
    }

    /**
     * Crear un nuevo paciente con validaciones empresariales
     */
    public function create(array $data): Paciente
    {
        try {
            DB::beginTransaction();

            // Validar unicidad de DNI y email
            if ($this->existsWithEmailOrDni($data['email'], $data['dni'])) {
                throw new \Exception("Ya existe un paciente con este email o DNI");
            }

            // Validar edad si es menor
            if (isset($data['fecha_nacimiento'])) {
                $age = Carbon::parse($data['fecha_nacimiento'])->age;
                if ($age < 18 && empty($data['tutor_responsable'])) {
                    throw new \Exception("Los pacientes menores de 18 años requieren un tutor responsable");
                }
            }

            $paciente = $this->model->create($data);

            // Crear historia clínica automáticamente
            $this->createMedicalHistory($paciente);

            // Limpiar cache relacionado
            $this->clearRelatedCache();

            DB::commit();

            Log::info('Paciente creado exitosamente', [
                'paciente_id' => $paciente->id,
                'dni' => $paciente->dni,
                'edad' => isset($data['fecha_nacimiento']) ? Carbon::parse($data['fecha_nacimiento'])->age : null
            ]);

            return $paciente;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear paciente', [
                'error' => $e->getMessage(),
                'data' => array_except($data, ['password'])
            ]);
            throw $e;
        }
    }

    /**
     * Buscar paciente por ID con cache
     */
    public function findById(int $id): ?Paciente
    {
        return Cache::remember(
            $this->cachePrefix . "id_{$id}",
            $this->cacheMinutes,
            function () use ($id) {
                return $this->model->with(['user', 'obraSocial'])->find($id);
            }
        );
    }

    /**
     * Buscar paciente por email con cache
     */
    public function findByEmail(string $email): ?Paciente
    {
        return Cache::remember(
            $this->cachePrefix . "email_{$email}",
            $this->cacheMinutes,
            function () use ($email) {
                return $this->model->with(['user', 'obraSocial'])
                    ->where('email', $email)
                    ->first();
            }
        );
    }

    /**
     * Buscar paciente por DNI con cache
     */
    public function findByDni(string $dni): ?Paciente
    {
        return Cache::remember(
            $this->cachePrefix . "dni_{$dni}",
            $this->cacheMinutes,
            function () use ($dni) {
                return $this->model->with(['user', 'obraSocial'])
                    ->where('dni', $dni)
                    ->first();
            }
        );
    }

    /**
     * Actualizar un paciente con auditoría
     */
    public function update(int $id, array $data): bool
    {
        try {
            DB::beginTransaction();

            $paciente = $this->findById($id);
            if (!$paciente) {
                throw new \Exception("Paciente no encontrado con ID: {$id}");
            }

            // Validar unicidad de DNI y email (excluyendo el paciente actual)
            if (isset($data['email']) || isset($data['dni'])) {
                $email = $data['email'] ?? $paciente->email;
                $dni = $data['dni'] ?? $paciente->dni;
                
                if ($this->existsWithEmailOrDni($email, $dni, $id)) {
                    throw new \Exception("Ya existe otro paciente con este email o DNI");
                }
            }

            // Validar cambio de edad para menores
            if (isset($data['fecha_nacimiento'])) {
                $newAge = Carbon::parse($data['fecha_nacimiento'])->age;
                $currentAge = Carbon::parse($paciente->fecha_nacimiento)->age;
                
                if ($newAge < 18 && $currentAge >= 18 && empty($data['tutor_responsable'])) {
                    throw new \Exception("Al cambiar la edad a menor de 18 años se requiere un tutor responsable");
                }
            }

            $updated = $paciente->update($data);

            // Limpiar cache específico
            $this->clearPacienteCache($id);
            $this->clearRelatedCache();

            DB::commit();

            Log::info('Paciente actualizado exitosamente', [
                'paciente_id' => $id,
                'updated_fields' => array_keys($data)
            ]);

            return $updated;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar paciente', [
                'paciente_id' => $id,
                'error' => $e->getMessage(),
                'data' => array_except($data, ['password'])
            ]);
            throw $e;
        }
    }

    /**
     * Eliminar un paciente (soft delete) con validaciones
     */
    public function delete(int $id): bool
    {
        try {
            DB::beginTransaction();

            $paciente = $this->findById($id);
            if (!$paciente) {
                throw new \Exception("Paciente no encontrado con ID: {$id}");
            }

            // Verificar que no tenga turnos futuros
            $futureTurnos = Turno::where('paciente_id', $id)
                ->where('fecha', '>=', now()->format('Y-m-d'))
                ->whereIn('estado', [Turno::ESTADO_PROGRAMADO, Turno::ESTADO_CONFIRMADO])
                ->exists();

            if ($futureTurnos) {
                throw new \Exception("No se puede eliminar el paciente porque tiene turnos programados");
            }

            $deleted = $paciente->delete();

            // Limpiar cache
            $this->clearPacienteCache($id);
            $this->clearRelatedCache();

            DB::commit();

            Log::warning('Paciente eliminado', [
                'paciente_id' => $id,
                'dni' => $paciente->dni
            ]);

            return $deleted;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar paciente', [
                'paciente_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtener todos los pacientes paginados con filtros
     */
    public function getPaginated(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->model->with(['user', 'obraSocial']);

        // Aplicar filtros
        $query = $this->applyFilters($query, $filters);

        return $query->orderBy('apellido')
                    ->orderBy('nombre')
                    ->paginate($perPage);
    }

    /**
     * Buscar pacientes activos
     */
    public function findActive(): Collection
    {
        return Cache::remember(
            $this->cachePrefix . 'all_active',
            $this->cacheMinutes,
            function () {
                return $this->model->with(['user', 'obraSocial'])
                    ->where('activo', true)
                    ->orderBy('apellido')
                    ->orderBy('nombre')
                    ->get();
            }
        );
    }

    /**
     * Buscar pacientes por múltiples criterios avanzados
     */
    public function search(array $criteria): Collection
    {
        $query = $this->model->with(['user', 'obraSocial']);

        if (!empty($criteria['search'])) {
            $search = $criteria['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('apellido', 'like', "%{$search}%")
                  ->orWhere('dni', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('telefono', 'like', "%{$search}%")
                  ->orWhere('numero_obra_social', 'like', "%{$search}%");
            });
        }

        return $this->applyFilters($query, $criteria)
                   ->orderBy('apellido')
                   ->orderBy('nombre')
                   ->get();
    }

    /**
     * Obtener pacientes con sus turnos
     */
    public function findWithTurnos(int $id, int $limit = 10): ?Paciente
    {
        return Cache::remember(
            $this->cachePrefix . "with_turnos_{$id}_{$limit}",
            15, // 15 minutos para datos de turnos
            function () use ($id, $limit) {
                return $this->model->with([
                    'user',
                    'obraSocial',
                    'turnos' => function ($query) use ($limit) {
                        $query->with(['doctor.especialidad'])
                              ->orderBy('fecha', 'desc')
                              ->orderBy('hora', 'desc')
                              ->limit($limit);
                    }
                ])->find($id);
            }
        );
    }

    /**
     * Obtener pacientes con historial clínico completo
     */
    public function findWithHistoriaClinica(int $id): ?Paciente
    {
        return Cache::remember(
            $this->cachePrefix . "with_historia_{$id}",
            60, // 1 hora para historia clínica
            function () use ($id) {
                return $this->model->with([
                    'user',
                    'obraSocial',
                    'historiaClinica.evoluciones' => function ($query) {
                        $query->with(['doctor.especialidad'])
                              ->orderBy('fecha', 'desc')
                              ->orderBy('created_at', 'desc');
                    }
                ])->find($id);
            }
        );
    }

    /**
     * Contar pacientes por diferentes estados y criterios
     */
    public function getStatistics(): array
    {
        return Cache::remember(
            $this->cachePrefix . 'statistics',
            60, // 1 hora
            function () {
                $total = $this->model->count();
                $activos = $this->model->where('activo', true)->count();
                $menores = $this->model->whereRaw('DATEDIFF(NOW(), fecha_nacimiento) / 365 < 18')->count();
                $conObraSocial = $this->model->whereNotNull('obra_social_id')->count();
                
                // Estadísticas por rango de edad
                $rangosEdad = [
                    '0-17' => $this->model->whereRaw('DATEDIFF(NOW(), fecha_nacimiento) / 365 < 18')->count(),
                    '18-30' => $this->model->whereRaw('DATEDIFF(NOW(), fecha_nacimiento) / 365 BETWEEN 18 AND 30')->count(),
                    '31-50' => $this->model->whereRaw('DATEDIFF(NOW(), fecha_nacimiento) / 365 BETWEEN 31 AND 50')->count(),
                    '51-70' => $this->model->whereRaw('DATEDIFF(NOW(), fecha_nacimiento) / 365 BETWEEN 51 AND 70')->count(),
                    '70+' => $this->model->whereRaw('DATEDIFF(NOW(), fecha_nacimiento) / 365 > 70')->count(),
                ];

                return [
                    'total' => $total,
                    'activos' => $activos,
                    'inactivos' => $total - $activos,
                    'menores_edad' => $menores,
                    'con_obra_social' => $conObraSocial,
                    'sin_obra_social' => $total - $conObraSocial,
                    'rangos_edad' => $rangosEdad,
                    'tasa_actividad' => $total > 0 ? ($activos / $total) * 100 : 0,
                ];
            }
        );
    }

    /**
     * Obtener pacientes recientes (últimos N días)
     */
    public function getRecent(int $days = 30): Collection
    {
        return Cache::remember(
            $this->cachePrefix . "recent_{$days}",
            30,
            function () use ($days) {
                return $this->model->with(['user', 'obraSocial'])
                    ->where('created_at', '>=', now()->subDays($days))
                    ->orderBy('created_at', 'desc')
                    ->get();
            }
        );
    }

    /**
     * Verificar si existe un paciente con email o DNI
     */
    public function existsWithEmailOrDni(string $email, string $dni, int $excludeId = null): bool
    {
        $query = $this->model->where(function ($q) use ($email, $dni) {
            $q->where('email', $email)->orWhere('dni', $dni);
        });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Obtener pacientes con próximos cumpleaños
     */
    public function getUpcomingBirthdays(int $days = 7): Collection
    {
        return Cache::remember(
            $this->cachePrefix . "birthdays_{$days}",
            720, // 12 horas
            function () use ($days) {
                $today = now();
                $endDate = now()->addDays($days);

                return $this->model->with(['user', 'obraSocial'])
                    ->where('activo', true)
                    ->whereRaw("
                        DATE_ADD(
                            DATE(CONCAT(YEAR(?), '-', MONTH(fecha_nacimiento), '-', DAY(fecha_nacimiento))),
                            INTERVAL IF(
                                DATE(CONCAT(YEAR(?), '-', MONTH(fecha_nacimiento), '-', DAY(fecha_nacimiento))) < ?,
                                1,
                                0
                            ) YEAR
                        ) BETWEEN ? AND ?
                    ", [$today, $today, $today, $today, $endDate])
                    ->orderByRaw("
                        DATE_ADD(
                            DATE(CONCAT(YEAR(?), '-', MONTH(fecha_nacimiento), '-', DAY(fecha_nacimiento))),
                            INTERVAL IF(
                                DATE(CONCAT(YEAR(?), '-', MONTH(fecha_nacimiento), '-', DAY(fecha_nacimiento))) < ?,
                                1,
                                0
                            ) YEAR
                        )
                    ", [$today, $today, $today])
                    ->get();
            }
        );
    }

    /**
     * Obtener pacientes frecuentes (con más turnos)
     */
    public function getFrequentPatients(int $limit = 10, Carbon $startDate = null): Collection
    {
        $startDate = $startDate ?? now()->subYear();
        $cacheKey = $this->cachePrefix . "frequent_{$limit}_{$startDate->format('Y-m-d')}";

        return Cache::remember($cacheKey, 120, function () use ($limit, $startDate) {
            return $this->model->with(['user', 'obraSocial'])
                ->withCount(['turnos' => function ($query) use ($startDate) {
                    $query->where('fecha', '>=', $startDate)
                          ->whereIn('estado', [Turno::ESTADO_COMPLETADO, Turno::ESTADO_CONFIRMADO]);
                }])
                ->having('turnos_count', '>', 0)
                ->orderBy('turnos_count', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Activar/desactivar paciente
     */
    public function toggleStatus(int $id): bool
    {
        try {
            DB::beginTransaction();

            $paciente = $this->findById($id);
            if (!$paciente) {
                throw new \Exception("Paciente no encontrado con ID: {$id}");
            }

            $newStatus = !$paciente->activo;
            $updated = $paciente->update(['activo' => $newStatus]);

            // Limpiar cache
            $this->clearPacienteCache($id);
            $this->clearRelatedCache();

            DB::commit();

            Log::info('Estado del paciente cambiado', [
                'paciente_id' => $id,
                'new_status' => $newStatus ? 'activo' : 'inactivo'
            ]);

            return $updated;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al cambiar estado del paciente', [
                'paciente_id' => $id,
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
     * Crear historia clínica automáticamente
     */
    private function createMedicalHistory(Paciente $paciente): void
    {
        HistoriaClinica::create([
            'paciente_id' => $paciente->id,
            'numero_historia' => $this->generateMedicalHistoryNumber(),
            'fecha_apertura' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Generar número de historia clínica único
     */
    private function generateMedicalHistoryNumber(): string
    {
        $year = now()->year;
        $lastNumber = HistoriaClinica::whereYear('created_at', $year)
            ->orderBy('numero_historia', 'desc')
            ->value('numero_historia');

        if ($lastNumber) {
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
        if (isset($filters['activo'])) {
            $query->where('activo', $filters['activo']);
        }

        if (isset($filters['obra_social_id'])) {
            $query->where('obra_social_id', $filters['obra_social_id']);
        }

        if (isset($filters['edad_min'])) {
            $query->whereRaw('DATEDIFF(NOW(), fecha_nacimiento) / 365 >= ?', [$filters['edad_min']]);
        }

        if (isset($filters['edad_max'])) {
            $query->whereRaw('DATEDIFF(NOW(), fecha_nacimiento) / 365 <= ?', [$filters['edad_max']]);
        }

        if (isset($filters['fecha_nacimiento_desde'])) {
            $query->where('fecha_nacimiento', '>=', $filters['fecha_nacimiento_desde']);
        }

        if (isset($filters['fecha_nacimiento_hasta'])) {
            $query->where('fecha_nacimiento', '<=', $filters['fecha_nacimiento_hasta']);
        }

        if (isset($filters['genero'])) {
            $query->where('genero', $filters['genero']);
        }

        if (isset($filters['ciudad'])) {
            $query->where('ciudad', 'like', "%{$filters['ciudad']}%");
        }

        return $query;
    }

    /**
     * Limpiar cache específico del paciente
     */
    private function clearPacienteCache(int $pacienteId): void
    {
        $paciente = $this->model->find($pacienteId);
        if ($paciente) {
            $keys = [
                $this->cachePrefix . "id_{$pacienteId}",
                $this->cachePrefix . "email_{$paciente->email}",
                $this->cachePrefix . "dni_{$paciente->dni}",
                $this->cachePrefix . "with_turnos_{$pacienteId}_10",
                $this->cachePrefix . "with_historia_{$pacienteId}",
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
        Cache::forget($this->cachePrefix . 'all_active');
        Cache::forget($this->cachePrefix . 'statistics');
        Cache::forget($this->cachePrefix . 'recent_30');
        Cache::forget($this->cachePrefix . 'birthdays_7');
    }
}
