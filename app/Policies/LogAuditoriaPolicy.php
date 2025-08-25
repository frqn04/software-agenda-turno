<?php

namespace App\Policies;

use App\Models\User;
use App\Models\LogAuditoria;
use Illuminate\Auth\Access\HandlesAuthorization;
use Carbon\Carbon;

/**
 * Policy para el modelo LogAuditoria
 * Maneja la autorización para operaciones de logs de auditoría
 * Los logs de auditoría son críticos para la seguridad y compliance
 */
class LogAuditoriaPolicy
{
    use HandlesAuthorization;

    /**
     * Determinar si el usuario puede ver cualquier log de auditoría
     */
    public function viewAny(User $user): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede ver un log específico
     */
    public function view(User $user, LogAuditoria $log): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Solo administradores pueden ver logs de auditoría
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor puede ver sus propios logs (limitado)
        if ($user->role === 'doctor' && $user->doctor) {
            return $this->canDoctorViewLog($user, $log);
        }

        return false;
    }

    /**
     * Determinar si el usuario puede crear logs de auditoría
     * Nota: Los logs se crean automáticamente por el sistema
     */
    public function create(User $user): bool
    {
        // Los logs de auditoría se crean automáticamente por el sistema
        // Solo el sistema puede crear logs directamente
        return false;
    }

    /**
     * Determinar si el usuario puede actualizar un log
     * Nota: Los logs de auditoría son inmutables
     */
    public function update(User $user, LogAuditoria $log): bool
    {
        // Los logs de auditoría son inmutables por diseño
        return false;
    }

    /**
     * Determinar si el usuario puede eliminar un log
     * Nota: Los logs solo pueden eliminarse según políticas de retención
     */
    public function delete(User $user, LogAuditoria $log): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Solo super_admin puede eliminar logs según políticas de retención
        return $user->role === 'super_admin' && 
               $this->isLogEligibleForDeletion($log);
    }

    /**
     * Determinar si el usuario puede restaurar un log
     */
    public function restore(User $user, LogAuditoria $log): bool
    {
        return $this->isActiveUser($user) && 
               $user->role === 'super_admin';
    }

    /**
     * Determinar si el usuario puede eliminar permanentemente un log
     */
    public function forceDelete(User $user, LogAuditoria $log): bool
    {
        return $this->isActiveUser($user) && 
               $user->role === 'super_admin' &&
               $this->isLogEligibleForDeletion($log);
    }

    /**
     * Determinar si el usuario puede exportar logs de auditoría
     */
    public function export(User $user): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede ver logs de un usuario específico
     */
    public function viewUserLogs(User $user, int $targetUserId): bool
    {
        if (!$this->viewAny($user)) {
            return false;
        }

        // Administradores pueden ver logs de cualquier usuario
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede ver logs por tipo de acción
     */
    public function viewByAction(User $user, string $action): bool
    {
        if (!$this->viewAny($user)) {
            return false;
        }

        // Administradores pueden filtrar por cualquier acción
        return in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede ver logs de seguridad críticos
     */
    public function viewSecurityLogs(User $user): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede generar reportes de auditoría
     */
    public function generateReports(User $user): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede configurar alertas de auditoría
     */
    public function configureAlerts(User $user): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede ver logs de acceso médico
     */
    public function viewMedicalAccessLogs(User $user): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede archivar logs antiguos
     */
    public function archiveOldLogs(User $user): bool
    {
        return $this->isActiveUser($user) && 
               $user->role === 'super_admin';
    }

    /**
     * Determinar si el usuario puede purgar logs según retención
     */
    public function purgeExpiredLogs(User $user): bool
    {
        return $this->isActiveUser($user) && 
               $user->role === 'super_admin';
    }

    /**
     * Verificar si el usuario está activo
     */
    private function isActiveUser(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Verificar si el doctor puede ver el log específico
     */
    private function canDoctorViewLog(User $user, LogAuditoria $log): bool
    {
        // Doctor solo puede ver logs relacionados con sus propias acciones
        // y que no sean de seguridad crítica
        if ($log->user_id !== $user->id) {
            return false;
        }

        // Excluir logs de seguridad crítica
        $criticalActions = [
            'login_failed',
            'unauthorized_access',
            'permission_denied',
            'security_violation'
        ];

        return !in_array($log->action, $criticalActions);
    }

    /**
     * Verificar si el log es elegible para eliminación según políticas de retención
     */
    private function isLogEligibleForDeletion(LogAuditoria $log): bool
    {
        // Política de retención: logs mayores a 7 años pueden eliminarse
        // excepto logs de seguridad crítica que se mantienen 10 años
        $retentionYears = $this->isCriticalSecurityLog($log) ? 10 : 7;
        $retentionDate = now()->subYears($retentionYears);

        return Carbon::parse($log->created_at)->lt($retentionDate);
    }

    /**
     * Verificar si es un log de seguridad crítica
     */
    private function isCriticalSecurityLog(LogAuditoria $log): bool
    {
        $criticalActions = [
            'login_failed',
            'unauthorized_access',
            'permission_denied',
            'security_violation',
            'data_breach_attempt',
            'admin_privilege_escalation',
            'medical_data_unauthorized_access'
        ];

        return in_array($log->action, $criticalActions);
    }
}
