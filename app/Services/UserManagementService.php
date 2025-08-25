<?php

namespace App\Services;

use App\Models\User;
use App\Models\Doctor;
use App\Models\Paciente;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Servicio de gestión de usuarios y autenticación
 * Maneja registro, autenticación y gestión de perfiles
 */
class UserManagementService
{
    /**
     * Crear nuevo usuario con rol
     */
    public function createUser(array $userData, string $role = 'patient'): User
    {
        return DB::transaction(function () use ($userData, $role) {
            // Validar que el email no exista
            if (User::where('email', $userData['email'])->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'El email ya está registrado en el sistema.'
                ]);
            }

            // Crear usuario
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => isset($userData['password']) ? 
                    Hash::make($userData['password']) : 
                    Hash::make(Str::random(12)), // Contraseña temporal
                'email_verified_at' => isset($userData['auto_verify']) && $userData['auto_verify'] ? 
                    now() : null,
                'is_active' => $userData['is_active'] ?? true,
                'last_login' => null,
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ]);

            // Asignar rol
            $user->assignRole($role);

            // Crear perfil específico según el rol
            switch ($role) {
                case 'doctor':
                    $this->createDoctorProfile($user, $userData);
                    break;
                case 'patient':
                    $this->createPatientProfile($user, $userData);
                    break;
                case 'admin':
                case 'staff':
                    // Solo crear usuario, sin perfil adicional
                    break;
            }

            // Enviar email de bienvenida si se especifica
            if (isset($userData['send_welcome_email']) && $userData['send_welcome_email']) {
                $this->sendWelcomeEmail($user, $userData['password'] ?? null);
            }

            // Log de auditoría
            AuditService::logActivity(
                'user_created',
                'users',
                $user->id,
                null,
                null,
                ['role' => $role, 'email' => $user->email]
            );

            return $user;
        });
    }

    /**
     * Actualizar perfil de usuario
     */
    public function updateUserProfile(int $userId, array $data): User
    {
        $user = User::findOrFail($userId);
        $oldData = $user->toArray();

        return DB::transaction(function () use ($user, $data, $oldData) {
            // Actualizar datos básicos del usuario
            $userFields = ['name', 'email', 'is_active'];
            $userData = array_intersect_key($data, array_flip($userFields));
            
            if (!empty($userData)) {
                $user->update($userData);
            }

            // Actualizar contraseña si se proporciona
            if (isset($data['password'])) {
                $user->update(['password' => Hash::make($data['password'])]);
            }

            // Actualizar perfil específico según el rol
            if ($user->hasRole('doctor') && isset($data['doctor_profile'])) {
                $this->updateDoctorProfile($user, $data['doctor_profile']);
            }

            if ($user->hasRole('patient') && isset($data['patient_profile'])) {
                $this->updatePatientProfile($user, $data['patient_profile']);
            }

            // Log de auditoría
            AuditService::logActivity(
                'user_updated',
                'users',
                $user->id,
                null,
                $oldData,
                $data
            );

            return $user->fresh();
        });
    }

    /**
     * Autenticar usuario con validaciones de seguridad
     */
    public function authenticateUser(string $email, string $password, string $ipAddress = null): array
    {
        $user = User::where('email', $email)->first();
        $ipAddress = $ipAddress ?? request()->ip();

        // Usuario no encontrado
        if (!$user) {
            AuditService::logAccess('login_failed', null, [
                'email' => $email,
                'reason' => 'user_not_found',
                'ip_address' => $ipAddress,
            ]);
            
            throw ValidationException::withMessages([
                'email' => 'Credenciales inválidas.'
            ]);
        }

        // Verificar si el usuario está bloqueado
        if ($user->locked_until && $user->locked_until->isFuture()) {
            AuditService::logAccess('login_blocked', $user->id, [
                'reason' => 'account_locked',
                'locked_until' => $user->locked_until,
            ]);
            
            throw ValidationException::withMessages([
                'email' => 'Cuenta bloqueada temporalmente. Intente más tarde.'
            ]);
        }

        // Verificar si el usuario está activo
        if (!$user->is_active) {
            AuditService::logAccess('login_failed', $user->id, [
                'reason' => 'account_inactive',
            ]);
            
            throw ValidationException::withMessages([
                'email' => 'Cuenta desactivada. Contacte al administrador.'
            ]);
        }

        // Verificar contraseña
        if (!Hash::check($password, $user->password)) {
            $this->handleFailedLogin($user);
            
            AuditService::logAccess('login_failed', $user->id, [
                'reason' => 'invalid_password',
                'failed_attempts' => $user->failed_login_attempts,
            ]);
            
            throw ValidationException::withMessages([
                'password' => 'Credenciales inválidas.'
            ]);
        }

        // Login exitoso
        $this->handleSuccessfulLogin($user);
        
        AuditService::logAccess('login_success', $user->id, [
            'last_login' => $user->last_login,
            'login_count' => $user->login_count ?? 0,
        ]);

        // Generar token de autenticación
        $token = $user->createToken('auth-token', $this->getTokenAbilities($user))->plainTextToken;

        return [
            'user' => $user->load('roles'),
            'token' => $token,
            'expires_at' => now()->addHours(8), // Token válido por 8 horas
        ];
    }

    /**
     * Cambiar contraseña del usuario
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        $user = User::findOrFail($userId);

        // Verificar contraseña actual
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'La contraseña actual no es correcta.'
            ]);
        }

        // Validar que la nueva contraseña sea diferente
        if (Hash::check($newPassword, $user->password)) {
            throw ValidationException::withMessages([
                'new_password' => 'La nueva contraseña debe ser diferente a la actual.'
            ]);
        }

        // Actualizar contraseña
        $user->update([
            'password' => Hash::make($newPassword),
            'password_changed_at' => now(),
        ]);

        // Log de auditoría
        AuditService::logActivity(
            'password_changed',
            'users',
            $user->id,
            $user->id,
            null,
            ['changed_at' => now()]
        );

        return true;
    }

    /**
     * Resetear contraseña
     */
    public function resetPassword(string $email): string
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => 'No se encontró un usuario con este email.'
            ]);
        }

        // Generar nueva contraseña temporal
        $temporaryPassword = Str::random(12);
        
        $user->update([
            'password' => Hash::make($temporaryPassword),
            'password_reset_required' => true,
            'password_reset_token' => Str::random(60),
            'password_reset_expires' => now()->addHours(24),
        ]);

        // Enviar email con nueva contraseña
        $this->sendPasswordResetEmail($user, $temporaryPassword);

        // Log de auditoría
        AuditService::logActivity(
            'password_reset',
            'users',
            $user->id,
            null,
            null,
            ['reset_at' => now(), 'email' => $email]
        );

        return 'Se ha enviado una nueva contraseña a su email.';
    }

    /**
     * Activar/Desactivar usuario
     */
    public function toggleUserStatus(int $userId, bool $isActive): bool
    {
        $user = User::findOrFail($userId);
        $oldStatus = $user->is_active;

        $user->update(['is_active' => $isActive]);

        // Log de auditoría
        AuditService::logActivity(
            $isActive ? 'user_activated' : 'user_deactivated',
            'users',
            $user->id,
            null,
            ['is_active' => $oldStatus],
            ['is_active' => $isActive]
        );

        return true;
    }

    /**
     * Obtener estadísticas de usuarios
     */
    public function getUserStats(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'inactive_users' => User::where('is_active', false)->count(),
            'locked_users' => User::where('locked_until', '>', now())->count(),
            'users_by_role' => [
                'admins' => User::role('admin')->count(),
                'doctors' => User::role('doctor')->count(),
                'patients' => User::role('patient')->count(),
                'staff' => User::role('staff')->count(),
            ],
            'recent_logins' => User::where('last_login', '>=', now()->subDay())->count(),
            'never_logged_in' => User::whereNull('last_login')->count(),
            'password_reset_required' => User::where('password_reset_required', true)->count(),
        ];
    }

    /**
     * Listar usuarios con filtros
     */
    public function listUsers(array $filters = []): array
    {
        $query = User::with('roles');

        // Aplicar filtros
        if (isset($filters['role'])) {
            $query->role($filters['role']);
        }

        if (isset($filters['active'])) {
            $query->where('is_active', $filters['active']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (isset($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from']);
        }

        if (isset($filters['created_to'])) {
            $query->where('created_at', '<=', $filters['created_to']);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(50);

        return [
            'users' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ];
    }

    // Métodos privados

    private function createDoctorProfile(User $user, array $data): void
    {
        if (isset($data['doctor_profile'])) {
            $doctorData = array_merge($data['doctor_profile'], [
                'user_id' => $user->id,
                'activo' => true,
            ]);

            Doctor::create($doctorData);
        }
    }

    private function createPatientProfile(User $user, array $data): void
    {
        if (isset($data['patient_profile'])) {
            $patientData = array_merge($data['patient_profile'], [
                'user_id' => $user->id,
                'activo' => true,
            ]);

            Paciente::create($patientData);
        }
    }

    private function updateDoctorProfile(User $user, array $data): void
    {
        $doctor = $user->doctor;
        if ($doctor) {
            $doctor->update($data);
        }
    }

    private function updatePatientProfile(User $user, array $data): void
    {
        $patient = $user->paciente;
        if ($patient) {
            $patient->update($data);
        }
    }

    private function handleFailedLogin(User $user): void
    {
        $user->increment('failed_login_attempts');

        // Bloquear cuenta después de 5 intentos fallidos
        if ($user->failed_login_attempts >= 5) {
            $user->update([
                'locked_until' => now()->addMinutes(30), // Bloquear por 30 minutos
            ]);
        }
    }

    private function handleSuccessfulLogin(User $user): void
    {
        $user->update([
            'last_login' => now(),
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'login_count' => ($user->login_count ?? 0) + 1,
        ]);
    }

    private function getTokenAbilities(User $user): array
    {
        $abilities = ['access:api'];

        if ($user->hasRole('admin')) {
            $abilities = array_merge($abilities, [
                'admin:*', 'users:*', 'doctors:*', 'patients:*', 'reports:*'
            ]);
        } elseif ($user->hasRole('doctor')) {
            $abilities = array_merge($abilities, [
                'appointments:manage', 'patients:view', 'medical-records:*'
            ]);
        } elseif ($user->hasRole('patient')) {
            $abilities = array_merge($abilities, [
                'appointments:own', 'profile:manage'
            ]);
        } elseif ($user->hasRole('staff')) {
            $abilities = array_merge($abilities, [
                'appointments:*', 'patients:*', 'doctors:view'
            ]);
        }

        return $abilities;
    }

    private function sendWelcomeEmail(User $user, ?string $password = null): void
    {
        try {
            // Aquí iría la lógica de envío de email de bienvenida
            Log::info('Welcome email sent', [
                'user_id' => $user->id,
                'email' => $user->email,
                'includes_password' => !is_null($password),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send welcome email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendPasswordResetEmail(User $user, string $temporaryPassword): void
    {
        try {
            // Aquí iría la lógica de envío de email de reset de contraseña
            Log::info('Password reset email sent', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send password reset email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
