<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Turno;
use App\Models\Doctor;
use App\Models\Paciente;
use Illuminate\Auth\Access\HandlesAuthorization;
use Carbon\Carbon;

/**
 * Policy para el modelo Turno (Appointment)
 * Maneja la autorización para operaciones de turnos médicos
 */
class AppointmentPolicy
{
    use HandlesAuthorization;

    /**
     * Determinar si el usuario puede ver cualquier turno
     */
    public function viewAny(User $user): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin', 'doctor', 'recepcionista']);
    }

    /**
     * Determinar si el usuario puede ver un turno específico
     */
    public function view(User $user, Turno $turno): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Administradores pueden ver todos los turnos
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor puede ver sus propios turnos
        if ($user->role === 'doctor' && $user->doctor) {
            return $turno->doctor_id === $user->doctor->id;
        }

        // Recepcionista puede ver todos los turnos
        if ($user->role === 'recepcionista') {
            return true;
        }

        // Paciente puede ver solo sus propios turnos
        if ($user->role === 'paciente' && $user->paciente) {
            return $turno->paciente_id === $user->paciente->id;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede crear turnos
     */
    public function create(User $user): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        return in_array($user->role, ['administrador', 'super_admin', 'recepcionista']);
    }

    /**
     * Determinar si el usuario puede actualizar un turno
     */
    public function update(User $user, Turno $turno): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Verificar que el turno sea modificable
        if (!$this->isTurnoModifiable($turno)) {
            return false;
        }

        // Administradores pueden modificar cualquier turno
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor puede modificar sus turnos si están en estado modificable
        if ($user->role === 'doctor' && $user->doctor) {
            return $turno->doctor_id === $user->doctor->id && 
                   in_array($turno->estado, [Turno::ESTADO_PROGRAMADO, Turno::ESTADO_CONFIRMADO]);
        }

        // Recepcionista puede modificar turnos en estado programado
        if ($user->role === 'recepcionista') {
            return $turno->estado === Turno::ESTADO_PROGRAMADO;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede eliminar un turno
     */
    public function delete(User $user, Turno $turno): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Solo administradores pueden eliminar definitivamente
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Recepcionista puede cancelar turnos programados
        if ($user->role === 'recepcionista') {
            return $turno->estado === Turno::ESTADO_PROGRAMADO;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede eliminar permanentemente un turno
     */
    public function forceDelete(User $user, Turno $turno): bool
    {
        return $this->isActiveUser($user) && 
               $user->role === 'super_admin';
    }

    /**
     * Determinar si el usuario puede restaurar un turno
     */
    public function restore(User $user, Turno $turno): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede cancelar un turno
     */
    public function cancel(User $user, Turno $turno): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Verificar que el turno sea cancelable
        if (!$this->isTurnoCancelable($turno)) {
            return false;
        }

        // Administradores pueden cancelar cualquier turno
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor puede cancelar sus turnos
        if ($user->role === 'doctor' && $user->doctor) {
            return $turno->doctor_id === $user->doctor->id;
        }

        // Recepcionista puede cancelar turnos
        if ($user->role === 'recepcionista') {
            return true;
        }

        // Paciente puede cancelar sus propios turnos con tiempo suficiente
        if ($user->role === 'paciente' && $user->paciente) {
            return $turno->paciente_id === $user->paciente->id && 
                   $this->canPatientCancel($turno);
        }

        return false;
    }

    /**
     * Determinar si el usuario puede completar un turno
     */
    public function complete(User $user, Turno $turno): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Solo doctores y administradores pueden completar turnos
        if ($user->role === 'doctor' && $user->doctor) {
            return $turno->doctor_id === $user->doctor->id && 
                   $turno->estado === Turno::ESTADO_EN_CURSO;
        }

        return in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede reprogramar un turno
     */
    public function reschedule(User $user, Turno $turno): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Verificar que el turno sea reprogramable
        if (!$this->isTurnoReschedulable($turno)) {
            return false;
        }

        // Administradores pueden reprogramar cualquier turno
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor puede reprogramar sus turnos
        if ($user->role === 'doctor' && $user->doctor) {
            return $turno->doctor_id === $user->doctor->id;
        }

        // Recepcionista puede reprogramar turnos
        if ($user->role === 'recepcionista') {
            return true;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede confirmar un turno
     */
    public function confirm(User $user, Turno $turno): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Solo se pueden confirmar turnos programados
        if ($turno->estado !== Turno::ESTADO_PROGRAMADO) {
            return false;
        }

        // Administradores y recepcionistas pueden confirmar
        if (in_array($user->role, ['administrador', 'super_admin', 'recepcionista'])) {
            return true;
        }

        // Doctor puede confirmar sus propios turnos
        if ($user->role === 'doctor' && $user->doctor) {
            return $turno->doctor_id === $user->doctor->id;
        }

        // Paciente puede confirmar su propio turno
        if ($user->role === 'paciente' && $user->paciente) {
            return $turno->paciente_id === $user->paciente->id;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede marcar un turno como "no asistió"
     */
    public function markNoShow(User $user, Turno $turno): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Solo se puede marcar como no asistió si está confirmado y ya pasó la hora
        if ($turno->estado !== Turno::ESTADO_CONFIRMADO) {
            return false;
        }

        $fechaHoraTurno = Carbon::parse("{$turno->fecha} {$turno->hora}");
        if (!$fechaHoraTurno->isPast()) {
            return false;
        }

        // Doctor puede marcar sus turnos como no asistió
        if ($user->role === 'doctor' && $user->doctor) {
            return $turno->doctor_id === $user->doctor->id;
        }

        // Administradores y recepcionistas pueden marcar cualquier turno
        return in_array($user->role, ['administrador', 'super_admin', 'recepcionista']);
    }

    /**
     * Determinar si el usuario puede ver la historia clínica relacionada
     */
    public function viewMedicalHistory(User $user, Turno $turno): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Administradores pueden ver cualquier historia
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor puede ver la historia de sus pacientes
        if ($user->role === 'doctor' && $user->doctor) {
            return $turno->doctor_id === $user->doctor->id;
        }

        return false;
    }

    /**
     * Verificar si el usuario está activo
     */
    private function isActiveUser(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Verificar si el turno es modificable
     */
    private function isTurnoModifiable(Turno $turno): bool
    {
        $estadosModificables = [
            Turno::ESTADO_PROGRAMADO,
            Turno::ESTADO_CONFIRMADO,
        ];

        return in_array($turno->estado, $estadosModificables);
    }

    /**
     * Verificar si el turno es cancelable
     */
    private function isTurnoCancelable(Turno $turno): bool
    {
        $estadosCancelables = [
            Turno::ESTADO_PROGRAMADO,
            Turno::ESTADO_CONFIRMADO,
        ];

        return in_array($turno->estado, $estadosCancelables);
    }

    /**
     * Verificar si el turno es reprogramable
     */
    private function isTurnoReschedulable(Turno $turno): bool
    {
        $estadosReprogramables = [
            Turno::ESTADO_PROGRAMADO,
            Turno::ESTADO_CONFIRMADO,
        ];

        return in_array($turno->estado, $estadosReprogramables);
    }

    /**
     * Verificar si un paciente puede cancelar el turno
     * (debe ser con al menos 2 horas de anticipación)
     */
    private function canPatientCancel(Turno $turno): bool
    {
        $fechaHoraTurno = Carbon::parse("{$turno->fecha} {$turno->hora}");
        $horasAnticipacion = now()->diffInHours($fechaHoraTurno, false);

        return $horasAnticipacion >= 2; // Al menos 2 horas de anticipación
    }
}
