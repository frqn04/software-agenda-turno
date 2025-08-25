<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Policy para el modelo User
 * Maneja la autorización para operaciones de gestión de usuarios
 * Incluye validaciones específicas para roles médicos y jerarquías administrativas
 */
class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determinar si el usuario puede ver cualquier usuario
     */
    public function viewAny(User $user): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin', 'recepcionista']);
    }

    /**
     * Determinar si el usuario puede ver un usuario específico
     */
    public function view(User $authenticatedUser, User $targetUser): bool
    {
        if (!$this->isActiveUser($authenticatedUser)) {
            return false;
        }

        // Administradores pueden ver todos los usuarios
        if (in_array($authenticatedUser->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Recepcionista puede ver información básica de otros usuarios
        if ($authenticatedUser->role === 'recepcionista') {
            return $this->canReceptionistViewUser($targetUser);
        }

        // Doctor puede ver información básica de otros doctores y pacientes
        if ($authenticatedUser->role === 'doctor') {
            return $this->canDoctorViewUser($authenticatedUser, $targetUser);
        }

        // Usuarios pueden ver su propio perfil
        return $authenticatedUser->id === $targetUser->id;
    }

    /**
     * Determinar si el usuario puede crear usuarios
     */
    public function create(User $user): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        return in_array($user->role, ['administrador', 'super_admin', 'recepcionista']);
    }

    /**
     * Determinar si el usuario puede crear un usuario con un rol específico
     */
    public function createWithRole(User $user, string $targetRole): bool
    {
        if (!$this->create($user)) {
            return false;
        }

        // Super admin puede crear cualquier rol
        if ($user->role === 'super_admin') {
            return true;
        }

        // Administrador puede crear todos excepto super_admin
        if ($user->role === 'administrador') {
            return $targetRole !== 'super_admin';
        }

        // Recepcionista solo puede crear pacientes
        if ($user->role === 'recepcionista') {
            return $targetRole === 'paciente';
        }

        return false;
    }

    /**
     * Determinar si el usuario puede actualizar otro usuario
     */
    public function update(User $authenticatedUser, User $targetUser): bool
    {
        if (!$this->isActiveUser($authenticatedUser)) {
            return false;
        }

        // Super admin puede actualizar cualquier usuario
        if ($authenticatedUser->role === 'super_admin') {
            return true;
        }

        // Administrador puede actualizar usuarios excepto super_admin
        if ($authenticatedUser->role === 'administrador') {
            return $targetUser->role !== 'super_admin';
        }

        // Recepcionista puede actualizar datos básicos de pacientes
        if ($authenticatedUser->role === 'recepcionista') {
            return $targetUser->role === 'paciente';
        }

        // Usuarios pueden actualizar su propio perfil (con limitaciones)
        return $authenticatedUser->id === $targetUser->id;
    }

    /**
     * Determinar si el usuario puede actualizar información sensible
     */
    public function updateSensitiveData(User $authenticatedUser, User $targetUser): bool
    {
        if (!$this->update($authenticatedUser, $targetUser)) {
            return false;
        }

        // Solo administradores pueden modificar datos sensibles
        return in_array($authenticatedUser->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede eliminar otro usuario
     */
    public function delete(User $authenticatedUser, User $targetUser): bool
    {
        if (!$this->isActiveUser($authenticatedUser)) {
            return false;
        }

        // No se puede eliminar a sí mismo
        if ($authenticatedUser->id === $targetUser->id) {
            return false;
        }

        // Verificar que no sea el último administrador
        if ($this->isLastAdministrator($targetUser)) {
            return false;
        }

        // Super admin puede eliminar cualquier usuario (excepto otros super_admin)
        if ($authenticatedUser->role === 'super_admin') {
            return $targetUser->role !== 'super_admin';
        }

        // Administrador puede eliminar usuarios de menor jerarquía
        if ($authenticatedUser->role === 'administrador') {
            return !in_array($targetUser->role, ['administrador', 'super_admin']);
        }

        return false;
    }

    /**
     * Determinar si el usuario puede restaurar otro usuario
     */
    public function restore(User $user, User $targetUser): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede eliminar permanentemente otro usuario
     */
    public function forceDelete(User $authenticatedUser, User $targetUser): bool
    {
        return $this->isActiveUser($authenticatedUser) && 
               $authenticatedUser->role === 'super_admin' &&
               $authenticatedUser->id !== $targetUser->id;
    }

    /**
     * Determinar si el usuario puede cambiar el rol de otro usuario
     */
    public function changeRole(User $authenticatedUser, User $targetUser, string $newRole): bool
    {
        if (!$this->isActiveUser($authenticatedUser)) {
            return false;
        }

        // No se puede cambiar su propio rol
        if ($authenticatedUser->id === $targetUser->id) {
            return false;
        }

        // Verificar que no sea el último administrador
        if ($this->isLastAdministrator($targetUser) && $newRole !== 'administrador') {
            return false;
        }

        // Super admin puede cambiar cualquier rol
        if ($authenticatedUser->role === 'super_admin') {
            return true;
        }

        // Administrador puede cambiar roles de menor jerarquía
        if ($authenticatedUser->role === 'administrador') {
            return !in_array($targetUser->role, ['administrador', 'super_admin']) &&
                   !in_array($newRole, ['super_admin']);
        }

        return false;
    }

    /**
     * Determinar si el usuario puede activar/desactivar otro usuario
     */
    public function toggleStatus(User $authenticatedUser, User $targetUser): bool
    {
        if (!$this->isActiveUser($authenticatedUser)) {
            return false;
        }

        // No se puede desactivar a sí mismo
        if ($authenticatedUser->id === $targetUser->id) {
            return false;
        }

        // Verificar que no sea el último administrador activo
        if ($this->isLastActiveAdministrator($targetUser)) {
            return false;
        }

        return in_array($authenticatedUser->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede ver logs de auditoría
     */
    public function viewAuditLogs(User $user): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede gestionar el sistema
     */
    public function manageSystem(User $user): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede acceder a reportes
     */
    public function accessReports(User $user): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin', 'doctor']);
    }

    /**
     * Determinar si el usuario puede acceder a estadísticas avanzadas
     */
    public function viewAdvancedStatistics(User $user): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede gestionar permisos
     */
    public function managePermissions(User $user): bool
    {
        return $this->isActiveUser($user) && 
               $user->role === 'super_admin';
    }

    /**
     * Determinar si el usuario puede hacer backup del sistema
     */
    public function performBackup(User $user): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Verificar si el usuario está activo
     */
    private function isActiveUser(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Verificar si es el último administrador del sistema
     */
    private function isLastAdministrator(User $user): bool
    {
        if (!in_array($user->role, ['administrador', 'super_admin'])) {
            return false;
        }

        $adminCount = User::whereIn('role', ['administrador', 'super_admin'])
                         ->where('is_active', true)
                         ->count();

        return $adminCount <= 1;
    }

    /**
     * Verificar si es el último administrador activo
     */
    private function isLastActiveAdministrator(User $user): bool
    {
        if (!in_array($user->role, ['administrador', 'super_admin']) || !$user->is_active) {
            return false;
        }

        $activeAdminCount = User::whereIn('role', ['administrador', 'super_admin'])
                              ->where('is_active', true)
                              ->count();

        return $activeAdminCount <= 1;
    }

    /**
     * Verificar si la recepcionista puede ver el usuario
     */
    private function canReceptionistViewUser(User $targetUser): bool
    {
        // Recepcionista puede ver información básica de doctores y pacientes
        return in_array($targetUser->role, ['doctor', 'paciente']);
    }

    /**
     * Verificar si el doctor puede ver el usuario
     */
    private function canDoctorViewUser(User $doctor, User $targetUser): bool
    {
        // Doctor puede ver otros doctores y pacientes que ha atendido
        if ($targetUser->role === 'doctor') {
            return true; // Información básica de colegas
        }

        if ($targetUser->role === 'paciente' && $doctor->doctor && $targetUser->paciente) {
            // Verificar si el doctor ha atendido al paciente
            return \App\Models\Turno::where('doctor_id', $doctor->doctor->id)
                                   ->where('paciente_id', $targetUser->paciente->id)
                                   ->exists();
        }

        return false;
    }
}
