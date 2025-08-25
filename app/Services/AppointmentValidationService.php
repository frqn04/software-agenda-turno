<?php

namespace App\Services;

use App\Models\Turno;
use App\Models\Doctor;
use App\Models\DoctorScheduleSlot;
use App\Models\Paciente;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de validación integral para citas médicas
 * Maneja todas las validaciones de negocio para el sistema de turnos
 */
class AppointmentValidationService
{
    private const CACHE_TTL = 900; // 15 minutos
    private const MIN_APPOINTMENT_DURATION = 15; // minutos
    private const MAX_APPOINTMENT_DURATION = 180; // 3 horas
    private const BUFFER_TIME = 5; // tiempo de buffer entre citas

    /**
     * Validar que no haya overlap de turnos para un doctor
     */
    public function validateNoOverlap(int $doctorId, string $fecha, string $horaInicio, string $horaFin, ?int $excludeAppointmentId = null): bool
    {
        $cacheKey = "doctor_overlaps_{$doctorId}_{$fecha}_{$horaInicio}_{$horaFin}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($doctorId, $fecha, $horaInicio, $horaFin, $excludeAppointmentId) {
            $horaInicioCarbon = Carbon::parse($horaInicio);
            $horaFinCarbon = Carbon::parse($horaFin);
            
            // Agregar tiempo de buffer
            $horaInicioBuffer = $horaInicioCarbon->copy()->subMinutes(self::BUFFER_TIME);
            $horaFinBuffer = $horaFinCarbon->copy()->addMinutes(self::BUFFER_TIME);

            $query = Turno::where('doctor_id', $doctorId)
                ->where('fecha', $fecha)
                ->whereIn('estado', ['programado', 'confirmado', 'en_curso'])
                ->where(function ($q) use ($horaInicioBuffer, $horaFinBuffer) {
                    // Verificar solapamiento con buffer
                    $q->where(function ($subQ) use ($horaInicioBuffer, $horaFinBuffer) {
                        $subQ->where('hora_inicio', '<', $horaFinBuffer->format('H:i:s'))
                             ->where('hora_fin', '>', $horaInicioBuffer->format('H:i:s'));
                    });
                });

            if ($excludeAppointmentId) {
                $query->where('id', '!=', $excludeAppointmentId);
            }

            return $query->count() === 0;
        });
    }

    /**
     * Validar que el turno esté dentro del contrato activo del doctor
     */
    public function validateWithinContract(int $doctorId, string $fecha): bool
    {
        $cacheKey = "doctor_contract_validation_{$doctorId}_{$fecha}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($doctorId, $fecha) {
            $doctor = Doctor::with(['contratos' => function ($query) {
                $query->where('is_active', true);
            }])->find($doctorId);
            
            if (!$doctor) {
                return false;
            }

            $fechaCarbon = Carbon::parse($fecha);
            
            return $doctor->contratos()
                ->where('is_active', true)
                ->where('fecha_inicio', '<=', $fechaCarbon)
                ->where(function ($query) use ($fechaCarbon) {
                    $query->whereNull('fecha_fin')
                          ->orWhere('fecha_fin', '>=', $fechaCarbon);
                })
                ->exists();
        });
    }

    /**
     * Validar que el turno esté dentro del horario disponible del doctor
     */
    public function validateWithinSchedule(int $doctorId, string $fecha, string $horaInicio, string $horaFin): bool
    {
        $cacheKey = "doctor_schedule_validation_{$doctorId}_{$fecha}_{$horaInicio}_{$horaFin}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($doctorId, $fecha, $horaInicio, $horaFin) {
            $doctor = Doctor::with(['horarios' => function ($query) {
                $query->where('is_active', true);
            }])->find($doctorId);
            
            if (!$doctor) {
                return false;
            }

            $fechaCarbon = Carbon::parse($fecha);
            $dayOfWeek = $fechaCarbon->dayOfWeek;
            
            $horaInicioCarbon = Carbon::parse($horaInicio);
            $horaFinCarbon = Carbon::parse($horaFin);

            // Verificar si existe un horario válido para ese día
            $schedule = $doctor->horarios()
                ->where('day_of_week', $dayOfWeek)
                ->where('is_active', true)
                ->where('start_time', '<=', $horaInicioCarbon->format('H:i:s'))
                ->where('end_time', '>=', $horaFinCarbon->format('H:i:s'))
                ->first();

            if (!$schedule) {
                return false;
            }

            // Verificar excepciones de horario (días no laborables, vacaciones, etc.)
            return !$this->hasScheduleException($doctorId, $fechaCarbon);
        });
    }

    /**
     * Validar duración de la cita
     */
    public function validateAppointmentDuration(string $horaInicio, string $horaFin): bool
    {
        $inicio = Carbon::parse($horaInicio);
        $fin = Carbon::parse($horaFin);
        $duration = $fin->diffInMinutes($inicio);

        return $duration >= self::MIN_APPOINTMENT_DURATION && 
               $duration <= self::MAX_APPOINTMENT_DURATION;
    }

    /**
     * Validar que la fecha de la cita sea válida
     */
    public function validateAppointmentDate(string $fecha): array
    {
        $errors = [];
        $fechaCarbon = Carbon::parse($fecha);
        $today = Carbon::today();

        // No puede ser en el pasado
        if ($fechaCarbon->lt($today)) {
            $errors[] = 'La fecha no puede ser anterior a hoy';
        }

        // No puede ser más de 6 meses en el futuro
        if ($fechaCarbon->gt($today->copy()->addMonths(6))) {
            $errors[] = 'La fecha no puede ser más de 6 meses en el futuro';
        }

        // No puede ser domingo (ajustar según reglas de negocio)
        if ($fechaCarbon->isSunday()) {
            $errors[] = 'No se pueden programar citas los domingos';
        }

        return $errors;
    }

    /**
     * Validar límites de citas por paciente
     */
    public function validatePatientAppointmentLimits(int $pacienteId, string $fecha): array
    {
        $errors = [];
        $fechaCarbon = Carbon::parse($fecha);

        // Máximo 3 citas por día por paciente
        $citasDelDia = Turno::where('paciente_id', $pacienteId)
            ->where('fecha', $fecha)
            ->whereIn('estado', ['programado', 'confirmado'])
            ->count();

        if ($citasDelDia >= 3) {
            $errors[] = 'El paciente ya tiene el máximo de citas permitidas para este día';
        }

        // Máximo 10 citas por mes por paciente
        $citasDelMes = Turno::where('paciente_id', $pacienteId)
            ->whereYear('fecha', $fechaCarbon->year)
            ->whereMonth('fecha', $fechaCarbon->month)
            ->whereIn('estado', ['programado', 'confirmado'])
            ->count();

        if ($citasDelMes >= 10) {
            $errors[] = 'El paciente ya tiene el máximo de citas permitidas para este mes';
        }

        return $errors;
    }

    /**
     * Verificar si el doctor tiene excepciones de horario
     */
    private function hasScheduleException(int $doctorId, Carbon $fecha): bool
    {
        // Aquí se pueden agregar validaciones para:
        // - Días de vacaciones del doctor
        // - Días feriados
        // - Ausencias programadas
        // - Reuniones o eventos especiales
        
        return false; // Por defecto, no hay excepciones
    }

    /**
     * Validar todas las reglas de negocio para un turno
     */
    public function validateAppointment(array $data, ?int $excludeAppointmentId = null): array
    {
        $errors = [];

        // Validar fecha
        $dateErrors = $this->validateAppointmentDate($data['fecha']);
        $errors = array_merge($errors, $dateErrors);

        // Validar duración
        if (!$this->validateAppointmentDuration($data['hora_inicio'], $data['hora_fin'])) {
            $errors[] = 'La duración de la cita debe estar entre ' . self::MIN_APPOINTMENT_DURATION . ' y ' . self::MAX_APPOINTMENT_DURATION . ' minutos';
        }

        // Validar overlap
        if (!$this->validateNoOverlap(
            $data['doctor_id'],
            $data['fecha'],
            $data['hora_inicio'],
            $data['hora_fin'],
            $excludeAppointmentId
        )) {
            $errors[] = 'El doctor ya tiene un turno en ese horario (incluyendo tiempo de buffer)';
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

        // Validar límites del paciente
        if (isset($data['paciente_id'])) {
            $patientLimitErrors = $this->validatePatientAppointmentLimits($data['paciente_id'], $data['fecha']);
            $errors = array_merge($errors, $patientLimitErrors);
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
    public function getAvailableSlots(int $doctorId, string $fecha, int $durationMinutes = 30): array
    {
        $cacheKey = "available_slots_{$doctorId}_{$fecha}_{$durationMinutes}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($doctorId, $fecha, $durationMinutes) {
            $doctor = Doctor::with(['horarios', 'turnos' => function ($query) use ($fecha) {
                $query->where('fecha', $fecha)
                      ->whereIn('estado', ['programado', 'confirmado', 'en_curso']);
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
            $startTime = Carbon::parse($schedule->start_time);
            $endTime = Carbon::parse($schedule->end_time);
            $bookedSlots = $doctor->turnos->map(function ($turno) {
                return [
                    'start' => Carbon::parse($turno->hora_inicio),
                    'end' => Carbon::parse($turno->hora_fin),
                ];
            });

            $currentSlot = $startTime->copy();
            
            while ($currentSlot->copy()->addMinutes($durationMinutes)->lte($endTime)) {
                $slotEnd = $currentSlot->copy()->addMinutes($durationMinutes);
                
                $isAvailable = true;
                foreach ($bookedSlots as $bookedSlot) {
                    // Verificar overlap con buffer time
                    if ($currentSlot->lt($bookedSlot['end']->addMinutes(self::BUFFER_TIME)) && 
                        $slotEnd->gt($bookedSlot['start']->subMinutes(self::BUFFER_TIME))) {
                        $isAvailable = false;
                        break;
                    }
                }

                if ($isAvailable) {
                    $availableSlots[] = [
                        'start' => $currentSlot->format('H:i'),
                        'end' => $slotEnd->format('H:i'),
                        'duration' => $durationMinutes,
                    ];
                }

                $currentSlot->addMinutes(15); // Slots cada 15 minutos
            }

            return $availableSlots;
        });
    }

    /**
     * Validar y sugerir horarios alternativos
     */
    public function suggestAlternativeSlots(int $doctorId, string $fecha, string $horaInicio, int $durationMinutes = 30): array
    {
        $availableSlots = $this->getAvailableSlots($doctorId, $fecha, $durationMinutes);
        $requestedTime = Carbon::parse($horaInicio);
        
        // Filtrar slots dentro de 2 horas de la hora solicitada
        return array_filter($availableSlots, function ($slot) use ($requestedTime) {
            $slotTime = Carbon::parse($slot['start']);
            return abs($slotTime->diffInMinutes($requestedTime)) <= 120;
        });
    }

    /**
     * Limpiar cache de validaciones
     */
    public function clearValidationCache(int $doctorId, string $fecha): void
    {
        $patterns = [
            "doctor_overlaps_{$doctorId}_{$fecha}_*",
            "doctor_contract_validation_{$doctorId}_{$fecha}",
            "doctor_schedule_validation_{$doctorId}_{$fecha}_*",
            "available_slots_{$doctorId}_{$fecha}_*"
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }
}
