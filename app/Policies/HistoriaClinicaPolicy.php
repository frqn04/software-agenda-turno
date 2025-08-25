<?php

namespace App\Policies;

use App\Models\User;
use App\Models\HistoriaClinica;
use App\Models\Turno;
use App\Models\Evolucion;
use Illuminate\Auth\Access\HandlesAuthorization;
use Carbon\Carbon;

/**
 * Policy para el modelo HistoriaClinica
 * Maneja la autorización para operaciones de historias clínicas
 * Las historias clínicas son documentos médicos legales que requieren máxima protección
 */
class HistoriaClinicaPolicy
{
    use HandlesAuthorization;

    /**
     * Determinar si el usuario puede ver cualquier historia clínica
     */
    public function viewAny(User $user): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin', 'doctor', 'recepcionista']);
    }

    /**
     * Determinar si el usuario puede ver una historia clínica específica
     */
    public function view(User $user, HistoriaClinica $historiaClinica): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Administradores pueden ver todas las historias clínicas
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor puede ver historias de pacientes bajo su cuidado
        if ($user->role === 'doctor' && $user->doctor) {
            return $this->canDoctorAccessHistoria($user, $historiaClinica);
        }

        // Recepcionista puede ver información básica (sin contenido médico)
        if ($user->role === 'recepcionista') {
            return $this->canReceptionistAccessHistoria($historiaClinica);
        }

        // Paciente puede ver su propia historia clínica (limitada)
        if ($user->role === 'paciente' && $user->paciente) {
            return $historiaClinica->paciente_id === $user->paciente->id;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede crear historias clínicas
     */
    public function create(User $user): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Administradores y recepcionistas pueden crear historias clínicas
        return in_array($user->role, ['administrador', 'super_admin', 'recepcionista']);
    }

    /**
     * Determinar si el usuario puede actualizar una historia clínica
     */
    public function update(User $user, HistoriaClinica $historiaClinica): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Administradores pueden modificar cualquier historia (con auditoría)
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Recepcionista puede actualizar datos administrativos básicos
        if ($user->role === 'recepcionista') {
            return $this->canReceptionistUpdateHistoria($historiaClinica);
        }

        // Doctor puede actualizar información médica si tiene relación con el paciente
        if ($user->role === 'doctor' && $user->doctor) {
            return $this->canDoctorUpdateHistoria($user, $historiaClinica);
        }

        return false;
    }

    /**
     * Determinar si el usuario puede eliminar una historia clínica
     */
    public function delete(User $user, HistoriaClinica $historiaClinica): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Solo administradores pueden eliminar historias clínicas (soft delete)
        // Las historias clínicas son documentos legales
        return in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede restaurar una historia clínica
     */
    public function restore(User $user, HistoriaClinica $historiaClinica): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede eliminar permanentemente una historia clínica
     */
    public function forceDelete(User $user, HistoriaClinica $historiaClinica): bool
    {
        // Solo super_admin puede eliminar permanentemente (muy restringido)
        return $this->isActiveUser($user) && 
               $user->role === 'super_admin';
    }

    /**
     * Determinar si el usuario puede ver información médica sensible
     */
    public function viewSensitiveMedicalData(User $user, HistoriaClinica $historiaClinica): bool
    {
        if (!$this->view($user, $historiaClinica)) {
            return false;
        }

        // Solo doctores y administradores pueden ver datos médicos sensibles
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        if ($user->role === 'doctor' && $user->doctor) {
            return $this->canDoctorAccessHistoria($user, $historiaClinica);
        }

        return false;
    }

    /**
     * Determinar si el usuario puede agregar evoluciones médicas
     */
    public function addEvolucion(User $user, HistoriaClinica $historiaClinica): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Solo doctores pueden agregar evoluciones médicas
        if ($user->role === 'doctor' && $user->doctor) {
            return $this->hasDoctorActiveRelationshipWithPatient($user, $historiaClinica->paciente_id);
        }

        return false;
    }

    /**
     * Determinar si el usuario puede ver evoluciones médicas
     */
    public function viewEvoluciones(User $user, HistoriaClinica $historiaClinica): bool
    {
        if (!$this->view($user, $historiaClinica)) {
            return false;
        }

        // Administradores pueden ver todas las evoluciones
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor puede ver evoluciones si tiene relación con el paciente
        if ($user->role === 'doctor' && $user->doctor) {
            return $this->canDoctorAccessHistoria($user, $historiaClinica);
        }

        // Paciente puede ver sus propias evoluciones (versión resumida)
        if ($user->role === 'paciente' && $user->paciente) {
            return $historiaClinica->paciente_id === $user->paciente->id;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede agregar archivos adjuntos
     */
    public function attachFiles(User $user, HistoriaClinica $historiaClinica): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Administradores pueden adjuntar archivos
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor puede adjuntar archivos médicos
        if ($user->role === 'doctor' && $user->doctor) {
            return $this->canDoctorAccessHistoria($user, $historiaClinica);
        }

        // Recepcionista puede adjuntar documentos administrativos
        if ($user->role === 'recepcionista') {
            return true;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede ver archivos adjuntos médicos
     */
    public function viewMedicalAttachments(User $user, HistoriaClinica $historiaClinica): bool
    {
        if (!$this->viewSensitiveMedicalData($user, $historiaClinica)) {
            return false;
        }

        return in_array($user->role, ['administrador', 'super_admin', 'doctor']);
    }

    /**
     * Determinar si el usuario puede exportar la historia clínica
     */
    public function export(User $user, HistoriaClinica $historiaClinica): bool
    {
        if (!$this->view($user, $historiaClinica)) {
            return false;
        }

        // Solo administradores y el doctor tratante pueden exportar
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        if ($user->role === 'doctor' && $user->doctor) {
            return $this->isPrimaryDoctor($user, $historiaClinica);
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
     * Verificar si el doctor puede acceder a la historia clínica
     */
    private function canDoctorAccessHistoria(User $user, HistoriaClinica $historiaClinica): bool
    {
        // Doctor puede ver historias de pacientes que ha atendido
        return Turno::where('doctor_id', $user->doctor->id)
                   ->where('paciente_id', $historiaClinica->paciente_id)
                   ->whereIn('estado', [Turno::ESTADO_COMPLETADO, Turno::ESTADO_CONFIRMADO])
                   ->exists();
    }

    /**
     * Verificar si la recepcionista puede acceder a información básica
     */
    private function canReceptionistAccessHistoria(HistoriaClinica $historiaClinica): bool
    {
        // Recepcionista puede ver información básica, no médica
        return true; // Los campos médicos se filtrarán en el controlador
    }

    /**
     * Verificar si la recepcionista puede actualizar la historia
     */
    private function canReceptionistUpdateHistoria(HistoriaClinica $historiaClinica): bool
    {
        // Solo puede actualizar datos administrativos, no médicos
        return true; // La validación específica se hará en el controlador
    }

    /**
     * Verificar si el doctor puede actualizar la historia clínica
     */
    private function canDoctorUpdateHistoria(User $user, HistoriaClinica $historiaClinica): bool
    {
        // Doctor puede agregar información médica si tiene relación activa con el paciente
        return $this->hasDoctorActiveRelationshipWithPatient($user, $historiaClinica->paciente_id);
    }

    /**
     * Verificar si el doctor tiene relación activa con el paciente
     */
    private function hasDoctorActiveRelationshipWithPatient(User $user, int $pacienteId): bool
    {
        $fechaLimite = now()->subMonths(3); // Relación activa en los últimos 3 meses
        
        return Turno::where('doctor_id', $user->doctor->id)
                   ->where('paciente_id', $pacienteId)
                   ->where('fecha', '>=', $fechaLimite)
                   ->whereIn('estado', [
                       Turno::ESTADO_COMPLETADO,
                       Turno::ESTADO_CONFIRMADO,
                       Turno::ESTADO_EN_CURSO
                   ])
                   ->exists();
    }

    /**
     * Verificar si el doctor es el médico principal del paciente
     */
    private function isPrimaryDoctor(User $user, HistoriaClinica $historiaClinica): bool
    {
        // El doctor con más turnos completados en los últimos 6 meses
        $fechaLimite = now()->subMonths(6);
        
        $turnosDoctor = Turno::where('doctor_id', $user->doctor->id)
                            ->where('paciente_id', $historiaClinica->paciente_id)
                            ->where('fecha', '>=', $fechaLimite)
                            ->where('estado', Turno::ESTADO_COMPLETADO)
                            ->count();

        return $turnosDoctor >= 3; // Al menos 3 turnos completados
    }
}
