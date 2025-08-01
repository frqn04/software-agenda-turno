<?php

namespace App\Services;

use App\Repositories\TurnoRepository;
use App\Repositories\DoctorRepository;
use App\Models\Turno;
use App\Models\Doctor;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class TurnoService
{
    public function __construct(
        private TurnoRepository $turnoRepository,
        private DoctorRepository $doctorRepository,
        private AppointmentValidationService $validationService
    ) {}

    /**
     * Obtener turnos con filtros
     */
    public function getTurnos(array $filters): array
    {
        $query = $this->turnoRepository->query();

        if (isset($filters['doctor_id'])) {
            $query->where('doctor_id', $filters['doctor_id']);
        }

        if (isset($filters['paciente_id'])) {
            $query->where('paciente_id', $filters['paciente_id']);
        }

        if (isset($filters['fecha'])) {
            $query->where('fecha', $filters['fecha']);
        }

        if (isset($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }

        return $query->with(['doctor', 'paciente'])->orderBy('fecha')->orderBy('hora_inicio')->get()->toArray();
    }

    /**
     * Obtener turno por ID
     */
    public function getById(int $id): ?array
    {
        $turno = $this->turnoRepository->findById($id);
        return $turno ? $turno->load(['doctor', 'paciente'])->toArray() : null;
    }
    public function create(array $data): Turno
    {
        // Validar que el doctor existe y está activo
        $doctor = $this->doctorRepository->findWithActiveContracts($data['doctor_id']);
        
        if (!$doctor) {
            throw ValidationException::withMessages([
                'doctor_id' => 'El doctor no existe o no está activo.'
            ]);
        }

        // Validar que el doctor tiene contratos activos
        if (!$this->doctorRepository->hasActiveContracts($data['doctor_id'])) {
            throw ValidationException::withMessages([
                'doctor_id' => 'El doctor no tiene contratos activos.'
            ]);
        }

        // Calcular hora_fin si no se proporciona
        if (!isset($data['hora_fin'])) {
            $duration = $data['duration_minutes'] ?? 30;
            $data['hora_fin'] = Carbon::parse($data['hora_inicio'])
                ->addMinutes($duration)
                ->format('H:i:s');
        }

        // Validar solapamientos
        $overlapping = $this->turnoRepository->findOverlappingAppointments(
            $data['doctor_id'],
            $data['fecha'],
            $data['hora_inicio'],
            $data['hora_fin']
        );

        if ($overlapping->isNotEmpty()) {
            throw ValidationException::withMessages([
                'hora_inicio' => 'Ya existe un turno en este horario.'
            ]);
        }

        // Validar que está dentro del horario de trabajo
        if (!$this->validationService->validateWithinSchedule(
            $data['doctor_id'],
            $data['fecha'],
            $data['hora_inicio'],
            $data['hora_fin']
        )) {
            throw ValidationException::withMessages([
                'hora_inicio' => 'El horario está fuera del horario de trabajo del doctor.'
            ]);
        }

        return $this->turnoRepository->create($data);
    }

    /**
     * Actualizar un turno existente
     */
    public function update(int $id, array $data): Turno
    {
        $turno = $this->turnoRepository->findById($id);
        
        if (!$turno) {
            throw ValidationException::withMessages([
                'id' => 'El turno no existe.'
            ]);
        }

        // Si se cambian horarios, validar solapamientos
        if (isset($data['hora_inicio']) || isset($data['hora_fin'])) {
            $horaInicio = $data['hora_inicio'] ?? $turno->hora_inicio;
            $horaFin = $data['hora_fin'] ?? $turno->hora_fin;
            $fecha = $data['fecha'] ?? $turno->fecha;

            $overlapping = $this->turnoRepository->findOverlappingAppointments(
                $turno->doctor_id,
                $fecha,
                $horaInicio,
                $horaFin,
                $id // Excluir el turno actual
            );

            if ($overlapping->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'hora_inicio' => 'Ya existe un turno en este horario.'
                ]);
            }
        }

        $this->turnoRepository->update($id, $data);
        return $this->turnoRepository->findById($id);
    }

    /**
     * Obtener slots disponibles para un doctor en una fecha
     */
    public function getAvailableSlots(int $doctorId, string $fecha): array
    {
        return $this->turnoRepository->getAvailableSlots($doctorId, $fecha);
    }

    /**
     * Obtener turnos por doctor y rango de fechas
     */
    public function getTurnosByDoctorAndDateRange(int $doctorId, string $fechaInicio, string $fechaFin): array
    {
        return $this->turnoRepository->findByDoctorAndDateRange($doctorId, $fechaInicio, $fechaFin)->toArray();
    }

    /**
     * Cancelar un turno
     */
    public function cancel(int $id, string $motivo = null): bool
    {
        $data = ['estado' => 'cancelado'];
        
        if ($motivo) {
            $data['observaciones'] = $motivo;
        }

        return $this->turnoRepository->update($id, $data);
    }

    /**
     * Confirmar un turno
     */
    public function confirm(int $id): bool
    {
        return $this->turnoRepository->update($id, ['estado' => 'confirmado']);
    }

    /**
     * Completar un turno
     */
    public function complete(int $id, array $additionalData = []): bool
    {
        $data = array_merge(['estado' => 'completado'], $additionalData);
        return $this->turnoRepository->update($id, $data);
    }

    /**
     * Eliminar un turno (soft delete)
     */
    public function delete(int $id): bool
    {
        return $this->turnoRepository->delete($id);
    }
}
