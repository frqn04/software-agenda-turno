<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

/**
 * Middleware para validar horarios de atención de la clínica dental
 * Horarios: Lunes a Viernes de 8:00 a 18:00
 */
class ValidateBusinessHours
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Solo validar en endpoints de gestión de turnos
        if (!$request->routeIs('turnos.store') && !$request->routeIs('turnos.update')) {
            return $next($request);
        }

        if ($request->has('fecha_hora') || $request->has('fecha')) {
            $fechaHora = $request->has('fecha_hora') 
                ? Carbon::parse($request->fecha_hora)
                : Carbon::parse($request->fecha . ' ' . ($request->hora ?? '09:00'));
            
            // Validar que no sea fin de semana
            if ($fechaHora->isWeekend()) {
                return response()->json([
                    'success' => false,
                    'message' => 'La clínica no atiende los fines de semana',
                    'errors' => [
                        'fecha' => ['Los turnos solo se pueden programar de lunes a viernes']
                    ],
                    'horarios_atencion' => 'Lunes a Viernes: 8:00 - 18:00'
                ], 422);
            }

            // Validar horario de atención de la clínica (8:00 a 18:00)
            $hora = $fechaHora->hour;
            if ($hora < 8 || $hora >= 18) {
                return response()->json([
                    'success' => false,
                    'message' => 'Horario fuera del rango de atención de la clínica',
                    'errors' => [
                        'hora' => ['La clínica atiende de 8:00 a 18:00 horas']
                    ],
                    'horarios_atencion' => 'Lunes a Viernes: 8:00 - 18:00'
                ], 422);
            }

            // Validar que no sea un día feriado (opcional - se puede expandir)
            // TODO: Implementar validación de feriados según calendario de Argentina
        }

        return $next($request);
    }
}
