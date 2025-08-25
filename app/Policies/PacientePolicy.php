<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Paciente;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Policy para el modelo Paciente
 * Maneja la autorización para operaciones de pacientes
 */
class PacientePolicy
{
    use HandlesAuthorization;

    /**
     * Determinar si el usuario puede ver cualquier paciente
     */
    public function viewAny(User $user): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin', 'doctor', 'recepcionista']);
    }

    /**
     * Determinar si el usuario puede ver un paciente específico
     */
    public function view(User $user, Paciente $paciente): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Administradores pueden ver todos los pacientes
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor puede ver pacientes que han tenido turnos con él o tiene historias clínicas
        if ($user->role === 'doctor' && $user->doctor) {
            return $this->doctorCanViewPatient($user->doctor->id, $paciente);
        }

        // Recepcionista puede ver todos los pacientes activos
        if ($user->role === 'recepcionista') {
            return $paciente->is_active;
        }

        // Paciente puede ver solo sus propios datos
        if ($user->role === 'paciente' && $user->paciente) {
            return $user->paciente->id === $paciente->id;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede crear pacientes
     */
    public function create(User $user): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin', 'recepcionista']);
    }

    /**
     * Determinar si el usuario puede actualizar un paciente
     */
    public function update(User $user, Paciente $paciente): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Administradores pueden modificar cualquier paciente
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Recepcionista puede modificar datos básicos de pacientes activos
        if ($user->role === 'recepcionista') {
            return $paciente->is_active;
        }

        // Doctor puede actualizar algunos datos de sus pacientes durante la consulta
        if ($user->role === 'doctor' && $user->doctor) {
            return $this->doctorCanUpdatePatient($user->doctor->id, $paciente);
        }

        // Paciente puede actualizar algunos de sus propios datos
        if ($user->role === 'paciente' && $user->paciente) {
            return $user->paciente->id === $paciente->id;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede eliminar un paciente
     */
    public function delete(User $user, Paciente $paciente): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Solo administradores pueden eliminar pacientes
        if (!in_array($user->role, ['administrador', 'super_admin'])) {
            return false;
        }

        // Verificar que no tenga datos médicos críticos
        return !$this->patientHasCriticalData($paciente);
    }

    /**
     * Determinar si el usuario puede restaurar un paciente
     */
    public function restore(User $user, Paciente $paciente): bool
    {
        return $this->isActiveUser($user) && 
               in_array($user->role, ['administrador', 'super_admin']);
    }

    /**
     * Determinar si el usuario puede eliminar permanentemente un paciente
     */
    public function forceDelete(User $user, Paciente $paciente): bool
    {
        return $this->isActiveUser($user) && 
               $user->role === 'super_admin';
    }

    /**
     * Determinar si el usuario puede acceder a la historia médica del paciente
     */
    public function viewMedicalHistory(User $user, Paciente $paciente): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Administradores pueden ver cualquier historia médica
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor puede ver historias de sus pacientes
        if ($user->role === 'doctor' && $user->doctor) {
            return $this->doctorCanViewPatientHistory($user->doctor->id, $paciente);
        }

        // Paciente puede ver su propia historia médica
        if ($user->role === 'paciente' && $user->paciente) {
            return $user->paciente->id === $paciente->id;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede actualizar la historia médica del paciente
     */
    public function updateMedicalHistory(User $user, Paciente $paciente): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Administradores pueden modificar cualquier historia médica
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Solo doctores pueden actualizar historias médicas
        if ($user->role === 'doctor' && $user->doctor) {
            return $this->doctorCanUpdatePatientHistory($user->doctor->id, $paciente);
        }

        return false;
    }

    /**
     * Determinar si el usuario puede crear historia clínica para el paciente
     */
    public function createMedicalHistory(User $user, Paciente $paciente): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Administradores pueden crear historias para cualquier paciente
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        // Doctor puede crear historia clínica para pacientes que atenderá
        if ($user->role === 'doctor' && $user->doctor) {
            return $paciente->is_active;
        }

        return false;
    }

    /**
     * Determinar si el usuario puede ver datos sensibles del paciente
     */
    public function viewSensitiveData(User $user, Paciente $paciente): bool
    {
        if (!$this->isActiveUser($user)) {
            return false;
        }

        // Solo administradores y doctores pueden ver datos sensibles
        if (in_array($user->role, ['administrador', 'super_admin'])) {
            return true;
        }

        if ($user->role === 'doctor' && $user->doctor) {
            return $this->doctorCanViewPatient($user->doctor->id, $paciente);
        }

        return false;
    }

    /**
     * Determinar si el usuario puede agendar turnos para el paciente
     */
    public function scheduleAppointment(User $user, Paciente $paciente): bool
    {
        if (!$this->isActiveUser($user) || !$paciente->is_active) {
            return false;
        }

        // Administradores y recepcionistas pueden agendar para cualquier paciente
        if (in_array($user->role, ['administrador', 'super_admin', 'recepcionista'])) {
            return true;
        }

        // Paciente puede agendar sus propios turnos
        if ($user->role === 'paciente' && $user->paciente) {
            return $user->paciente->id === $paciente->id;
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
     * Verificar si el doctor puede ver al paciente
     */
    private function doctorCanViewPatient(int $doctorId, Paciente $paciente): bool
    {
        // Doctor puede ver pacientes que han tenido turnos con él
        $hasAppointments = $paciente->turnos()
            ->where('doctor_id', $doctorId)
            ->exists();

        // O pacientes que tienen historias clínicas con él
        $hasHistory = $paciente->historiasClinicas()
            ->whereHas('evoluciones', function($query) use ($doctorId) {
                $query->where('doctor_id', $doctorId);
            })
            ->exists();

        return $hasAppointments || $hasHistory;
    }

    /**
     * Verificar si el doctor puede actualizar al paciente
     */
    private function doctorCanUpdatePatient(int $doctorId, Paciente $paciente): bool
    {
        // Doctor puede actualizar datos básicos de pacientes que está atendiendo activamente
        return $paciente->turnos()
            ->where('doctor_id', $doctorId)
            ->where('estado', 'en_curso')
            ->exists();
    }

    /**
     * Verificar si el doctor puede ver la historia del paciente
     */
    private function doctorCanViewPatientHistory(int $doctorId, Paciente $paciente): bool
    {
        // Doctor puede ver historias donde ha participado o tiene turnos
        return $this->doctorCanViewPatient($doctorId, $paciente);
    }

    /**
     * Verificar si el doctor puede actualizar la historia del paciente
     */
    private function doctorCanUpdatePatientHistory(int $doctorId, Paciente $paciente): bool
    {
        // Doctor puede actualizar historias de pacientes que está atendiendo
        return $paciente->turnos()
            ->where('doctor_id', $doctorId)
            ->whereIn('estado', ['confirmado', 'en_curso'])
            ->exists();
    }

    /**
     * Verificar si el paciente tiene datos médicos críticos
     */
    private function patientHasCriticalData(Paciente $paciente): bool
    {
        // Verificar si tiene turnos futuros
        $hasFutureAppointments = $paciente->turnos()
            ->where('fecha', '>=', now()->toDateString())
            ->exists();

        // Verificar si tiene historias clínicas
        $hasMedicalHistory = $paciente->historiasClinicas()->exists();

        return $hasFutureAppointments || $hasMedicalHistory;
    }
}
