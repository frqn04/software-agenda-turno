<?php

namespace App\Repositories;

use App\Models\DoctorContract;
use App\Models\Doctor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

/**
 * Repository empresarial para el modelo DoctorContract
 * Maneja operaciones optimizadas con cache, validaciones contractuales y auditoría
 * Incluye funcionalidades específicas para gestión de contratos médicos
 */
class DoctorContractRepository
{
    protected DoctorContract $model;
    protected int $cacheMinutes = 60;
    protected string $cachePrefix = 'doctor_contract_';

    public function __construct(DoctorContract $model)
    {
        $this->model = $model;
    }

    /**
     * Crear un nuevo contrato con validaciones empresariales
     */
    public function create(array $data): DoctorContract
    {
        try {
            DB::beginTransaction();

            // Validar que el doctor existe y está activo
            $doctor = Doctor::find($data['doctor_id']);
            if (!$doctor || !$doctor->activo) {
                throw new \Exception("Doctor no válido o inactivo");
            }

            // Validar que no exista solapamiento de fechas con contratos activos
            if ($this->hasDateOverlap($data['doctor_id'], $data['fecha_inicio'], $data['fecha_fin'] ?? null)) {
                throw new \Exception("Existe un solapamiento de fechas con otro contrato activo");
            }

            // Validar fechas
            if (isset($data['fecha_fin']) && $data['fecha_fin'] <= $data['fecha_inicio']) {
                throw new \Exception("La fecha de fin debe ser posterior a la fecha de inicio");
            }

            // Establecer estado activo por defecto
            $data['activo'] = $data['activo'] ?? true;

            // Generar número de contrato único si no se proporciona
            if (!isset($data['numero_contrato'])) {
                $data['numero_contrato'] = $this->generateContractNumber();
            }

            $contract = $this->model->create($data);

            // Limpiar cache relacionado
            $this->clearRelatedCache($data['doctor_id']);

            DB::commit();

            Log::info('Contrato creado exitosamente', [
                'contract_id' => $contract->id,
                'numero_contrato' => $contract->numero_contrato,
                'doctor_id' => $data['doctor_id'],
                'fecha_inicio' => $data['fecha_inicio']
            ]);

            return $contract;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear contrato', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Buscar contrato por ID con cache
     */
    public function findById(int $id): ?DoctorContract
    {
        return Cache::remember(
            $this->cachePrefix . "id_{$id}",
            $this->cacheMinutes,
            function () use ($id) {
                return $this->model->with(['doctor.especialidad'])->find($id);
            }
        );
    }

    /**
     * Buscar contrato por número con cache
     */
    public function findByNumber(string $numeroContrato): ?DoctorContract
    {
        return Cache::remember(
            $this->cachePrefix . "number_{$numeroContrato}",
            $this->cacheMinutes,
            function () use ($numeroContrato) {
                return $this->model->with(['doctor.especialidad'])
                    ->where('numero_contrato', $numeroContrato)
                    ->first();
            }
        );
    }

    /**
     * Actualizar un contrato con validaciones
     */
    public function update(int $id, array $data): bool
    {
        try {
            DB::beginTransaction();

            $contract = $this->findById($id);
            if (!$contract) {
                throw new \Exception("Contrato no encontrado con ID: {$id}");
            }

            // Validar solapamiento de fechas si se cambian las fechas
            if (isset($data['fecha_inicio']) || isset($data['fecha_fin'])) {
                $newFechaInicio = $data['fecha_inicio'] ?? $contract->fecha_inicio;
                $newFechaFin = $data['fecha_fin'] ?? $contract->fecha_fin;
                
                if ($this->hasDateOverlap($contract->doctor_id, $newFechaInicio, $newFechaFin, $id)) {
                    throw new \Exception("Existe un solapamiento de fechas con otro contrato activo");
                }

                // Validar que fecha fin sea posterior a fecha inicio
                if ($newFechaFin && $newFechaFin <= $newFechaInicio) {
                    throw new \Exception("La fecha de fin debe ser posterior a la fecha de inicio");
                }
            }

            // No permitir cambio de doctor una vez creado
            if (isset($data['doctor_id']) && $data['doctor_id'] != $contract->doctor_id) {
                throw new \Exception("No se puede cambiar el doctor de un contrato existente");
            }

            $updated = $contract->update($data);

            // Limpiar cache específico
            $this->clearContractCache($id);
            $this->clearRelatedCache($contract->doctor_id);

            DB::commit();

            Log::info('Contrato actualizado exitosamente', [
                'contract_id' => $id,
                'updated_fields' => array_keys($data)
            ]);

            return $updated;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar contrato', [
                'contract_id' => $id,
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Eliminar un contrato (soft delete) con validaciones
     */
    public function delete(int $id): bool
    {
        try {
            DB::beginTransaction();

            $contract = $this->findById($id);
            if (!$contract) {
                throw new \Exception("Contrato no encontrado con ID: {$id}");
            }

            // Verificar que no sea el único contrato activo del doctor
            $activeContracts = $this->model->where('doctor_id', $contract->doctor_id)
                ->where('activo', true)
                ->where('id', '!=', $id)
                ->count();

            if ($activeContracts === 0 && $contract->activo) {
                // Solo advertir, no bloquear la eliminación
                Log::warning('Eliminando último contrato activo del doctor', [
                    'contract_id' => $id,
                    'doctor_id' => $contract->doctor_id
                ]);
            }

            $deleted = $contract->delete();

            // Limpiar cache
            $this->clearContractCache($id);
            $this->clearRelatedCache($contract->doctor_id);

            DB::commit();

            Log::warning('Contrato eliminado', [
                'contract_id' => $id,
                'numero_contrato' => $contract->numero_contrato
            ]);

            return $deleted;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar contrato', [
                'contract_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtener contratos paginados con filtros
     */
    public function getPaginated(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->model->with(['doctor.especialidad']);

        // Aplicar filtros
        $query = $this->applyFilters($query, $filters);

        return $query->orderBy('fecha_inicio', 'desc')
                    ->paginate($perPage);
    }

    /**
     * Buscar contratos por doctor
     */
    public function findByDoctor(int $doctorId): Collection
    {
        return Cache::remember(
            $this->cachePrefix . "doctor_{$doctorId}",
            $this->cacheMinutes,
            function () use ($doctorId) {
                return $this->model->where('doctor_id', $doctorId)
                    ->orderBy('fecha_inicio', 'desc')
                    ->get();
            }
        );
    }

    /**
     * Buscar contratos activos por doctor
     */
    public function findActiveByDoctor(int $doctorId): Collection
    {
        return Cache::remember(
            $this->cachePrefix . "active_doctor_{$doctorId}",
            $this->cacheMinutes,
            function () use ($doctorId) {
                return $this->model->where('doctor_id', $doctorId)
                    ->where('activo', true)
                    ->where(function ($query) {
                        $query->whereNull('fecha_fin')
                              ->orWhere('fecha_fin', '>=', now());
                    })
                    ->orderBy('fecha_inicio', 'desc')
                    ->get();
            }
        );
    }

    /**
     * Obtener contrato activo actual de un doctor
     */
    public function getCurrentActiveContract(int $doctorId): ?DoctorContract
    {
        return Cache::remember(
            $this->cachePrefix . "current_active_{$doctorId}",
            $this->cacheMinutes,
            function () use ($doctorId) {
                return $this->model->where('doctor_id', $doctorId)
                    ->where('activo', true)
                    ->where('fecha_inicio', '<=', now())
                    ->where(function ($query) {
                        $query->whereNull('fecha_fin')
                              ->orWhere('fecha_fin', '>=', now());
                    })
                    ->orderBy('fecha_inicio', 'desc')
                    ->first();
            }
        );
    }

    /**
     * Buscar contratos que vencen pronto
     */
    public function getExpiringSoon(int $days = 30): Collection
    {
        return Cache::remember(
            $this->cachePrefix . "expiring_{$days}",
            60, // Cache más corto para datos dinámicos
            function () use ($days) {
                $expirationDate = now()->addDays($days);
                
                return $this->model->with(['doctor.especialidad'])
                    ->where('activo', true)
                    ->whereNotNull('fecha_fin')
                    ->whereBetween('fecha_fin', [now(), $expirationDate])
                    ->orderBy('fecha_fin')
                    ->get();
            }
        );
    }

    /**
     * Buscar contratos vencidos
     */
    public function getExpired(): Collection
    {
        return Cache::remember(
            $this->cachePrefix . 'expired',
            60,
            function () {
                return $this->model->with(['doctor.especialidad'])
                    ->where('activo', true)
                    ->whereNotNull('fecha_fin')
                    ->where('fecha_fin', '<', now())
                    ->orderBy('fecha_fin', 'desc')
                    ->get();
            }
        );
    }

    /**
     * Obtener estadísticas de contratos
     */
    public function getStatistics(): array
    {
        return Cache::remember(
            $this->cachePrefix . 'statistics',
            120, // 2 horas
            function () {
                $total = $this->model->count();
                $activos = $this->model->where('activo', true)->count();
                
                // Contratos vigentes (activos y dentro de fechas)
                $vigentes = $this->model->where('activo', true)
                    ->where('fecha_inicio', '<=', now())
                    ->where(function ($query) {
                        $query->whereNull('fecha_fin')
                              ->orWhere('fecha_fin', '>=', now());
                    })
                    ->count();

                // Contratos vencidos
                $vencidos = $this->model->where('activo', true)
                    ->whereNotNull('fecha_fin')
                    ->where('fecha_fin', '<', now())
                    ->count();

                // Contratos que vencen en 30 días
                $vencenProximamente = $this->model->where('activo', true)
                    ->whereNotNull('fecha_fin')
                    ->whereBetween('fecha_fin', [now(), now()->addDays(30)])
                    ->count();

                // Tipos de contrato más comunes
                $tiposContrato = $this->model->select('tipo_contrato', DB::raw('COUNT(*) as total'))
                    ->whereNotNull('tipo_contrato')
                    ->groupBy('tipo_contrato')
                    ->orderBy('total', 'desc')
                    ->get()
                    ->pluck('total', 'tipo_contrato')
                    ->toArray();

                // Duración promedio de contratos (solo contratos finalizados)
                $duracionPromedio = $this->model->whereNotNull('fecha_fin')
                    ->where('fecha_fin', '<', now())
                    ->get()
                    ->map(function ($contract) {
                        return Carbon::parse($contract->fecha_inicio)->diffInDays($contract->fecha_fin);
                    })
                    ->avg();

                return [
                    'total' => $total,
                    'activos' => $activos,
                    'inactivos' => $total - $activos,
                    'vigentes' => $vigentes,
                    'vencidos' => $vencidos,
                    'vencen_proximamente' => $vencenProximamente,
                    'tipos_contrato' => $tiposContrato,
                    'duracion_promedio_dias' => round($duracionPromedio ?? 0, 2),
                    'tasa_vigencia' => $activos > 0 ? ($vigentes / $activos) * 100 : 0,
                ];
            }
        );
    }

    /**
     * Buscar contratos por múltiples criterios
     */
    public function search(array $criteria): Collection
    {
        $query = $this->model->with(['doctor.especialidad']);

        if (!empty($criteria['search'])) {
            $search = $criteria['search'];
            $query->where(function ($q) use ($search) {
                $q->where('numero_contrato', 'like', "%{$search}%")
                  ->orWhere('tipo_contrato', 'like', "%{$search}%")
                  ->orWhereHas('doctor', function ($subQ) use ($search) {
                      $subQ->where('nombre', 'like', "%{$search}%")
                           ->orWhere('apellido', 'like', "%{$search}%");
                  });
            });
        }

        return $this->applyFilters($query, $criteria)
                   ->orderBy('fecha_inicio', 'desc')
                   ->get();
    }

    /**
     * Verificar solapamiento de fechas
     */
    public function hasDateOverlap(int $doctorId, string $fechaInicio, string $fechaFin = null, int $excludeContractId = null): bool
    {
        $query = $this->model->where('doctor_id', $doctorId)
            ->where('activo', true);

        if ($excludeContractId) {
            $query->where('id', '!=', $excludeContractId);
        }

        // Verificar solapamiento
        $query->where(function ($q) use ($fechaInicio, $fechaFin) {
            if ($fechaFin) {
                // Contrato con fecha de fin definida
                $q->where(function ($subQ) use ($fechaInicio, $fechaFin) {
                    $subQ->where('fecha_inicio', '<=', $fechaFin)
                         ->where(function ($nestedQ) use ($fechaInicio) {
                             $nestedQ->whereNull('fecha_fin')
                                     ->orWhere('fecha_fin', '>=', $fechaInicio);
                         });
                });
            } else {
                // Contrato indefinido (sin fecha de fin)
                $q->where('fecha_inicio', '<=', $fechaInicio)
                  ->where(function ($subQ) use ($fechaInicio) {
                      $subQ->whereNull('fecha_fin')
                           ->orWhere('fecha_fin', '>=', $fechaInicio);
                  });
            }
        });

        return $query->exists();
    }

    /**
     * Finalizar contrato
     */
    public function finalize(int $id, string $fechaFin = null, string $motivoFinalizacion = null): bool
    {
        try {
            DB::beginTransaction();

            $contract = $this->findById($id);
            if (!$contract) {
                throw new \Exception("Contrato no encontrado con ID: {$id}");
            }

            if (!$contract->activo) {
                throw new \Exception("El contrato ya está inactivo");
            }

            if ($contract->fecha_fin && $contract->fecha_fin < now()) {
                throw new \Exception("El contrato ya está vencido");
            }

            $updateData = [
                'fecha_fin' => $fechaFin ?? now()->format('Y-m-d'),
                'activo' => false,
            ];

            if ($motivoFinalizacion) {
                $updateData['motivo_finalizacion'] = $motivoFinalizacion;
            }

            $updated = $contract->update($updateData);

            // Limpiar cache
            $this->clearContractCache($id);
            $this->clearRelatedCache($contract->doctor_id);

            DB::commit();

            Log::info('Contrato finalizado', [
                'contract_id' => $id,
                'numero_contrato' => $contract->numero_contrato,
                'fecha_fin' => $updateData['fecha_fin'],
                'motivo' => $motivoFinalizacion
            ]);

            return $updated;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al finalizar contrato', [
                'contract_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Renovar contrato
     */
    public function renew(int $id, array $newContractData): DoctorContract
    {
        try {
            DB::beginTransaction();

            $oldContract = $this->findById($id);
            if (!$oldContract) {
                throw new \Exception("Contrato no encontrado con ID: {$id}");
            }

            // Finalizar contrato anterior
            $this->finalize($id, $newContractData['fecha_inicio'] ?? now()->format('Y-m-d'), 'Renovación');

            // Crear nuevo contrato
            $newContractData['doctor_id'] = $oldContract->doctor_id;
            $newContractData['numero_contrato'] = $this->generateContractNumber();
            
            $newContract = $this->create($newContractData);

            DB::commit();

            Log::info('Contrato renovado exitosamente', [
                'old_contract_id' => $id,
                'new_contract_id' => $newContract->id,
                'doctor_id' => $oldContract->doctor_id
            ]);

            return $newContract;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al renovar contrato', [
                'contract_id' => $id,
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
     * Generar número único de contrato
     */
    private function generateContractNumber(): string
    {
        $year = now()->year;
        $lastNumber = $this->model->where('numero_contrato', 'like', "CNT{$year}%")
            ->orderBy('numero_contrato', 'desc')
            ->value('numero_contrato');

        if ($lastNumber) {
            $lastSequence = (int) substr($lastNumber, -6);
            $newSequence = $lastSequence + 1;
        } else {
            $newSequence = 1;
        }

        return "CNT{$year}" . str_pad($newSequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Aplicar filtros a la query
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (isset($filters['doctor_id'])) {
            $query->where('doctor_id', $filters['doctor_id']);
        }

        if (isset($filters['activo'])) {
            $query->where('activo', $filters['activo']);
        }

        if (isset($filters['tipo_contrato'])) {
            $query->where('tipo_contrato', $filters['tipo_contrato']);
        }

        if (isset($filters['fecha_inicio_desde'])) {
            $query->where('fecha_inicio', '>=', $filters['fecha_inicio_desde']);
        }

        if (isset($filters['fecha_inicio_hasta'])) {
            $query->where('fecha_inicio', '<=', $filters['fecha_inicio_hasta']);
        }

        if (isset($filters['fecha_fin_desde'])) {
            $query->where('fecha_fin', '>=', $filters['fecha_fin_desde']);
        }

        if (isset($filters['fecha_fin_hasta'])) {
            $query->where('fecha_fin', '<=', $filters['fecha_fin_hasta']);
        }

        if (isset($filters['vigente'])) {
            if ($filters['vigente']) {
                $query->where('activo', true)
                      ->where('fecha_inicio', '<=', now())
                      ->where(function ($q) {
                          $q->whereNull('fecha_fin')
                            ->orWhere('fecha_fin', '>=', now());
                      });
            }
        }

        if (isset($filters['vencido'])) {
            if ($filters['vencido']) {
                $query->where('activo', true)
                      ->whereNotNull('fecha_fin')
                      ->where('fecha_fin', '<', now());
            }
        }

        if (isset($filters['especialidad_id'])) {
            $query->whereHas('doctor', function ($q) use ($filters) {
                $q->where('especialidad_id', $filters['especialidad_id']);
            });
        }

        return $query;
    }

    /**
     * Limpiar cache específico del contrato
     */
    private function clearContractCache(int $contractId): void
    {
        $contract = $this->model->find($contractId);
        if ($contract) {
            $keys = [
                $this->cachePrefix . "id_{$contractId}",
                $this->cachePrefix . "number_{$contract->numero_contrato}",
            ];

            foreach ($keys as $key) {
                Cache::forget($key);
            }
        }
    }

    /**
     * Limpiar cache relacionado
     */
    private function clearRelatedCache(int $doctorId): void
    {
        $cacheKeys = [
            $this->cachePrefix . "doctor_{$doctorId}",
            $this->cachePrefix . "active_doctor_{$doctorId}",
            $this->cachePrefix . "current_active_{$doctorId}",
            $this->cachePrefix . 'statistics',
            $this->cachePrefix . 'expired',
            $this->cachePrefix . 'expiring_30',
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }
}
