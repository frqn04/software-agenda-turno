<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Paciente;
use Illuminate\Auth\Access\HandlesAuthorization;

class PacientePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->rol, ['admin', 'doctor', 'recepcionista']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Paciente $paciente): bool
    {
        return in_array($user->rol, ['admin', 'doctor', 'recepcionista']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return in_array($user->rol, ['admin', 'recepcionista']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Paciente $paciente): bool
    {
        return in_array($user->rol, ['admin', 'recepcionista']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Paciente $paciente): bool
    {
        return $user->rol === 'admin';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Paciente $paciente): bool
    {
        return $user->rol === 'admin';
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Paciente $paciente): bool
    {
        return $user->rol === 'admin';
    }

    /**
     * Determine whether the user can access patient medical history.
     */
    public function viewMedicalHistory(User $user, Paciente $paciente): bool
    {
        if ($user->rol === 'admin') {
            return true;
        }

        if ($user->rol === 'doctor') {
            return true; // Los doctores pueden ver todas las historias
        }

        return false;
    }

    /**
     * Determine whether the user can update patient medical history.
     */
    public function updateMedicalHistory(User $user, Paciente $paciente): bool
    {
        if ($user->rol === 'admin') {
            return true;
        }

        if ($user->rol === 'doctor') {
            return true; // Los doctores pueden actualizar historias
        }

        return false;
    }
}
