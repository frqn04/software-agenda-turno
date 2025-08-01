<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->rol === 'admin';
    }

    public function view(User $authenticatedUser, User $user): bool
    {
        if ($authenticatedUser->rol === 'admin') {
            return true;
        }

        return $authenticatedUser->id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->rol === 'admin';
    }

    public function update(User $authenticatedUser, User $user): bool
    {
        if ($authenticatedUser->rol === 'admin') {
            return true;
        }

        return $authenticatedUser->id === $user->id;
    }

    public function delete(User $authenticatedUser, User $user): bool
    {
        if ($authenticatedUser->rol !== 'admin') {
            return false;
        }

        if ($user->rol === 'admin' && User::where('rol', 'admin')->count() <= 1) {
            return false;
        }

        return $authenticatedUser->id !== $user->id;
    }

    public function forceDelete(User $authenticatedUser, User $user): bool
    {
        return $authenticatedUser->rol === 'admin' &&
               $authenticatedUser->id !== $user->id;
    }

    public function restore(User $user): bool
    {
        return $user->rol === 'admin';
    }

    public function changeRole(User $authenticatedUser, User $user): bool
    {
        if ($authenticatedUser->rol !== 'admin') {
            return false;
        }

        if ($user->rol === 'admin' && User::where('rol', 'admin')->count() <= 1) {
            return false;
        }

        return $authenticatedUser->id !== $user->id;
    }

    public function viewAuditLogs(User $user): bool
    {
        return $user->rol === 'admin';
    }

    public function manageSystem(User $user): bool
    {
        return $user->rol === 'admin';
    }

    public function accessReports(User $user): bool
    {
        return in_array($user->rol, ['admin', 'doctor']);
    }
}
