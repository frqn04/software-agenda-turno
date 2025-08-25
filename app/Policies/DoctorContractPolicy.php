<?php

namespace App\Policies;

use App\Models\User;
use App\Models\DoctorContract;
use Illuminate\Auth\Access\HandlesAuthorization;
use Carbon\Carbon;

/**
 * Policy para el modelo DoctorContract
 * Maneja la autorización para operaciones de contratos médicos
 * Los contratos son documentos sensibles que requieren protección especial
 */
class DoctorContractPolicy
{
    use HandlesAuthorization;

    /**
     * Determinar si el usuario puede ver cualquier contrato
     */
    public function viewAny(User $user): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin', 'recepcionista']);
    }

    /**
     * Determinar si el usuario puede ver un contrato específico
     */
    public function view(User $user, DoctorContract $contract): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Administradores pueden ver todos los contratos
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Recepcionista puede ver información básica de contratos
        if ($user->role === 'recepcionista') {
            return $this->canReceptionistViewContract($contract);
        }

        // Doctor puede ver solo su propio contrato
        if ($user->role === 'doctor' && $user->doctor) {
            return $contract->doctor_id === $user->doctor->id;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede crear contratos
     */
    public function create(User $user): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Solo administradores pueden crear contratos médicos
        return in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede actualizar un contrato
     */
    public function update(User $user, DoctorContract $contract): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Verificar que el contrato sea modificable
        if (!$this->isContractModifiable($contract)) {
            return false;
        }

        // Solo administradores pueden modificar contratos
        return in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede eliminar un contrato
     */
    public function delete(User $user, DoctorContract $contract): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Verificar que no haya turnos asociados al contrato
        if ($this->hasAssociatedAppointments($contract)) {
            return false;
        }

        // Solo administradores pueden eliminar contratos
        return in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede restaurar un contrato
     */
    public function restore(User $user, DoctorContract $contract): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede eliminar permanentemente un contrato
     */
    public function forceDelete(User $user, DoctorContract $contract): bool
    {
        return $this->isActiveUser($user) && 
               $user->role === 'super_admin' &&
               !$this->hasAssociatedAppointments($contract);
    }

    /**
     * Determinar si el usuario puede activar/desactivar un contrato
     */
    public function toggleStatus(User $user, DoctorContract $contract): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Solo administradores pueden cambiar el estado de contratos
        return in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede ver detalles financieros del contrato
     */
    public function viewFinancialDetails(User $user, DoctorContract $contract): bool
    {
        if (!$this->view($user, $contract)) {
            return false;
        }

        // Solo administradores pueden ver detalles financieros
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor puede ver los detalles básicos de su propio contrato
        if ($user->role === 'doctor' && $user->doctor) {
            return $contract->doctor_id === $user->doctor->id;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede renovar un contrato
     */
    public function renew(User $user, DoctorContract $contract): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Verificar que el contrato sea renovable
        if (!$this->isContractRenewable($contract)) {
            return false;
        }

        // Solo administradores pueden renovar contratos
        return in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede terminar un contrato
     */
    public function terminate(User $user, DoctorContract $contract): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Verificar que el contrato esté activo
        if (!$contract->is_active) {
            return false;
        }

        // Solo administradores pueden terminar contratos
        return in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede generar reportes del contrato
     */
    public function generateReports(User $user, DoctorContract $contract): bool
    {
        if (!$this->view($user, $contract)) {
            return false;
        }

        return in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede adjuntar documentos al contrato
     */
    public function attachDocuments(User $user, DoctorContract $contract): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Solo administradores pueden adjuntar documentos
        return in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Verificar si el usuario está activo
     */
    private function isActiveUser(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Verificar si el contrato es modificable
     */
    private function isContractModifiable(DoctorContract $contract): bool
    {
        // No se puede modificar si ya está vencido
        if ($contract->end_date && Carbon::parse($contract->end_date)->isPast()) {
            return false;
        }

        // No se puede modificar si está terminado
        if ($contract->status === 'terminated') {
            return false;
        }

        return true;
    }

    /**
     * Verificar si el contrato es renovable
     */
    private function isContractRenewable(DoctorContract $contract): bool
    {
        // Solo se puede renovar si está activo o próximo a vencer
        if (!$contract->is_active) {
            return false;
        }

        // Debe estar cerca de la fecha de vencimiento (30 días)
        if ($contract->end_date) {
            $diasParaVencimiento = Carbon::parse($contract->end_date)->diffInDays(now(), false);
            return $diasParaVencimiento <= 30;
        }

        return false;
    }

    /**
     * Verificar si la recepcionista puede ver el contrato
     */
    private function canReceptionistViewContract(DoctorContract $contract): bool
    {
        // Recepcionista solo puede ver información básica de contratos activos
        return $contract->is_active;
    }

    /**
     * Verificar si el contrato tiene turnos asociados
     */
    private function hasAssociatedAppointments(DoctorContract $contract): bool
    {
        return \App\Models\Turno::where('doctor_id', $contract->doctor_id)
                               ->whereBetween('fecha', [$contract->start_date, $contract->end_date ?? now()])
                               ->exists();
    }
}
