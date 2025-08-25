<?php

namespace App\Repositories;

use App\Models\Turno;
use App\Models\Doctor;
use App\Models\Paciente;
use App\Models\DoctorScheduleSlot;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

/**
 * Repository empresarial para el modelo Turno
 * Maneja operaciones optimizadas con cache, validaciones médicas y control de conflictos
 * Incluye funcionalidades específicas para gestión de turnos médicos
 */
class TurnoRepository
{
    protected Turno $model;
    protected int $cacheMinutes = 15;
    protected string $cachePrefix = 'turno_';

    public function __construct(Turno $model)
    {
        $this->model = $model;
    }

    /**
     * Crear un nuevo turno con validaciones empresariales
     */
    public function create(array $data): Turno
    {
        try {
            DB::beginTransaction();

            // Validar disponibilidad del doctor
            if (!$this->isDoctorAvailable($data['doctor_id'], $data['fecha'], $data['hora'])) {
                throw new \Exception("El doctor no está disponible en la fecha y hora seleccionada");
            }

            // Validar conflictos de horarios
            if ($this->hasTimeConflicts($data['doctor_id'], $data['fecha'], $data['hora'], $data['duracion'] ?? 30)) {
                throw new \Exception("Existe un conflicto de horarios con otro turno");
            }

            // Validar que el paciente no tenga otro turno en horario cercano
            if ($this->patientHasNearbyAppointment($data['paciente_id'], $data['fecha'], $data['hora'])) {
                throw new \Exception("El paciente ya tiene un turno programado muy cerca de esta fecha y hora");
            }

            // Asignar número de turno único
            $data['numero_turno'] = $this->generateTurnoNumber($data['fecha']);
            $data['estado'] = $data['estado'] ?? Turno::ESTADO_PROGRAMADO;

            $turno = $this->model->create($data);

            // Marcar slot como ocupado si existe
            $this->markSlotAsOccupied($data['doctor_id'], $data['fecha'], $data['hora']);

            // Limpiar cache relacionado
            $this->clearRelatedCache($data['doctor_id'], $data['paciente_id'], $data['fecha']);

            DB::commit();

            Log::info('Turno creado exitosamente', [
                'turno_id' => $turno->id,
                'numero_turno' => $turno->numero_turno,
                'doctor_id' => $data['doctor_id'],
                'paciente_id' => $data['paciente_id'],
                'fecha' => $data['fecha'],
                'hora' => $data['hora']
            ]);

            return $turno;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear turno', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Buscar turno por ID con cache
     */
    public function findById(int $id): ?Turno
    {
        return Cache::remember(
            $this->cachePrefix . "id_{$id}",
            $this->cacheMinutes,
            function () use ($id) {
                return $this->model->with(['doctor.especialidad', 'paciente'])->find($id);
            }
        );
    }

    /**
     * Buscar turno por número con cache
     */
    public function findByNumber(string $numeroTurno): ?Turno
    {
        return Cache::remember(
            $this->cachePrefix . "number_{$numeroTurno}",
            $this->cacheMinutes,
            function () use ($numeroTurno) {
                return $this->model->with(['doctor.especialidad', 'paciente'])
                    ->where('numero_turno', $numeroTurno)
                    ->first();
            }
        );
    }

    /**
     * Actualizar un turno con validaciones
     */
    public function update(int $id, array $data): bool
    {
        try {
            DB::beginTransaction();

            $turno = $this->findById($id);
            if (!$turno) {
                throw new \Exception("Turno no encontrado con ID: {$id}");
            }

            // Validar cambios de fecha/hora
            if (isset($data['fecha']) || isset($data['hora']) || isset($data['doctor_id'])) {
                $newDoctorId = $data['doctor_id'] ?? $turno->doctor_id;
                $newFecha = $data['fecha'] ?? $turno->fecha;
                $newHora = $data['hora'] ?? $turno->hora;
                $duracion = $data['duracion'] ?? $turno->duracion ?? 30;

                // Validar disponibilidad del doctor
                if (!$this->isDoctorAvailable($newDoctorId, $newFecha, $newHora, $id)) {
                    throw new \Exception("El doctor no está disponible en la nueva fecha y hora");
                }

                // Validar conflictos de horarios
                if ($this->hasTimeConflicts($newDoctorId, $newFecha, $newHora, $duracion, $id)) {
                    throw new \Exception("Existe un conflicto de horarios con otro turno");
                }

                // Liberar slot anterior si cambió
                if ($turno->doctor_id !== $newDoctorId || $turno->fecha !== $newFecha || $turno->hora !== $newHora) {
                    $this->freeSlot($turno->doctor_id, $turno->fecha, $turno->hora);
                    $this->markSlotAsOccupied($newDoctorId, $newFecha, $newHora);
                }
            }

            $updated = $turno->update($data);

            // Limpiar cache específico
            $this->clearTurnoCache($id);
            $this->clearRelatedCache($turno->doctor_id, $turno->paciente_id, $turno->fecha);

            DB::commit();

            Log::info('Turno actualizado exitosamente', [
                'turno_id' => $id,
                'updated_fields' => array_keys($data)
            ]);

            return $updated;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar turno', [
                'turno_id' => $id,
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Cancelar un turno
     */
    public function cancel(int $id, string $motivo = null): bool
    {
        try {
            DB::beginTransaction();

            $turno = $this->findById($id);
            if (!$turno) {
                throw new \Exception("Turno no encontrado con ID: {$id}");
            }

            if ($turno->estado === Turno::ESTADO_CANCELADO) {
                throw new \Exception("El turno ya está cancelado");
            }

            if ($turno->estado === Turno::ESTADO_COMPLETADO) {
                throw new \Exception("No se puede cancelar un turno completado");
            }

            $updated = $turno->update([
                'estado' => Turno::ESTADO_CANCELADO,
                'motivo_cancelacion' => $motivo,
                'fecha_cancelacion' => now(),
            ]);

            // Liberar slot
            $this->freeSlot($turno->doctor_id, $turno->fecha, $turno->hora);

            // Limpiar cache
            $this->clearTurnoCache($id);
            $this->clearRelatedCache($turno->doctor_id, $turno->paciente_id, $turno->fecha);

            DB::commit();

            Log::info('Turno cancelado', [
                'turno_id' => $id,
                'numero_turno' => $turno->numero_turno,
                'motivo' => $motivo
            ]);

            return $updated;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al cancelar turno', [
                'turno_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Completar un turno
     */
    public function complete(int $id, array $observaciones = []): bool
    {
        try {
            DB::beginTransaction();

            $turno = $this->findById($id);
            if (!$turno) {
                throw new \Exception("Turno no encontrado con ID: {$id}");
            }

            if ($turno->estado === Turno::ESTADO_COMPLETADO) {
                throw new \Exception("El turno ya está completado");
            }

            if ($turno->estado === Turno::ESTADO_CANCELADO) {
                throw new \Exception("No se puede completar un turno cancelado");
            }

            $updateData = [
                'estado' => Turno::ESTADO_COMPLETADO,
                'fecha_atencion' => now(),
            ];

            if (!empty($observaciones)) {
                $updateData['observaciones'] = json_encode($observaciones);
            }

            $updated = $turno->update($updateData);

            // Limpiar cache
            $this->clearTurnoCache($id);
            $this->clearRelatedCache($turno->doctor_id, $turno->paciente_id, $turno->fecha);

            DB::commit();

            Log::info('Turno completado', [
                'turno_id' => $id,
                'numero_turno' => $turno->numero_turno
            ]);

            return $updated;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al completar turno', [
                'turno_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtener turnos paginados con filtros
     */
    public function getPaginated(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->model->with(['doctor.especialidad', 'paciente']);

        // Aplicar filtros
        $query = $this->applyFilters($query, $filters);

        return $query->orderBy('fecha', 'desc')
                    ->orderBy('hora', 'desc')
                    ->paginate($perPage);
    }

    /**
     * Obtener turnos por doctor en una fecha específica
     */
    public function findByDoctorAndDate(int $doctorId, string $fecha): Collection
    {
        return Cache::remember(
            $this->cachePrefix . "doctor_{$doctorId}_date_{$fecha}",
            $this->cacheMinutes,
            function () use ($doctorId, $fecha) {
                return $this->model->with(['paciente'])
                    ->where('doctor_id', $doctorId)
                    ->where('fecha', $fecha)
                    ->whereIn('estado', [Turno::ESTADO_PROGRAMADO, Turno::ESTADO_CONFIRMADO])
                    ->orderBy('hora')
                    ->get();
            }
        );
    }

    /**
     * Obtener turnos por paciente
     */
    public function findByPaciente(int $pacienteId, int $limit = 10): Collection
    {
        return Cache::remember(
            $this->cachePrefix . "paciente_{$pacienteId}_limit_{$limit}",
            $this->cacheMinutes,
            function () use ($pacienteId, $limit) {
                return $this->model->with(['doctor.especialidad'])
                    ->where('paciente_id', $pacienteId)
                    ->orderBy('fecha', 'desc')
                    ->orderBy('hora', 'desc')
                    ->limit($limit)
                    ->get();
            }
        );
    }

    /**
     * Obtener próximos turnos (próximos N días)
     */
    public function getUpcoming(int $days = 7): Collection
    {
        return Cache::remember(
            $this->cachePrefix . "upcoming_{$days}",
            5, // 5 minutos para datos dinámicos
            function () use ($days) {
                return $this->model->with(['doctor.especialidad', 'paciente'])
                    ->where('fecha', '>=', now()->format('Y-m-d'))
                    ->where('fecha', '<=', now()->addDays($days)->format('Y-m-d'))
                    ->whereIn('estado', [Turno::ESTADO_PROGRAMADO, Turno::ESTADO_CONFIRMADO])
                    ->orderBy('fecha')
                    ->orderBy('hora')
                    ->get();
            }
        );
    }

    /**
     * Obtener turnos de hoy
     */
    public function getTodayAppointments(): Collection
    {
        return Cache::remember(
            $this->cachePrefix . 'today_' . now()->format('Y-m-d'),
            5,
            function () {
                return $this->model->with(['doctor.especialidad', 'paciente'])
                    ->where('fecha', now()->format('Y-m-d'))
                    ->whereIn('estado', [Turno::ESTADO_PROGRAMADO, Turno::ESTADO_CONFIRMADO])
                    ->orderBy('hora')
                    ->get();
            }
        );
    }

    /**
     * Verificar disponibilidad de doctor
     */
    public function isDoctorAvailable(int $doctorId, string $fecha, string $hora, int $excludeTurnoId = null): bool
    {
        $query = $this->model->where('doctor_id', $doctorId)
            ->where('fecha', $fecha)
            ->where('hora', $hora)
            ->whereIn('estado', [Turno::ESTADO_PROGRAMADO, Turno::ESTADO_CONFIRMADO]);

        if ($excludeTurnoId) {
            $query->where('id', '!=', $excludeTurnoId);
        }

        $hasConflict = $query->exists();

        // Verificar también en horarios laborales del doctor
        $doctor = Doctor::find($doctorId);
        if (!$doctor || !$doctor->activo) {
            return false;
        }

        // Aquí puedes agregar lógica adicional para verificar horarios laborales
        return !$hasConflict;
    }

    /**
     * Verificar conflictos de horarios
     */
    public function hasTimeConflicts(int $doctorId, string $fecha, string $hora, int $duracion = 30, int $excludeTurnoId = null): bool
    {
        $startTime = Carbon::parse($fecha . ' ' . $hora);
        $endTime = $startTime->copy()->addMinutes($duracion);

        $query = $this->model->where('doctor_id', $doctorId)
            ->where('fecha', $fecha)
            ->whereIn('estado', [Turno::ESTADO_PROGRAMADO, Turno::ESTADO_CONFIRMADO])
            ->where(function ($q) use ($hora, $startTime, $endTime) {
                $q->where(function ($subQ) use ($hora, $startTime, $endTime) {
                    // Turno existente que se solapa con el nuevo
                    $subQ->whereRaw("STR_TO_DATE(CONCAT(fecha, ' ', hora), '%Y-%m-%d %H:%i:%s') < ?", [$endTime])
                         ->whereRaw("DATE_ADD(STR_TO_DATE(CONCAT(fecha, ' ', hora), '%Y-%m-%d %H:%i:%s'), INTERVAL COALESCE(duracion, 30) MINUTE) > ?", [$startTime]);
                });
            });

        if ($excludeTurnoId) {
            $query->where('id', '!=', $excludeTurnoId);
        }

        return $query->exists();
    }

    /**
     * Verificar si paciente tiene turno cercano
     */
    public function patientHasNearbyAppointment(int $pacienteId, string $fecha, string $hora, int $marginMinutes = 60): bool
    {
        $appointmentTime = Carbon::parse($fecha . ' ' . $hora);
        $startTime = $appointmentTime->copy()->subMinutes($marginMinutes);
        $endTime = $appointmentTime->copy()->addMinutes($marginMinutes);

        return $this->model->where('paciente_id', $pacienteId)
            ->whereIn('estado', [Turno::ESTADO_PROGRAMADO, Turno::ESTADO_CONFIRMADO])
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereRaw("STR_TO_DATE(CONCAT(fecha, ' ', hora), '%Y-%m-%d %H:%i:%s') BETWEEN ? AND ?", [$startTime, $endTime]);
            })
            ->exists();
    }

    /**
     * Obtener estadísticas de turnos
     */
    public function getStatistics(Carbon $startDate = null, Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth();
        $endDate = $endDate ?? now()->endOfMonth();
        
        $cacheKey = $this->cachePrefix . "stats_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}";

        return Cache::remember($cacheKey, 60, function () use ($startDate, $endDate) {
            $baseQuery = $this->model->whereBetween('fecha', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);

            $total = $baseQuery->count();
            $programados = $baseQuery->where('estado', Turno::ESTADO_PROGRAMADO)->count();
            $confirmados = $baseQuery->where('estado', Turno::ESTADO_CONFIRMADO)->count();
            $completados = $baseQuery->where('estado', Turno::ESTADO_COMPLETADO)->count();
            $cancelados = $baseQuery->where('estado', Turno::ESTADO_CANCELADO)->count();

            // Estadísticas por día de la semana
            $porDiaSemana = DB::table('turnos')
                ->select(DB::raw('DAYOFWEEK(fecha) as dia_semana, COUNT(*) as total'))
                ->whereBetween('fecha', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->groupBy('dia_semana')
                ->orderBy('dia_semana')
                ->pluck('total', 'dia_semana')
                ->toArray();

            // Estadísticas por especialidad
            $porEspecialidad = DB::table('turnos')
                ->join('doctors', 'turnos.doctor_id', '=', 'doctors.id')
                ->join('especialidads', 'doctors.especialidad_id', '=', 'especialidads.id')
                ->select('especialidads.nombre', DB::raw('COUNT(*) as total'))
                ->whereBetween('turnos.fecha', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->groupBy('especialidads.id', 'especialidads.nombre')
                ->orderBy('total', 'desc')
                ->get()
                ->toArray();

            return [
                'total' => $total,
                'por_estado' => [
                    'programados' => $programados,
                    'confirmados' => $confirmados,
                    'completados' => $completados,
                    'cancelados' => $cancelados,
                ],
                'tasas' => [
                    'finalizacion' => $total > 0 ? ($completados / $total) * 100 : 0,
                    'cancelacion' => $total > 0 ? ($cancelados / $total) * 100 : 0,
                    'confirmacion' => $total > 0 ? (($confirmados + $completados) / $total) * 100 : 0,
                ],
                'por_dia_semana' => $porDiaSemana,
                'por_especialidad' => $porEspecialidad,
            ];
        });
    }

    /**
     * Obtener disponibilidad de doctor por fecha
     */
    public function getDoctorAvailability(int $doctorId, string $fecha): array
    {
        return Cache::remember(
            $this->cachePrefix . "availability_{$doctorId}_{$fecha}",
            30,
            function () use ($doctorId, $fecha) {
                $occupiedSlots = $this->model->where('doctor_id', $doctorId)
                    ->where('fecha', $fecha)
                    ->whereIn('estado', [Turno::ESTADO_PROGRAMADO, Turno::ESTADO_CONFIRMADO])
                    ->pluck('hora')
                    ->toArray();

                // Obtener slots programados del doctor
                $scheduledSlots = DoctorScheduleSlot::where('doctor_id', $doctorId)
                    ->where('dia_semana', Carbon::parse($fecha)->dayOfWeek)
                    ->where('activo', true)
                    ->orderBy('hora_inicio')
                    ->get();

                $availableSlots = [];
                $unavailableSlots = [];

                foreach ($scheduledSlots as $slot) {
                    $slotTime = $slot->hora_inicio;
                    if (in_array($slotTime, $occupiedSlots)) {
                        $unavailableSlots[] = $slotTime;
                    } else {
                        $availableSlots[] = $slotTime;
                    }
                }

                return [
                    'available' => $availableSlots,
                    'unavailable' => $unavailableSlots,
                    'occupied' => $occupiedSlots,
                ];
            }
        );
    }

    /**
     * Buscar turnos por doctor y rango de fechas
     */
    public function findByDoctorAndDateRange(int $doctorId, string $fechaInicio, string $fechaFin): Collection
    {
        return $this->model->where('doctor_id', $doctorId)
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get();
    }

    /**
     * Buscar turnos con posibles solapamientos
     */
    public function findOverlappingAppointments(int $doctorId, string $fecha, string $horaInicio, string $horaFin, ?int $excludeId = null): Collection
    {
        $query = $this->model->where('doctor_id', $doctorId)
            ->where('fecha', $fecha)
            ->where('estado', '!=', 'cancelado')
            ->where(function ($q) use ($horaInicio, $horaFin) {
                $q->whereBetween('hora', [$horaInicio, $horaFin])
                  ->orWhere(function ($nested) use ($horaInicio, $horaFin) {
                      $nested->where('hora', '<=', $horaInicio)
                             ->whereRaw("DATE_ADD(STR_TO_DATE(CONCAT(fecha, ' ', hora), '%Y-%m-%d %H:%i:%s'), INTERVAL COALESCE(duracion, 30) MINUTE) >= STR_TO_DATE(CONCAT(?, ' ', ?), '%Y-%m-%d %H:%i:%s')", [$fecha, $horaFin]);
                  });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get();
    }

    /**
     * Obtener slots disponibles para un doctor en una fecha
     */
    public function getAvailableSlots(int $doctorId, string $fecha): array
    {
        $doctor = Doctor::with(['scheduleSlots'])->find($doctorId);
        
        if (!$doctor) {
            return [];
        }

        $dayOfWeek = Carbon::parse($fecha)->dayOfWeek;
        $scheduleSlots = $doctor->scheduleSlots()
            ->where('dia_semana', $dayOfWeek)
            ->where('activo', true)
            ->orderBy('hora_inicio')
            ->get();

        if ($scheduleSlots->isEmpty()) {
            return [];
        }

        // Obtener turnos ocupados
        $ocupados = $this->findByDoctorAndDate($doctorId, $fecha)
            ->pluck('hora')
            ->toArray();

        // Generar slots disponibles
        $slots = [];
        
        foreach ($scheduleSlots as $schedule) {
            $current = Carbon::parse($schedule->hora_inicio);
            $end = Carbon::parse($schedule->hora_fin);
            $duration = $schedule->duracion_slot ?? 30;
            
            while ($current->lt($end)) {
                $timeSlot = $current->format('H:i:s');
                
                if (!in_array($timeSlot, $ocupados)) {
                    $slots[] = $timeSlot;
                }
                
                $current->addMinutes($duration);
            }
        }

        return $slots;
    }

    /**
     * Eliminar turno (soft delete)
     */
    public function delete(int $id): bool
    {
        try {
            DB::beginTransaction();

            $turno = $this->findById($id);
            if (!$turno) {
                throw new \Exception("Turno no encontrado con ID: {$id}");
            }

            // Solo permitir eliminar turnos cancelados o futuros no confirmados
            if ($turno->estado === Turno::ESTADO_COMPLETADO) {
                throw new \Exception("No se puede eliminar un turno completado");
            }

            if ($turno->fecha < now()->format('Y-m-d') && $turno->estado !== Turno::ESTADO_CANCELADO) {
                throw new \Exception("No se puede eliminar un turno pasado que no esté cancelado");
            }

            $deleted = $turno->delete();

            // Liberar slot si no estaba cancelado
            if ($turno->estado !== Turno::ESTADO_CANCELADO) {
                $this->freeSlot($turno->doctor_id, $turno->fecha, $turno->hora);
            }

            // Limpiar cache
            $this->clearTurnoCache($id);
            $this->clearRelatedCache($turno->doctor_id, $turno->paciente_id, $turno->fecha);

            DB::commit();

            Log::warning('Turno eliminado', [
                'turno_id' => $id,
                'numero_turno' => $turno->numero_turno
            ]);

            return $deleted;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar turno', [
                'turno_id' => $id,
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
     * Generar número único de turno
     */
    private function generateTurnoNumber(string $fecha): string
    {
        $date = Carbon::parse($fecha);
        $prefix = $date->format('Ymd');
        
        $lastNumber = $this->model->where('fecha', $fecha)
            ->where('numero_turno', 'like', $prefix . '%')
            ->orderBy('numero_turno', 'desc')
            ->value('numero_turno');

        if ($lastNumber) {
            $lastSequence = (int) substr($lastNumber, -4);
            $newSequence = $lastSequence + 1;
        } else {
            $newSequence = 1;
        }

        return $prefix . str_pad($newSequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Marcar slot como ocupado
     */
    private function markSlotAsOccupied(int $doctorId, string $fecha, string $hora): void
    {
        $dayOfWeek = Carbon::parse($fecha)->dayOfWeek;
        
        DoctorScheduleSlot::where('doctor_id', $doctorId)
            ->where('dia_semana', $dayOfWeek)
            ->where('hora_inicio', $hora)
            ->update(['disponible' => false]);
    }

    /**
     * Liberar slot
     */
    private function freeSlot(int $doctorId, string $fecha, string $hora): void
    {
        $dayOfWeek = Carbon::parse($fecha)->dayOfWeek;
        
        DoctorScheduleSlot::where('doctor_id', $doctorId)
            ->where('dia_semana', $dayOfWeek)
            ->where('hora_inicio', $hora)
            ->update(['disponible' => true]);
    }

    /**
     * Aplicar filtros a la query
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (isset($filters['doctor_id'])) {
            $query->where('doctor_id', $filters['doctor_id']);
        }

        if (isset($filters['paciente_id'])) {
            $query->where('paciente_id', $filters['paciente_id']);
        }

        if (isset($filters['fecha_desde'])) {
            $query->where('fecha', '>=', $filters['fecha_desde']);
        }

        if (isset($filters['fecha_hasta'])) {
            $query->where('fecha', '<=', $filters['fecha_hasta']);
        }

        if (isset($filters['estado'])) {
            if (is_array($filters['estado'])) {
                $query->whereIn('estado', $filters['estado']);
            } else {
                $query->where('estado', $filters['estado']);
            }
        }

        if (isset($filters['especialidad_id'])) {
            $query->whereHas('doctor', function ($q) use ($filters) {
                $q->where('especialidad_id', $filters['especialidad_id']);
            });
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('numero_turno', 'like', "%{$search}%")
                  ->orWhereHas('paciente', function ($subQ) use ($search) {
                      $subQ->where('nombre', 'like', "%{$search}%")
                           ->orWhere('apellido', 'like', "%{$search}%")
                           ->orWhere('dni', 'like', "%{$search}%");
                  })
                  ->orWhereHas('doctor', function ($subQ) use ($search) {
                      $subQ->where('nombre', 'like', "%{$search}%")
                           ->orWhere('apellido', 'like', "%{$search}%");
                  });
            });
        }

        return $query;
    }

    /**
     * Limpiar cache específico del turno
     */
    private function clearTurnoCache(int $turnoId): void
    {
        $turno = $this->model->find($turnoId);
        if ($turno) {
            $keys = [
                $this->cachePrefix . "id_{$turnoId}",
                $this->cachePrefix . "number_{$turno->numero_turno}",
            ];

            foreach ($keys as $key) {
                Cache::forget($key);
            }
        }
    }

    /**
     * Limpiar cache relacionado
     */
    private function clearRelatedCache(int $doctorId, int $pacienteId, string $fecha): void
    {
        $cacheKeys = [
            $this->cachePrefix . "doctor_{$doctorId}_date_{$fecha}",
            $this->cachePrefix . "paciente_{$pacienteId}_limit_10",
            $this->cachePrefix . "upcoming_7",
            $this->cachePrefix . "today_" . now()->format('Y-m-d'),
            $this->cachePrefix . "availability_{$doctorId}_{$fecha}",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        // Limpiar estadísticas del mes actual
        $currentMonth = now()->startOfMonth()->format('Y-m-d');
        $endMonth = now()->endOfMonth()->format('Y-m-d');
        Cache::forget($this->cachePrefix . "stats_{$currentMonth}_{$endMonth}");
    }
}
