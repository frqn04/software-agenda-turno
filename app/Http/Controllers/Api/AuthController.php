<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\StoreUserRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $key = 'login:' . $request->throttleKey();
        
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            
            Log::warning('Login rate limit exceeded', [
                'email' => $request->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => "Demasiados intentos de login. Intente nuevamente en {$seconds} segundos."
            ], 429);
        }

        $user = User::where('email', $request->email)
                   ->where('activo', true)
                   ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($key, 300);
            
            Log::warning('Failed login attempt', [
                'email' => $request->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'user_exists' => $user !== null,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

        RateLimiter::clear($key);

        $user->tokens()->delete();

        $token = $user->createToken(
            'auth_token',
            ['*'],
            now()->addHours(config('sanctum.expiration', 24))
        )->plainTextToken;

        Log::info('Successful login', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login exitoso',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'rol' => $user->rol,
                'doctor_id' => $user->doctor_id,
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        
        Log::info('User logout', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
        ]);

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout exitoso'
        ]);
    }

    public function register(StoreUserRequest $request)
    {
        $user = User::create($request->validated());

        Log::info('New user created', [
            'created_by' => $request->user()->id,
            'new_user_id' => $user->id,
            'email' => $user->email,
            'rol' => $user->rol,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'rol' => $user->rol,
                'doctor_id' => $user->doctor_id,
                'activo' => $user->activo,
            ]
        ], 201);
    }

    public function user(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'rol' => $user->rol,
                'doctor_id' => $user->doctor_id,
                'activo' => $user->activo,
                'last_login' => $user->updated_at,
            ]
        ]);
    }

    public function refreshToken(Request $request)
    {
        $user = $request->user();
        
        $user->currentAccessToken()->delete();
        
        $token = $user->createToken(
            'auth_token',
            ['*'],
            now()->addHours(config('sanctum.expiration', 24))
        )->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'message' => 'Token renovado exitosamente'
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        // Verificar password actual
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'La contraseña actual es incorrecta'
            ], 422);
        }

        // Verificar que la nueva contraseña sea diferente
        if (Hash::check($request->new_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'La nueva contraseña debe ser diferente a la actual'
            ], 422);
        }

        // Actualizar contraseña
        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        // Revocar todos los tokens excepto el actual
        $currentTokenId = $user->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        // Log de auditoría
        LogAuditoria::logActivity(
            'password_changed',
            'users',
            $user->id,
            $user->id,
            null,
            ['user_id' => $user->id],
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'message' => 'Contraseña actualizada exitosamente'
        ]);
    }

    public function revokeAllTokens(Request $request)
    {
        $user = $request->user();
        
        if (!$user->can('manageSystem', User::class)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para esta acción'
            ], 403);
        }
        
        $user->tokens()->delete();

        Log::warning('All tokens revoked by user', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Todas las sesiones han sido cerradas'
        ]);
    }
}
