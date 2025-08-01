<?php

namespace App\Repositories;

use App\Models\Turno;
use App\Models\Doctor;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class TurnoRepository
{
    public function __construct(
        private Turno $model
    ) {}

    /**
     * Buscar turnos por doctor y fecha con posibles solapamientos
     */
    public function findOverlappingAppointments(int $doctorId, string $fecha, string $horaInicio, string $horaFin, ?int $excludeId = null): Collection
    {
        $query = Turno::where('doctor_id', $doctorId)
            ->where('fecha', $fecha)
            ->where('estado', '!=', 'cancelado')
            ->where(function ($q) use ($horaInicio, $horaFin) {
                $q->whereBetween('hora_inicio', [$horaInicio, $horaFin])
                  ->orWhereBetween('hora_fin', [$horaInicio, $horaFin])
                  ->orWhere(function ($nested) use ($horaInicio, $horaFin) {
                      $nested->where('hora_inicio', '<=', $horaInicio)
                             ->where('hora_fin', '>=', $horaFin);
                  });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get();
    }

    /**
     * Obtener turnos por doctor y rango de fechas
     */
    public function findByDoctorAndDateRange(int $doctorId, string $fechaInicio, string $fechaFin): Collection
    {
        return Turno::where('doctor_id', $doctorId)
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();
    }

    /**
     * Obtener turnos por paciente
     */
    public function findByPaciente(int $pacienteId, int $limit = 10): Collection
    {
        return Turno::where('paciente_id', $pacienteId)
            ->orderBy('fecha', 'desc')
            ->orderBy('hora_inicio', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Crear un nuevo turno
     */
    public function create(array $data): Turno
    {
        return Turno::create($data);
    }

    /**
     * Actualizar un turno existente
     */
    public function update(int $id, array $data): bool
    {
        return Turno::where('id', $id)->update($data);
    }

    /**
     * Encontrar turno por ID
     */
    public function findById(int $id): ?Turno
    {
        return Turno::find($id);
    }

    /**
     * Obtener slots disponibles para un doctor en una fecha
     */
    public function getAvailableSlots(int $doctorId, string $fecha): array
    {
        $doctor = Doctor::with(['scheduleSlots', 'contracts'])->find($doctorId);
        
        if (!$doctor) {
            return [];
        }

        $dayOfWeek = Carbon::parse($fecha)->dayOfWeek;
        $schedule = $doctor->scheduleSlots()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();

        if (!$schedule) {
            return [];
        }

        // Obtener turnos ocupados
        $ocupados = $this->findByDoctorAndDateRange($doctorId, $fecha, $fecha)
            ->pluck('hora_inicio')
            ->toArray();

        // Generar slots disponibles
        $slots = [];
        $current = Carbon::parse($schedule->start_time);
        $end = Carbon::parse($schedule->end_time);
        
        while ($current->lt($end)) {
            $timeSlot = $current->format('H:i:s');
            
            if (!in_array($timeSlot, $ocupados)) {
                $slots[] = $timeSlot;
            }
            
            $current->addMinutes($schedule->slot_duration_minutes);
        }

        return $slots;
    }

    /**
     * Eliminar turno (soft delete)
     */
    public function delete(int $id): bool
    {
        $turno = $this->findById($id);
        return $turno ? $turno->delete() : false;
    }

    /**
     * Obtener modelo base para queries personalizadas
     */
    public function query()
    {
        return $this->model->newQuery();
    }
}
