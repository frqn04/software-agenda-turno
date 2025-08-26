<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\LogAuditoria;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\StoreUserRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

/**
 * Controlador de autenticación para personal de clínica dental
 * Maneja login/logout de admin, doctores, secretarias y operadores
 * Sistema interno con auditoría médica y seguridad reforzada
 */
class AuthController extends Controller
{
    /**
     * Login de personal de clínica dental
     */
    public function login(LoginRequest $request)
    {
        return $this->handleMedicalAction(function () use ($request) {
            $key = 'clinic_login:' . $request->throttleKey();
            
            // Rate limiting más estricto para sistema médico
            if (RateLimiter::tooManyAttempts($key, 3)) {
                $seconds = RateLimiter::availableIn($key);
                
                $this->logSecurityEvent('Exceso de intentos de login de personal médico', $request, [
                    'email' => $request->email,
                    'attempts_blocked' => true,
                    'wait_seconds' => $seconds,
                ]);
                
                return $this->errorResponse(
                    "Demasiados intentos de acceso. Personal médico debe esperar {$seconds} segundos por seguridad.",
                    429
                );
            }

            // Buscar usuario activo del personal de clínica
            $user = User::where('email', $request->email)
                       ->where('activo', true)
                       ->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                RateLimiter::hit($key, 600); // 10 minutos de bloqueo para sistema médico
                
                $this->logSecurityEvent('Intento de acceso fallido al sistema médico', $request, [
                    'email' => $request->email,
                    'user_exists' => $user !== null,
                    'security_level' => 'high',
                ]);
                
                return $this->unauthorizedResponse('Credenciales incorrectas para personal de clínica');
            }

            // Verificar que el usuario tenga rol apropiado para clínica
            if (!in_array($user->rol, ['admin', 'doctor', 'secretaria', 'operador'])) {
                $this->logSecurityEvent('Intento de acceso con rol no autorizado', $request, [
                    'user_id' => $user->id,
                    'user_role' => $user->rol,
                    'security_level' => 'critical',
                ]);
                
                return $this->forbiddenResponse('Rol no autorizado para acceder al sistema de clínica');
            }

            RateLimiter::clear($key);

            // Revocar tokens anteriores por seguridad médica
            $user->tokens()->delete();

            // Crear token con expiración más corta para sistema médico
            $token = $user->createToken(
                'clinic_access_token',
                ['*'],
                now()->addHours(8) // 8 horas para personal médico
            )->plainTextToken;

            // Log de acceso exitoso al sistema médico
            $this->logMedicalActivity('Login exitoso de personal médico', 'users', $user->id, $request, [
                'user_role' => $user->rol,
                'doctor_id' => $user->doctor_id,
                'session_duration' => '8 horas',
            ]);

            return $this->successResponse([
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'rol' => $user->rol,
                    'doctor_id' => $user->doctor_id,
                    'permissions' => $this->getUserPermissions($user),
                    'clinic_access' => true,
                    'session_expires_at' => now()->addHours(8)->toISOString(),
                ]
            ], 'Acceso exitoso al sistema de clínica dental');
        }, 'login de personal médico');
    }

    /**
     * Logout de personal de clínica
     */
    public function logout(Request $request)
    {
        return $this->handleMedicalAction(function () use ($request) {
            $user = $request->user();
            
            $this->logMedicalActivity('Logout de personal médico', 'users', $user->id, $request, [
                'session_duration_minutes' => $user->currentAccessToken()->created_at->diffInMinutes(now()),
            ]);

            $request->user()->currentAccessToken()->delete();

            return $this->successResponse(null, 'Sesión cerrada exitosamente');
        }, 'logout de personal médico');
    }

    /**
     * Registro de nuevo personal de clínica (solo admins)
     */
    public function register(StoreUserRequest $request)
    {
        return $this->handleMedicalAction(function () use ($request) {
            // Solo admins pueden crear usuarios
            if ($request->user()->rol !== 'admin') {
                return $this->forbiddenResponse('Solo administradores pueden registrar personal médico');
            }

            $user = User::create($request->validated());

            $this->logMedicalActivity('Nuevo personal médico registrado', 'users', $user->id, $request, [
                'created_by_admin' => $request->user()->id,
                'new_user_role' => $user->rol,
                'new_user_email' => $user->email,
                'doctor_id' => $user->doctor_id,
            ]);

            return $this->successResponse([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'rol' => $user->rol,
                    'doctor_id' => $user->doctor_id,
                    'activo' => $user->activo,
                ]
            ], 'Personal médico registrado exitosamente', 201);
        }, 'registro de personal médico');
    }

    /**
     * Obtener información del usuario actual
     */
    public function user(Request $request)
    {
        return $this->handleMedicalAction(function () use ($request) {
            $user = $request->user();
            
            return $this->successResponse([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'rol' => $user->rol,
                'doctor_id' => $user->doctor_id,
                'activo' => $user->activo,
                'last_login' => $user->updated_at,
                'permissions' => $this->getUserPermissions($user),
                'clinic_access' => true,
                'token_expires_at' => $user->currentAccessToken()->expires_at,
            ], 'Información de personal médico obtenida');
        }, 'obtener información de usuario');
    }

    /**
     * Renovar token de acceso para personal médico
     */
    public function refreshToken(Request $request)
    {
        return $this->handleMedicalAction(function () use ($request) {
            $user = $request->user();
            
            $user->currentAccessToken()->delete();
            
            $token = $user->createToken(
                'clinic_refresh_token',
                ['*'],
                now()->addHours(8) // Renovar por 8 horas más
            )->plainTextToken;

            $this->logMedicalActivity('Token renovado para personal médico', 'users', $user->id, $request);

            return $this->successResponse([
                'token' => $token,
                'expires_at' => now()->addHours(8)->toISOString(),
            ], 'Token de acceso renovado exitosamente');
        }, 'renovación de token');
    }

    /**
     * Cambio de contraseña para personal médico
     */
    public function changePassword(Request $request)
    {
        return $this->handleMedicalAction(function () use ($request) {
            $request->validate([
                'current_password' => 'required|string',
                'new_password' => [
                    'required',
                    'confirmed',
                    Password::min(8)
                        ->letters()
                        ->mixedCase()
                        ->numbers()
                        ->symbols()
                        ->uncompromised(),
                ],
            ], [
                'current_password.required' => 'Debe ingresar su contraseña actual.',
                'new_password.required' => 'Debe ingresar la nueva contraseña.',
                'new_password.confirmed' => 'La confirmación de contraseña no coincide.',
            ]);

            $user = $request->user();

            // Verificar password actual
            if (!Hash::check($request->current_password, $user->password)) {
                $this->logSecurityEvent('Intento de cambio de contraseña con password incorrecto', $request, [
                    'user_id' => $user->id,
                    'security_level' => 'medium',
                ]);
                
                return $this->errorResponse('La contraseña actual es incorrecta', 422);
            }

            // Verificar que la nueva contraseña sea diferente
            if (Hash::check($request->new_password, $user->password)) {
                return $this->errorResponse('La nueva contraseña debe ser diferente a la actual', 422);
            }

            // Actualizar contraseña
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            // Revocar todos los tokens excepto el actual por seguridad
            $currentTokenId = $user->currentAccessToken()->id;
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();

            // Log de auditoría médica
            $this->logMedicalActivity('Cambio de contraseña de personal médico', 'users', $user->id, $request, [
                'security_action' => true,
                'tokens_revoked' => true,
            ]);

            LogAuditoria::logActivity(
                'password_changed',
                'users',
                $user->id,
                $user->id,
                null,
                ['user_role' => $user->rol, 'clinic_system' => true],
                $request->ip()
            );

            return $this->successResponse(null, 'Contraseña actualizada exitosamente. Otras sesiones han sido cerradas por seguridad.');
        }, 'cambio de contraseña');
    }

    /**
     * Revocar todas las sesiones del usuario (emergencia médica)
     */
    public function revokeAllTokens(Request $request)
    {
        return $this->handleMedicalAction(function () use ($request) {
            $user = $request->user();
            
            // Solo admins o el propio usuario pueden revocar tokens
            if ($user->rol !== 'admin' && $request->user()->id !== $user->id) {
                return $this->forbiddenResponse('Sin permisos para revocar sesiones de otro personal médico');
            }
            
            $tokensCount = $user->tokens()->count();
            $user->tokens()->delete();

            $this->logSecurityEvent('Revocación de emergencia de todas las sesiones', $request, [
                'user_id' => $user->id,
                'tokens_revoked' => $tokensCount,
                'security_level' => 'high',
                'action_type' => 'emergency_logout',
            ]);

            return $this->successResponse([
                'tokens_revoked' => $tokensCount,
            ], 'Todas las sesiones del personal médico han sido cerradas por seguridad');
        }, 'revocación de tokens de emergencia');
    }

    /**
     * Obtener permisos según el rol del usuario en la clínica
     */
    private function getUserPermissions(User $user): array
    {
        $permissions = [];
        
        switch ($user->rol) {
            case 'admin':
                $permissions = [
                    'manage_users', 'manage_doctors', 'manage_patients', 
                    'manage_appointments', 'manage_specialties', 'view_reports',
                    'system_config', 'audit_logs', 'backup_system'
                ];
                break;
                
            case 'doctor':
                $permissions = [
                    'view_patients', 'manage_own_appointments', 'view_medical_history',
                    'create_prescriptions', 'view_own_schedule', 'update_medical_notes'
                ];
                break;
                
            case 'secretaria':
                $permissions = [
                    'manage_appointments', 'view_patients', 'manage_patient_data',
                    'view_schedules', 'generate_reports'
                ];
                break;
                
            case 'operador':
                $permissions = [
                    'view_appointments', 'basic_patient_info', 'reception_tasks'
                ];
                break;
        }
        
        return $permissions;
    }
}
