<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Force Accept header for API routes
        if ($request->is('api/*')) {
            $request->headers->set('Accept', 'application/json');
        }

        $response = $next($request);

        // Ensure JSON response for API routes
        if ($request->is('api/*') && !$response instanceof JsonResponse) {
            if ($response->getStatusCode() >= 400) {
                return response()->json([
                    'success' => false,
                    'message' => $this->getStatusMessage($response->getStatusCode()),
                    'status_code' => $response->getStatusCode()
                ], $response->getStatusCode());
            }
        }

        return $response;
    }

    /**
     * Get appropriate message for status code
     */
    private function getStatusMessage(int $statusCode): string
    {
        return match($statusCode) {
            400 => 'Solicitud incorrecta',
            401 => 'No autorizado',
            403 => 'Acceso denegado',
            404 => 'Recurso no encontrado',
            405 => 'Método no permitido',
            422 => 'Datos de validación incorrectos',
            429 => 'Demasiadas solicitudes',
            500 => 'Error interno del servidor',
            503 => 'Servicio no disponible',
            default => 'Error en la solicitud'
        };
    }
}
