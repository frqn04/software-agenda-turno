<?php

namespace App\Policies;

use App\Models\User;
use App\Models\HistoriaClinica;

class HistoriaClinicaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->rol, ['admin', 'doctor', 'secretaria']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, HistoriaClinica $historiaClinica): bool
    {
        // Admin y secretaria pueden ver todas
        if (in_array($user->rol, ['admin', 'secretaria'])) {
            return true;
        }

        // Doctor puede ver historias de sus pacientes
        if ($user->rol === 'doctor') {
            // Verificar si el doctor tiene turnos con este paciente
            return $historiaClinica->paciente->turnos()
                ->where('doctor_id', $user->doctor_id)
                ->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return in_array($user->rol, ['admin', 'secretaria']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, HistoriaClinica $historiaClinica): bool
    {
        // Admin puede editar cualquier historia
        if ($user->rol === 'admin') {
            return true;
        }

        // Secretaria puede editar datos básicos
        if ($user->rol === 'secretaria') {
            return true;
        }

        // Doctor puede actualizar solo si tiene relación con el paciente
        if ($user->rol === 'doctor') {
            return $historiaClinica->paciente->turnos()
                ->where('doctor_id', $user->doctor_id)
                ->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, HistoriaClinica $historiaClinica): bool
    {
        // Solo admin puede eliminar historias clínicas
        return $user->rol === 'admin';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, HistoriaClinica $historiaClinica): bool
    {
        return $user->rol === 'admin';
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, HistoriaClinica $historiaClinica): bool
    {
        return $user->rol === 'admin';
    }
}
