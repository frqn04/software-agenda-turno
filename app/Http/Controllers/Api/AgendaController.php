<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Turno;
use Illuminate\Http\Request;

class AgendaController extends Controller
{
    public function porDoctor(Doctor $doctor, Request $request)
    {
        $fecha = $request->get('fecha', now()->format('Y-m-d'));
        
        $turnos = Turno::with(['paciente', 'doctor'])
            ->where('doctor_id', $doctor->id)
            ->where('fecha', $fecha)
            ->orderBy('hora_inicio')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $turnos
        ]);
    }

    public function porFecha(Request $request)
    {
        $fecha = $request->route('fecha');
        
        $turnos = Turno::with(['paciente', 'doctor'])
            ->where('fecha', $fecha)
            ->orderBy('hora_inicio')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $turnos
        ]);
    }

    public function disponibilidad(Request $request)
    {
        $doctorId = $request->get('doctor_id');
        $fecha = $request->get('fecha');

        if (!$doctorId || !$fecha) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor y fecha son requeridos'
            ], 400);
        }

        $turnos = Turno::where('doctor_id', $doctorId)
            ->where('fecha', $fecha)
            ->where('estado', 'programado')
            ->pluck('hora_inicio');

        $horarios = [];
        
        // Horarios mañana: 8:00 - 12:00
        for ($hora = 8; $hora < 12; $hora++) {
            for ($minutos = 0; $minutos < 60; $minutos += 30) {
                $tiempo = sprintf('%02d:%02d', $hora, $minutos);
                $horarios[] = [
                    'hora' => $tiempo,
                    'disponible' => !$turnos->contains($tiempo),
                    'periodo' => 'mañana'
                ];
            }
        }

        // Horarios tarde: 14:00 - 18:00
        for ($hora = 14; $hora < 18; $hora++) {
            for ($minutos = 0; $minutos < 60; $minutos += 30) {
                $tiempo = sprintf('%02d:%02d', $hora, $minutos);
                $horarios[] = [
                    'hora' => $tiempo,
                    'disponible' => !$turnos->contains($tiempo),
                    'periodo' => 'tarde'
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $horarios
        ]);
    }

    public function generarPDF(Request $request)
    {
        $doctorId = $request->get('doctor_id');
        $fecha = $request->get('fecha', now()->format('Y-m-d'));

        if (!$doctorId) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor es requerido'
            ], 400);
        }

        try {
            $doctor = Doctor::with('especialidad')->findOrFail($doctorId);
            
            $turnos = Turno::with(['paciente'])
                ->where('doctor_id', $doctorId)
                ->where('fecha', $fecha)
                ->orderBy('hora_inicio')
                ->get();

            // Por ahora devolvemos JSON, después podemos implementar PDF
            return response()->json([
                'success' => true,
                'data' => [
                    'doctor' => $doctor,
                    'fecha' => $fecha,
                    'turnos' => $turnos,
                    'fecha_formatted' => \Carbon\Carbon::parse($fecha)->format('d/m/Y')
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generando reporte: ' . $e->getMessage()
            ], 500);
        }
    }
}
