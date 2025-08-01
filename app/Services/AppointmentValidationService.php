<?php

namespace App\Services;

use App\Models\Turno;
use App\Models\Doctor;
use Carbon\Carbon;

class AppointmentValidationService
{
    /**
     * Validar que no haya overlap de turnos para un doctor
     */
    public function validateNoOverlap(int $doctorId, string $fecha, string $horaInicio, string $horaFin, ?int $excludeAppointmentId = null): bool
    {
        $query = Turno::where('doctor_id', $doctorId)
            ->where('fecha', $fecha)
            ->where('estado', '!=', 'cancelado')
            ->where(function ($q) use ($horaInicio, $horaFin) {
                // Verificar solapamiento
                $q->where(function ($subQ) use ($horaInicio, $horaFin) {
                    // Caso 1: El nuevo turno empieza antes de que termine uno existente
                    $subQ->where('hora_inicio', '<', $horaFin)
                         ->where('hora_fin', '>', $horaInicio);
                });
            });

        if ($excludeAppointmentId) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        return $query->count() === 0;
    }

    /**
     * Validar que el turno esté dentro del contrato activo del doctor
     */
    public function validateWithinContract(int $doctorId, string $fecha): bool
    {
        $doctor = Doctor::with('contratos')->find($doctorId);
        
        if (!$doctor) {
            return false;
        }

        $fechaCarbon = Carbon::parse($fecha);
        
        return $doctor->contratos()
            ->where('is_active', true)
            ->where('fecha_inicio', '<=', $fechaCarbon)
            ->where('fecha_fin', '>=', $fechaCarbon)
            ->exists();
    }

    /**
     * Validar que el turno esté dentro del horario disponible del doctor
     */
    public function validateWithinSchedule(int $doctorId, string $fecha, string $horaInicio, string $horaFin): bool
    {
        $doctor = Doctor::with('horarios')->find($doctorId);
        
        if (!$doctor) {
            return false;
        }

        $fechaCarbon = Carbon::parse($fecha);
        $dayOfWeek = $fechaCarbon->dayOfWeek;
        
        $horaInicioCarbon = Carbon::parse($horaInicio);
        $horaFinCarbon = Carbon::parse($horaFin);

        return $doctor->horarios()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->where('start_time', '<=', $horaInicioCarbon->format('H:i:s'))
            ->where('end_time', '>=', $horaFinCarbon->format('H:i:s'))
            ->exists();
    }

    /**
     * Validar todas las reglas de negocio para un turno
     */
    public function validateAppointment(array $data, ?int $excludeAppointmentId = null): array
    {
        $errors = [];

        // Validar overlap
        if (!$this->validateNoOverlap(
            $data['doctor_id'],
            $data['fecha'],
            $data['hora_inicio'],
            $data['hora_fin'],
            $excludeAppointmentId
        )) {
            $errors[] = 'El doctor ya tiene un turno en ese horario';
        }

        // Validar contrato activo
        if (!$this->validateWithinContract($data['doctor_id'], $data['fecha'])) {
            $errors[] = 'El doctor no tiene un contrato activo para esa fecha';
        }

        // Validar horario disponible
        if (!$this->validateWithinSchedule(
            $data['doctor_id'],
            $data['fecha'],
            $data['hora_inicio'],
            $data['hora_fin']
        )) {
            $errors[] = 'El turno no está dentro del horario disponible del doctor';
        }

        return $errors;
    }

    /**
     * Calcular hora de fin basada en duración
     */
    public function calculateEndTime(string $horaInicio, int $durationMinutes): string
    {
        return Carbon::parse($horaInicio)->addMinutes($durationMinutes)->format('H:i:s');
    }

    /**
     * Obtener slots disponibles para un doctor en una fecha
     */
    public function getAvailableSlots(int $doctorId, string $fecha): array
    {
        $doctor = Doctor::with(['horarios', 'turnos' => function ($query) use ($fecha) {
            $query->where('fecha', $fecha)->where('estado', '!=', 'cancelado');
        }])->find($doctorId);

        if (!$doctor) {
            return [];
        }

        $fechaCarbon = Carbon::parse($fecha);
        $dayOfWeek = $fechaCarbon->dayOfWeek;

        $schedule = $doctor->horarios()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();

        if (!$schedule) {
            return [];
        }

        $availableSlots = [];
        $slots = $schedule->getTimeSlots();
        $bookedTimes = $doctor->turnos->pluck('hora_inicio')->toArray();

        foreach ($slots as $slot) {
            if (!in_array($slot['start'], $bookedTimes)) {
                $availableSlots[] = $slot;
            }
        }

        return $availableSlots;
    }
}
