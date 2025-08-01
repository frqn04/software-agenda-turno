<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Patient;

class PatientPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->rol, ['admin', 'doctor', 'secretaria']);
    }

    public function view(User $user, Patient $patient): bool
    {
        if ($user->rol === 'admin') {
            return true;
        }

        if ($user->rol === 'doctor') {
            return $patient->appointments()
                ->where('doctor_id', $user->doctor_id)
                ->exists() || 
                $patient->clinicalHistories()
                ->where('doctor_id', $user->doctor_id)
                ->exists();
        }

        return in_array($user->rol, ['secretaria']);
    }

    public function create(User $user): bool
    {
        return in_array($user->rol, ['admin', 'secretaria']);
    }

    public function update(User $user, Patient $patient): bool
    {
        if ($user->rol === 'admin') {
            return true;
        }

        if ($user->rol === 'doctor') {
            return $patient->appointments()
                ->where('doctor_id', $user->doctor_id)
                ->where('estado', 'pendiente')
                ->exists();
        }

        return $user->rol === 'secretaria';
    }

    public function delete(User $user, Patient $patient): bool
    {
        if ($user->rol !== 'admin') {
            return false;
        }

        return !$patient->appointments()
            ->where('estado', 'realizado')
            ->exists();
    }

    public function forceDelete(User $user, Patient $patient): bool
    {
        return $user->rol === 'admin';
    }

    public function restore(User $user, Patient $patient): bool
    {
        return $user->rol === 'admin';
    }

    public function viewClinicalHistory(User $user, Patient $patient): bool
    {
        if ($user->rol === 'admin') {
            return true;
        }

        if ($user->rol === 'doctor') {
            return $patient->clinicalHistories()
                ->where('doctor_id', $user->doctor_id)
                ->exists();
        }

        return false;
    }

    public function createClinicalHistory(User $user, Patient $patient): bool
    {
        return $user->rol === 'doctor' && $user->doctor_id;
    }

    public function updateClinicalHistory(User $user, Patient $patient): bool
    {
        if ($user->rol === 'admin') {
            return true;
        }

        return $user->rol === 'doctor' && 
               $patient->clinicalHistories()
               ->where('doctor_id', $user->doctor_id)
               ->exists();
    }
}
