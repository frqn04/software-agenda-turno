<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Appointment;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->rol, ['admin', 'doctor', 'secretaria']);
    }

    public function view(User $user, Appointment $appointment): bool
    {
        if ($user->rol === 'admin') {
            return true;
        }

        if ($user->rol === 'doctor') {
            return $appointment->doctor_id === $user->doctor_id;
        }

        return $user->rol === 'secretaria';
    }

    public function create(User $user): bool
    {
        return in_array($user->rol, ['admin', 'secretaria']);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        if ($user->rol === 'admin') {
            return true;
        }

        if ($user->rol === 'doctor') {
            return $appointment->doctor_id === $user->doctor_id &&
                   $appointment->estado === 'pendiente';
        }

        return $user->rol === 'secretaria' &&
               $appointment->estado === 'pendiente';
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        if ($user->rol === 'admin') {
            return true;
        }

        return in_array($user->rol, ['secretaria']) &&
               $appointment->estado === 'pendiente';
    }

    public function forceDelete(User $user, Appointment $appointment): bool
    {
        return $user->rol === 'admin';
    }

    public function restore(User $user, Appointment $appointment): bool
    {
        return $user->rol === 'admin';
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        if ($user->rol === 'admin') {
            return true;
        }

        if ($user->rol === 'doctor') {
            return $appointment->doctor_id === $user->doctor_id &&
                   $appointment->canBeCancelled();
        }

        return $user->rol === 'secretaria' &&
               $appointment->canBeCancelled();
    }

    public function complete(User $user, Appointment $appointment): bool
    {
        if ($user->rol === 'admin') {
            return true;
        }

        return $user->rol === 'doctor' &&
               $appointment->doctor_id === $user->doctor_id &&
               $appointment->canBeCompleted();
    }

    public function reschedule(User $user, Appointment $appointment): bool
    {
        if ($user->rol === 'admin') {
            return true;
        }

        if ($user->rol === 'doctor') {
            return $appointment->doctor_id === $user->doctor_id &&
                   $appointment->estado === 'pendiente';
        }

        return $user->rol === 'secretaria' &&
               $appointment->estado === 'pendiente';
    }
}
