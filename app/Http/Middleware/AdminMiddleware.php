<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para verificar permisos de administrador
 * Sistema interno - solo personal de clínica dental
 */
class AdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || $request->user()->rol !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Acceso restringido a administradores de la clínica',
                'error_code' => 'INSUFFICIENT_PRIVILEGES'
            ], 403);
        }

        return $next($request);
    }
}
