<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

class ValidateBusinessHours
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Solo validar en endpoints de creación de turnos
        if (!$request->routeIs('turnos.store') && !$request->routeIs('turnos.update')) {
            return $next($request);
        }

        if ($request->has('fecha_hora')) {
            $fechaHora = Carbon::parse($request->fecha_hora);
            
            // Validar que no sea fin de semana
            if ($fechaHora->isWeekend()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pueden programar turnos los fines de semana',
                    'errors' => [
                        'fecha_hora' => ['Los turnos solo se pueden programar de lunes a viernes']
                    ]
                ], 422);
            }

            // Validar horario de atención (8:00 a 18:00)
            $hora = $fechaHora->hour;
            if ($hora < 8 || $hora >= 18) {
                return response()->json([
                    'success' => false,
                    'message' => 'Horario fuera del rango de atención',
                    'errors' => [
                        'fecha_hora' => ['Los turnos solo se pueden programar entre las 8:00 y 18:00 horas']
                    ]
                ], 422);
            }
        }

        return $next($request);
    }
}
