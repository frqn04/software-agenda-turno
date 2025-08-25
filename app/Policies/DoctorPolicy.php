<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Doctor;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Policy para el modelo Doctor
 * Maneja la autorización para operaciones de doctores
 */
class DoctorPolicy
{
    use HandlesAuthorization;

    /**
     * Determinar si el usuario puede ver cualquier doctor
     */
    public function viewAny(User $user): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin', 'doctor', 'recepcionista']);
    }

    /**
     * Determinar si el usuario puede ver un doctor específico
     */
    public function view(User $user, Doctor $doctor): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Administradores pueden ver todos los doctores
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor puede ver sus propios datos
        if ($user->role === 'doctor' && $user->doctor) {
            return $user->doctor->id === $doctor->id;
        }

        // Recepcionista puede ver doctores activos
        if ($user->role === 'recepcionista') {
            return $doctor->is_active;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede crear doctores
     */
    public function create(User $user): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede actualizar un doctor
     */
    public function update(User $user, Doctor $doctor): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Administradores pueden modificar cualquier doctor
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor puede actualizar algunos de sus propios datos
        if ($user->role === 'doctor' && $user->doctor) {
            return $user->doctor->id === $doctor->id;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede eliminar un doctor
     */
    public function delete(User $user, Doctor $doctor): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Solo administradores pueden eliminar doctores
        if (!in_array($user->role, ['administrador', 'super_admin'])) {
            return false;
        }

        // Verificar que no tenga turnos futuros o en curso
        return !$this->doctorHasActiveTurnos($doctor);
    }

    /**
     * Determinar si el usuario puede eliminar permanentemente un doctor
     */
    public function forceDelete(User $user, Doctor $doctor): bool
    {
        return $this->isActiveUser($user) && 
               $user->role === 'super_admin';
    }

    /**
     * Determinar si el usuario puede restaurar un doctor
     */
    public function restore(User $user, Doctor $doctor): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede ver los horarios del doctor
     */
    public function viewSchedule(User $user, Doctor $doctor): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Administradores pueden ver todos los horarios
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor puede ver sus propios horarios
        if ($user->role === 'doctor' && $user->doctor) {
            return $user->doctor->id === $doctor->id;
        }

        // Recepcionista puede ver horarios de doctores activos
        if ($user->role === 'recepcionista') {
            return $doctor->is_active;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede gestionar los horarios del doctor
     */
    public function manageSchedule(User $user, Doctor $doctor): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Administradores pueden gestionar todos los horarios
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor puede gestionar sus propios horarios si tiene contrato activo
        if ($user->role === 'doctor' && $user->doctor) {
            return $user->doctor->id === $doctor->id && 
                   $this->doctorHasActiveContract($doctor);
        }

        return false;
    }

    /**
     * Determinar si el usuario puede ver los contratos del doctor
     */
    public function viewContracts(User $user, Doctor $doctor): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Administradores pueden ver todos los contratos
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor puede ver sus propios contratos
        if ($user->role === 'doctor' && $user->doctor) {
            return $user->doctor->id === $doctor->id;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede gestionar los contratos del doctor
     */
    public function manageContracts(User $user, Doctor $doctor): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede ver las estadísticas del doctor
     */
    public function viewStatistics(User $user, Doctor $doctor): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Administradores pueden ver todas las estadísticas
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor puede ver sus propias estadísticas
        if ($user->role === 'doctor' && $user->doctor) {
            return $user->doctor->id === $doctor->id;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede activar/desactivar el doctor
     */
    public function toggleStatus(User $user, Doctor $doctor): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede ver la agenda del doctor
     */
    public function viewAgenda(User $user, Doctor $doctor): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Administradores pueden ver todas las agendas
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor puede ver su propia agenda
        if ($user->role === 'doctor' && $user->doctor) {
            return $user->doctor->id === $doctor->id;
        }

        // Recepcionista puede ver agendas de doctores activos
        if ($user->role === 'recepcionista') {
            return $doctor->is_active;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede asignar especialidades al doctor
     */
    public function assignSpecialty(User $user, Doctor $doctor): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede ver las evoluciones del doctor
     */
    public function viewEvolutions(User $user, Doctor $doctor): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Administradores pueden ver todas las evoluciones
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor puede ver sus propias evoluciones
        if ($user->role === 'doctor' && $user->doctor) {
            return $user->doctor->id === $doctor->id;
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
     * Verificar si el doctor tiene turnos activos
     */
    private function doctorHasActiveTurnos(Doctor $doctor): bool
    {
        return $doctor->turnos()
            ->where('fecha', '>=', now()->toDateString())
            ->whereIn('estado', ['programado', 'confirmado', 'en_curso'])
            ->exists();
    }

    /**
     * Verificar si el doctor tiene contrato activo
     */
    private function doctorHasActiveContract(Doctor $doctor): bool
    {
        return $doctor->contratoActivo()->exists();
    }
}
