<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Turno;
use Illuminate\Auth\Access\HandlesAuthorization;

class TurnoPolicy
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
    public function view(User $user, Turno $turno): bool
    {
        if ($user->rol === 'admin') {
            return true;
        }

        if ($user->rol === 'doctor' && $user->doctor_id === $turno->doctor_id) {
            return true;
        }

        if ($user->rol === 'recepcionista') {
            return true;
        }

        return false;
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
    public function update(User $user, Turno $turno): bool
    {
        if ($user->rol === 'admin') {
            return true;
        }

        if ($user->rol === 'doctor' && $user->doctor_id === $turno->doctor_id) {
            return true; // Doctor puede modificar sus propios turnos
        }

        if ($user->rol === 'recepcionista') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Turno $turno): bool
    {
        if ($user->rol === 'admin') {
            return true;
        }

        if ($user->rol === 'recepcionista') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can cancel the appointment.
     */
    public function cancel(User $user, Turno $turno): bool
    {
        if ($user->rol === 'admin') {
            return true;
        }

        if ($user->rol === 'doctor' && $user->doctor_id === $turno->doctor_id) {
            return true;
        }

        if ($user->rol === 'recepcionista') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can mark appointment as completed.
     */
    public function complete(User $user, Turno $turno): bool
    {
        if ($user->rol === 'admin') {
            return true;
        }

        if ($user->rol === 'doctor' && $user->doctor_id === $turno->doctor_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can reschedule the appointment.
     */
    public function reschedule(User $user, Turno $turno): bool
    {
        if ($user->rol === 'admin') {
            return true;
        }

        if ($user->rol === 'doctor' && $user->doctor_id === $turno->doctor_id) {
            return true;
        }

        if ($user->rol === 'recepcionista') {
            return true;
        }

        return false;
    }
}
