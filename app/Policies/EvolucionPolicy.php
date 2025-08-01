<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Evolucion;
use App\Models\HistoriaClinica;

class EvolucionPolicy
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
    public function view(User $user, Evolucion $evolucion): bool
    {
        // Admin puede ver todas
        if ($user->rol === 'admin') {
            return true;
        }

        // Doctor solo puede ver sus propias evoluciones
        if ($user->rol === 'doctor') {
            return $evolucion->doctor_id === $user->doctor_id;
        }

        // Secretaria puede ver todas
        if ($user->rol === 'secretaria') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->rol === 'doctor';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Evolucion $evolucion): bool
    {
        // Solo el doctor que creó la evolución puede editarla
        if ($user->rol === 'doctor') {
            return $evolucion->doctor_id === $user->doctor_id;
        }

        // Admin puede editar cualquier evolución
        return $user->rol === 'admin';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Evolucion $evolucion): bool
    {
        // Solo admin puede eliminar evoluciones
        return $user->rol === 'admin';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Evolucion $evolucion): bool
    {
        return $user->rol === 'admin';
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Evolucion $evolucion): bool
    {
        return $user->rol === 'admin';
    }
}
