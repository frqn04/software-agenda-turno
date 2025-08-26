<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Middleware para verificar roles del personal de clínica
 * Roles: admin, doctor, secretaria, operador
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso no autorizado - inicie sesión',
                'error_code' => 'NOT_AUTHENTICATED'
            ], 401);
        }

        if (!in_array($request->user()->rol, $roles)) {
            Log::warning('Intento de acceso no autorizado en clínica', [
                'user_id' => $request->user()->id,
                'user_name' => $request->user()->name,
                'user_role' => $request->user()->rol,
                'required_roles' => $roles,
                'endpoint' => $request->path(),
                'ip_address' => $request->ip(),
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Rol insuficiente para acceder a este recurso de la clínica',
                'required_roles' => $roles,
                'current_role' => $request->user()->rol,
                'error_code' => 'INSUFFICIENT_ROLE'
            ], 403);
        }

        return $next($request);
    }
}
