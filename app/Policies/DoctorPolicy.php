<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Doctor;

class DoctorPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->rol, ['admin', 'doctor', 'secretaria']);
    }

    public function view(User $user, Doctor $doctor): bool
    {
        if ($user->rol === 'admin') {
            return true;
        }

        if ($user->rol === 'doctor') {
            return $user->doctor_id === $doctor->id;
        }

        return $user->rol === 'secretaria';
    }

    public function create(User $user): bool
    {
        return $user->rol === 'admin';
    }

    public function update(User $user, Doctor $doctor): bool
    {
        if ($user->rol === 'admin') {
            return true;
        }

        return $user->rol === 'doctor' && $user->doctor_id === $doctor->id;
    }

    public function delete(User $user, Doctor $doctor): bool
    {
        if ($user->rol !== 'admin') {
            return false;
        }

        return !$doctor->appointments()
            ->where('estado', 'pendiente')
            ->exists();
    }

    public function forceDelete(User $user, Doctor $doctor): bool
    {
        return $user->rol === 'admin';
    }

    public function restore(User $user, Doctor $doctor): bool
    {
        return $user->rol === 'admin';
    }

    public function viewSchedule(User $user, Doctor $doctor): bool
    {
        if ($user->rol === 'admin') {
            return true;
        }

        if ($user->rol === 'doctor') {
            return $user->doctor_id === $doctor->id;
        }

        return $user->rol === 'secretaria';
    }

    public function manageSchedule(User $user, Doctor $doctor): bool
    {
        if ($user->rol === 'admin') {
            return true;
        }

        return $user->rol === 'doctor' && $user->doctor_id === $doctor->id;
    }

    public function viewContracts(User $user, Doctor $doctor): bool
    {
        if ($user->rol === 'admin') {
            return true;
        }

        return $user->rol === 'doctor' && $user->doctor_id === $doctor->id;
    }

    public function manageContracts(User $user, Doctor $doctor): bool
    {
        return $user->rol === 'admin';
    }
}
