<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Evolucion;
use App\Models\HistoriaClinica;
use App\Models\Turno;
use Illuminate\Auth\Access\HandlesAuthorization;
use Carbon\Carbon;

/**
 * Policy para el modelo Evolucion
 * Maneja la autorización para operaciones de evoluciones médicas
 * Las evoluciones son registros críticos que requieren protección especial
 */
class EvolucionPolicy
{
    use HandlesAuthorization;

    /**
     * Determinar si el usuario puede ver cualquier evolución
     */
    public function viewAny(User $user): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin', 'doctor', 'recepcionista']);
    }

    /**
     * Determinar si el usuario puede ver una evolución específica
     */
    public function view(User $user, Evolucion $evolucion): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Administradores pueden ver todas las evoluciones
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor puede ver evoluciones que él creó o de pacientes bajo su cuidado
        if ($user->role === 'doctor' && $user->doctor) {
            return $this->canDoctorAccessEvolucion($user, $evolucion);
        }

        // Recepcionista puede ver evoluciones básicas (sin contenido médico sensible)
        if ($user->role === 'recepcionista') {
            return $this->canReceptionistAccessEvolucion($evolucion);
        }

        // Paciente puede ver solo sus propias evoluciones (limitado)
        if ($user->role === 'paciente' && $user->paciente) {
            return $this->canPatientAccessEvolucion($user, $evolucion);
        }

        return false;
    }

    /**
     * Determinar si el usuario puede crear evoluciones
     */
    public function create(User $user): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Solo doctores pueden crear evoluciones médicas
        return $user->role === 'doctor' && $user->doctor;
    }

    /**
     * Determinar si el usuario puede crear una evolución para un paciente específico
     */
    public function createForPatient(User $user, int $pacienteId): bool
    {
        if (!$this->create($user)) {
            return false;
        }

        // Doctor debe tener un turno completado con el paciente
        return $this->hasDoctorCompletedAppointmentWithPatient($user, $pacienteId);
    }

    /**
     * Determinar si el usuario puede actualizar una evolución
     */
    public function update(User $user, Evolucion $evolucion): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Verificar que la evolución sea modificable
        if (!$this->isEvolucionModifiable($evolucion)) {
            return false;
        }

        // Administradores pueden modificar cualquier evolución (con auditoría)
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor solo puede modificar sus propias evoluciones recientes
        if ($user->role === 'doctor' && $user->doctor) {
            return $this->canDoctorUpdateEvolucion($user, $evolucion);
        }

        return false;
    }

    /**
     * Determinar si el usuario puede eliminar una evolución
     */
    public function delete(User $user, Evolucion $evolucion): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Solo administradores pueden eliminar evoluciones (soft delete)
        return in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede restaurar una evolución
     */
    public function restore(User $user, Evolucion $evolucion): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede eliminar permanentemente una evolución
     */
    public function forceDelete(User $user, Evolucion $evolucion): bool
    {
        return $this->isActiveUser($user) && 
               $user->role === 'super_admin';
    }

    /**
     * Determinar si el usuario puede ver el contenido médico completo
     */
    public function viewMedicalContent(User $user, Evolucion $evolucion): bool
    {
        if (!$this->view($user, $evolucion)) {
            return false;
        }

        // Solo doctores y administradores pueden ver contenido médico completo
        if (in_array($user->role, ['administrador', 'super_admin', 'doctor'])) {
            return true;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede agregar archivos adjuntos
     */
    public function attachFiles(User $user, Evolucion $evolucion): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Administradores pueden adjuntar archivos
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor puede adjuntar archivos a sus evoluciones
        if ($user->role === 'doctor' && $user->doctor) {
            return $evolucion->doctor_id === $user->doctor->id;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede firmar digitalmente la evolución
     */
    public function digitalSign(User $user, Evolucion $evolucion): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Solo el doctor que creó la evolución puede firmarla
        if ($user->role === 'doctor' && $user->doctor) {
            return $evolucion->doctor_id === $user->doctor->id && 
                   !$evolucion->is_signed;
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
     * Verificar si la evolución es modificable
     */
    private function isEvolucionModifiable(Evolucion $evolucion): bool
    {
        // No se puede modificar si ya está firmada
        if ($evolucion->is_signed) {
            return false;
        }

        // Solo se puede modificar dentro de las primeras 24 horas
        $horasDesdeCreacion = Carbon::parse($evolucion->created_at)->diffInHours(now());
        return $horasDesdeCreacion <= 24;
    }

    /**
     * Verificar si el doctor puede acceder a la evolución
     */
    private function canDoctorAccessEvolucion(User $user, Evolucion $evolucion): bool
    {
        // Doctor puede ver sus propias evoluciones
        if ($evolucion->doctor_id === $user->doctor->id) {
            return true;
        }

        // Doctor puede ver evoluciones de pacientes que atendió recientemente
        return $this->hasDoctorRecentlyTreatedPatient($user, $evolucion->historia_clinica->paciente_id);
    }

    /**
     * Verificar si la recepcionista puede acceder a información básica
     */
    private function canReceptionistAccessEvolucion(Evolucion $evolucion): bool
    {
        // Recepcionista solo puede ver información básica, no contenido médico
        return true; // Se filtrarán los campos sensibles en el controlador
    }

    /**
     * Verificar si el paciente puede acceder a sus evoluciones
     */
    private function canPatientAccessEvolucion(User $user, Evolucion $evolucion): bool
    {
        return $evolucion->historia_clinica->paciente_id === $user->paciente->id;
    }

    /**
     * Verificar si el doctor puede actualizar la evolución
     */
    private function canDoctorUpdateEvolucion(User $user, Evolucion $evolucion): bool
    {
        // Solo puede modificar sus propias evoluciones
        if ($evolucion->doctor_id !== $user->doctor->id) {
            return false;
        }

        // Solo dentro del período modificable
        return $this->isEvolucionModifiable($evolucion);
    }

    /**
     * Verificar si el doctor tiene turno completado con el paciente
     */
    private function hasDoctorCompletedAppointmentWithPatient(User $user, int $pacienteId): bool
    {
        return Turno::where('doctor_id', $user->doctor->id)
                   ->where('paciente_id', $pacienteId)
                   ->where('estado', Turno::ESTADO_COMPLETADO)
                   ->exists();
    }

    /**
     * Verificar si el doctor atendió recientemente al paciente
     */
    private function hasDoctorRecentlyTreatedPatient(User $user, int $pacienteId): bool
    {
        $fechaLimite = now()->subMonths(6); // Últimos 6 meses
        
        return Turno::where('doctor_id', $user->doctor->id)
                   ->where('paciente_id', $pacienteId)
                   ->where('estado', Turno::ESTADO_COMPLETADO)
                   ->where('fecha', '>=', $fechaLimite)
                   ->exists();
    }
}
