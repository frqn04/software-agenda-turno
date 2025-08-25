<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Especialidad;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Policy para el modelo Especialidad
 * Maneja la autorización para operaciones de especialidades médicas
 */
class EspecialidadPolicy
{
    use HandlesAuthorization;

    /**
     * Determinar si el usuario puede ver cualquier especialidad
     */
    public function viewAny(User $user): bool
    {
        // Todos los usuarios activos pueden ver especialidades
        return $this->isActiveUser($user);
    }

    /**
     * Determinar si el usuario puede ver una especialidad específica
     */
    public function view(User $user, Especialidad $especialidad): bool
    {
        // Todos los usuarios activos pueden ver especialidades
        return $this->isActiveUser($user);
    }

    /**
     * Determinar si el usuario puede crear especialidades
     */
    public function create(User $user): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Solo administradores pueden crear nuevas especialidades
        return in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede actualizar una especialidad
     */
    public function update(User $user, Especialidad $especialidad): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Solo administradores pueden modificar especialidades
        return in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede eliminar una especialidad
     */
    public function delete(User $user, Especialidad $especialidad): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Verificar que no tenga doctores asociados
        if ($this->hasAssociatedDoctors($especialidad)) {
            return false;
        }

        // Solo administradores pueden eliminar especialidades
        return in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede restaurar una especialidad
     */
    public function restore(User $user, Especialidad $especialidad): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede eliminar permanentemente una especialidad
     */
    public function forceDelete(User $user, Especialidad $especialidad): bool
    {
        return $this->isActiveUser($user) && 
               $user->role === 'super_admin' &&
               !$this->hasAssociatedDoctors($especialidad);
    }

    /**
     * Determinar si el usuario puede asociar doctores a la especialidad
     */
    public function associateDoctors(User $user, Especialidad $especialidad): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        return in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede ver estadísticas de la especialidad
     */
    public function viewStatistics(User $user, Especialidad $especialidad): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Administradores pueden ver todas las estadísticas
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctores pueden ver estadísticas de su especialidad
        if ($user->role === 'doctor' && $user->doctor) {
            return $user->doctor->especialidad_id === $especialidad->id;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede gestionar horarios de la especialidad
     */
    public function manageSchedules(User $user, Especialidad $especialidad): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        return in_array($user->role, ['administrador', 'super_admin', 'recepcionista']);
    }

    /**
     * Verificar si el usuario está activo
     */
    private function isActiveUser(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Verificar si la especialidad tiene doctores asociados
     */
    private function hasAssociatedDoctors(Especialidad $especialidad): bool
    {
        return $especialidad->doctors()->exists();
    }
}
