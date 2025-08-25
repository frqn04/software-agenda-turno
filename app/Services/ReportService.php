<?php

namespace App\Services;

use App\Models\Turno;
use App\Models\Doctor;
use App\Models\Paciente;
use App\Models\Especialidad;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Servicio de reportes y estadísticas del sistema médico
 * Genera reportes analíticos para la administración
 */
class ReportService
{
    private const CACHE_TTL = 3600; // 1 hora

    /**
     * Reporte de turnos por período
     */
    public function getTurnosReport(string $fechaInicio, string $fechaFin, array $filters = []): array
    {
        $cacheKey = "turnos_report_" . md5($fechaInicio . $fechaFin . serialize($filters));
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($fechaInicio, $fechaFin, $filters) {
            $query = Turno::with(['doctor.especialidad', 'paciente'])
                          ->whereBetween('fecha', [$fechaInicio, $fechaFin]);

            // Aplicar filtros
            if (isset($filters['doctor_id'])) {
                $query->where('doctor_id', $filters['doctor_id']);
            }

            if (isset($filters['especialidad_id'])) {
                $query->whereHas('doctor', function ($q) use ($filters) {
                    $q->where('especialidad_id', $filters['especialidad_id']);
                });
            }

            if (isset($filters['estado'])) {
                $query->where('estado', $filters['estado']);
            }

            $turnos = $query->get();

            return [
                'periodo' => [
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin' => $fechaFin,
                ],
                'resumen' => [
                    'total_turnos' => $turnos->count(),
                    'programados' => $turnos->where('estado', 'programado')->count(),
                    'confirmados' => $turnos->where('estado', 'confirmado')->count(),
                    'completados' => $turnos->where('estado', 'completado')->count(),
                    'cancelados' => $turnos->where('estado', 'cancelado')->count(),
                    'no_asistio' => $turnos->where('estado', 'no_asistio')->count(),
                ],
                'por_especialidad' => $this->groupByEspecialidad($turnos),
                'por_doctor' => $this->groupByDoctor($turnos),
                'por_dia' => $this->groupByDay($turnos),
                'por_hora' => $this->groupByHour($turnos),
                'estadisticas_avanzadas' => [
                    'tasa_asistencia' => $this->calculateAttendanceRate($turnos),
                    'tasa_cancelacion' => $this->calculateCancellationRate($turnos),
                    'promedio_diario' => $this->calculateDailyAverage($turnos, $fechaInicio, $fechaFin),
                    'horarios_mas_solicitados' => $this->getMostRequestedHours($turnos),
                ],
            ];
        });
    }

    /**
     * Reporte de productividad de doctores
     */
    public function getDoctorsProductivityReport(string $fechaInicio, string $fechaFin): array
    {
        $cacheKey = "doctors_productivity_" . md5($fechaInicio . $fechaFin);
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($fechaInicio, $fechaFin) {
            $doctors = Doctor::with(['especialidad', 'turnos' => function ($query) use ($fechaInicio, $fechaFin) {
                $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
            }])->where('activo', true)->get();

            $report = [];
            
            foreach ($doctors as $doctor) {
                $turnos = $doctor->turnos;
                
                $report[] = [
                    'doctor' => [
                        'id' => $doctor->id,
                        'nombre' => $doctor->nombre . ' ' . $doctor->apellido,
                        'especialidad' => $doctor->especialidad->nombre ?? 'Sin especialidad',
                    ],
                    'estadisticas' => [
                        'total_turnos' => $turnos->count(),
                        'turnos_completados' => $turnos->where('estado', 'completado')->count(),
                        'turnos_cancelados' => $turnos->where('estado', 'cancelado')->count(),
                        'no_asistencias' => $turnos->where('estado', 'no_asistio')->count(),
                        'tasa_efectividad' => $this->calculateEffectivenessRate($turnos),
                        'pacientes_unicos' => $turnos->pluck('paciente_id')->unique()->count(),
                        'promedio_semanal' => $this->calculateWeeklyAverage($turnos, $fechaInicio, $fechaFin),
                        'horarios_preferidos' => $this->getDoctorPreferredHours($turnos),
                    ],
                ];
            }

            // Ordenar por total de turnos completados
            usort($report, function ($a, $b) {
                return $b['estadisticas']['turnos_completados'] <=> $a['estadisticas']['turnos_completados'];
            });

            return [
                'periodo' => [$fechaInicio, $fechaFin],
                'doctors' => $report,
                'resumen_general' => [
                    'total_doctors_activos' => $doctors->count(),
                    'promedio_turnos_por_doctor' => round($doctors->sum(function ($d) { return $d->turnos->count(); }) / max($doctors->count(), 1), 2),
                    'doctor_mas_productivo' => $report[0] ?? null,
                ],
            ];
        });
    }

    /**
     * Reporte de pacientes y demografía
     */
    public function getPatientsReport(array $filters = []): array
    {
        $cacheKey = "patients_report_" . md5(serialize($filters));
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters) {
            $query = Paciente::with(['turnos']);

            // Aplicar filtros
            if (isset($filters['activo'])) {
                $query->where('activo', $filters['activo']);
            }

            if (isset($filters['fecha_registro_desde'])) {
                $query->where('created_at', '>=', $filters['fecha_registro_desde']);
            }

            if (isset($filters['fecha_registro_hasta'])) {
                $query->where('created_at', '<=', $filters['fecha_registro_hasta']);
            }

            $pacientes = $query->get();

            return [
                'resumen' => [
                    'total_pacientes' => $pacientes->count(),
                    'pacientes_activos' => $pacientes->where('activo', true)->count(),
                    'pacientes_inactivos' => $pacientes->where('activo', false)->count(),
                    'nuevos_este_mes' => $pacientes->where('created_at', '>=', now()->startOfMonth())->count(),
                ],
                'demografia' => [
                    'distribucion_edad' => $this->getAgeDistribution($pacientes),
                    'distribucion_genero' => $this->getGenderDistribution($pacientes),
                    'pacientes_por_mes' => $this->getPatientsByMonth($pacientes),
                ],
                'actividad' => [
                    'pacientes_con_turnos' => $pacientes->filter(function ($p) { return $p->turnos->count() > 0; })->count(),
                    'pacientes_frecuentes' => $this->getFrequentPatients($pacientes),
                    'promedio_turnos_por_paciente' => $this->getAverageTurnosPerPatient($pacientes),
                ],
            ];
        });
    }

    /**
     * Reporte financiero (simulado)
     */
    public function getFinancialReport(string $fechaInicio, string $fechaFin): array
    {
        $cacheKey = "financial_report_" . md5($fechaInicio . $fechaFin);
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($fechaInicio, $fechaFin) {
            $turnos = Turno::with(['doctor.especialidad'])
                           ->whereBetween('fecha', [$fechaInicio, $fechaFin])
                           ->whereIn('estado', ['completado'])
                           ->get();

            // Valores simulados - en un sistema real vendrían de una tabla de precios
            $precios = [
                'Cardiología' => 15000,
                'Dermatología' => 12000,
                'Neurología' => 18000,
                'Pediatría' => 10000,
                'Ginecología' => 13000,
                'default' => 12000,
            ];

            $ingresos = [];
            $totalIngresos = 0;

            foreach ($turnos as $turno) {
                $especialidad = $turno->doctor->especialidad->nombre ?? 'General';
                $precio = $precios[$especialidad] ?? $precios['default'];
                
                $ingresos[] = [
                    'turno_id' => $turno->id,
                    'fecha' => $turno->fecha,
                    'especialidad' => $especialidad,
                    'doctor' => $turno->doctor->nombre . ' ' . $turno->doctor->apellido,
                    'precio' => $precio,
                ];
                
                $totalIngresos += $precio;
            }

            return [
                'periodo' => [$fechaInicio, $fechaFin],
                'resumen' => [
                    'total_ingresos' => $totalIngresos,
                    'total_consultas' => $turnos->count(),
                    'ingreso_promedio_consulta' => $turnos->count() > 0 ? round($totalIngresos / $turnos->count(), 2) : 0,
                    'ingresos_por_dia' => $this->getIncomeByDay($ingresos),
                ],
                'por_especialidad' => $this->getIncomeBySpecialty($ingresos),
                'por_doctor' => $this->getIncomeByDoctor($ingresos),
                'detalle' => $ingresos,
            ];
        });
    }

    /**
     * Dashboard ejecutivo
     */
    public function getExecutiveDashboard(): array
    {
        $cacheKey = "executive_dashboard_" . date('Y-m-d');
        
        return Cache::remember($cacheKey, 1800, function () { // 30 minutos
            $today = Carbon::today();
            $thisMonth = Carbon::now()->startOfMonth();
            $lastMonth = Carbon::now()->subMonth()->startOfMonth();

            return [
                'hoy' => [
                    'turnos_programados' => Turno::whereDate('fecha', $today)->whereIn('estado', ['programado', 'confirmado'])->count(),
                    'turnos_completados' => Turno::whereDate('fecha', $today)->where('estado', 'completado')->count(),
                    'turnos_cancelados' => Turno::whereDate('fecha', $today)->where('estado', 'cancelado')->count(),
                    'doctores_activos' => Doctor::where('activo', true)->count(),
                ],
                'este_mes' => [
                    'total_turnos' => Turno::where('created_at', '>=', $thisMonth)->count(),
                    'nuevos_pacientes' => Paciente::where('created_at', '>=', $thisMonth)->count(),
                    'tasa_asistencia' => $this->getMonthlyAttendanceRate($thisMonth),
                ],
                'comparacion_mes_anterior' => [
                    'turnos_variacion' => $this->calculateMonthlyVariation('turnos', $thisMonth, $lastMonth),
                    'pacientes_variacion' => $this->calculateMonthlyVariation('pacientes', $thisMonth, $lastMonth),
                ],
                'especialidades_mas_demandadas' => $this->getMostDemandedSpecialties(),
                'alertas' => $this->getSystemAlerts(),
            ];
        });
    }

    // Métodos auxiliares privados

    private function groupByEspecialidad($turnos): array
    {
        return $turnos->groupBy(function ($turno) {
            return $turno->doctor->especialidad->nombre ?? 'Sin especialidad';
        })->map(function ($group, $especialidad) {
            return [
                'especialidad' => $especialidad,
                'total' => $group->count(),
                'completados' => $group->where('estado', 'completado')->count(),
                'cancelados' => $group->where('estado', 'cancelado')->count(),
            ];
        })->values()->toArray();
    }

    private function groupByDoctor($turnos): array
    {
        return $turnos->groupBy('doctor_id')->map(function ($group) {
            $doctor = $group->first()->doctor;
            return [
                'doctor' => $doctor->nombre . ' ' . $doctor->apellido,
                'especialidad' => $doctor->especialidad->nombre ?? 'Sin especialidad',
                'total' => $group->count(),
                'completados' => $group->where('estado', 'completado')->count(),
            ];
        })->values()->toArray();
    }

    private function groupByDay($turnos): array
    {
        return $turnos->groupBy('fecha')->map(function ($group, $fecha) {
            return [
                'fecha' => $fecha,
                'total' => $group->count(),
                'completados' => $group->where('estado', 'completado')->count(),
            ];
        })->values()->toArray();
    }

    private function groupByHour($turnos): array
    {
        return $turnos->groupBy(function ($turno) {
            return Carbon::parse($turno->hora_inicio)->format('H:00');
        })->map(function ($group, $hora) {
            return [
                'hora' => $hora,
                'total' => $group->count(),
            ];
        })->values()->toArray();
    }

    private function calculateAttendanceRate($turnos): float
    {
        $total = $turnos->whereIn('estado', ['completado', 'no_asistio'])->count();
        $asistieron = $turnos->where('estado', 'completado')->count();
        
        return $total > 0 ? round(($asistieron / $total) * 100, 2) : 0;
    }

    private function calculateCancellationRate($turnos): float
    {
        $total = $turnos->count();
        $cancelados = $turnos->where('estado', 'cancelado')->count();
        
        return $total > 0 ? round(($cancelados / $total) * 100, 2) : 0;
    }

    private function calculateDailyAverage($turnos, string $fechaInicio, string $fechaFin): float
    {
        $days = Carbon::parse($fechaInicio)->diffInDays(Carbon::parse($fechaFin)) + 1;
        return $days > 0 ? round($turnos->count() / $days, 2) : 0;
    }

    private function getMostRequestedHours($turnos): array
    {
        return $turnos->groupBy(function ($turno) {
            return Carbon::parse($turno->hora_inicio)->format('H:00');
        })->map(function ($group) {
            return $group->count();
        })->sortDesc()->take(5)->toArray();
    }

    private function calculateEffectivenessRate($turnos): float
    {
        $total = $turnos->count();
        $completados = $turnos->where('estado', 'completado')->count();
        
        return $total > 0 ? round(($completados / $total) * 100, 2) : 0;
    }

    private function calculateWeeklyAverage($turnos, string $fechaInicio, string $fechaFin): float
    {
        $weeks = Carbon::parse($fechaInicio)->diffInWeeks(Carbon::parse($fechaFin)) + 1;
        return $weeks > 0 ? round($turnos->count() / $weeks, 2) : 0;
    }

    private function getDoctorPreferredHours($turnos): array
    {
        return $turnos->groupBy(function ($turno) {
            return Carbon::parse($turno->hora_inicio)->format('H:00');
        })->map(function ($group) {
            return $group->count();
        })->sortDesc()->take(3)->keys()->toArray();
    }

    private function getAgeDistribution($pacientes): array
    {
        $ranges = [
            '0-18' => 0, '19-30' => 0, '31-45' => 0, 
            '46-60' => 0, '61-75' => 0, '76+' => 0
        ];

        foreach ($pacientes as $paciente) {
            if ($paciente->fecha_nacimiento) {
                $age = Carbon::parse($paciente->fecha_nacimiento)->age;
                
                if ($age <= 18) $ranges['0-18']++;
                elseif ($age <= 30) $ranges['19-30']++;
                elseif ($age <= 45) $ranges['31-45']++;
                elseif ($age <= 60) $ranges['46-60']++;
                elseif ($age <= 75) $ranges['61-75']++;
                else $ranges['76+']++;
            }
        }

        return $ranges;
    }

    private function getGenderDistribution($pacientes): array
    {
        return [
            'masculino' => $pacientes->where('genero', 'M')->count(),
            'femenino' => $pacientes->where('genero', 'F')->count(),
            'otro' => $pacientes->whereNotIn('genero', ['M', 'F'])->count(),
        ];
    }

    private function getPatientsByMonth($pacientes): array
    {
        return $pacientes->groupBy(function ($paciente) {
            return Carbon::parse($paciente->created_at)->format('Y-m');
        })->map(function ($group) {
            return $group->count();
        })->toArray();
    }

    private function getFrequentPatients($pacientes): array
    {
        return $pacientes->filter(function ($paciente) {
            return $paciente->turnos->count() >= 5;
        })->map(function ($paciente) {
            return [
                'nombre' => $paciente->nombre . ' ' . $paciente->apellido,
                'total_turnos' => $paciente->turnos->count(),
            ];
        })->sortByDesc('total_turnos')->values()->toArray();
    }

    private function getAverageTurnosPerPatient($pacientes): float
    {
        $totalTurnos = $pacientes->sum(function ($paciente) {
            return $paciente->turnos->count();
        });

        return $pacientes->count() > 0 ? round($totalTurnos / $pacientes->count(), 2) : 0;
    }

    private function getIncomeByDay($ingresos): array
    {
        $byDay = [];
        foreach ($ingresos as $ingreso) {
            $fecha = $ingreso['fecha'];
            if (!isset($byDay[$fecha])) {
                $byDay[$fecha] = 0;
            }
            $byDay[$fecha] += $ingreso['precio'];
        }
        return $byDay;
    }

    private function getIncomeBySpecialty($ingresos): array
    {
        $bySpecialty = [];
        foreach ($ingresos as $ingreso) {
            $especialidad = $ingreso['especialidad'];
            if (!isset($bySpecialty[$especialidad])) {
                $bySpecialty[$especialidad] = ['total' => 0, 'consultas' => 0];
            }
            $bySpecialty[$especialidad]['total'] += $ingreso['precio'];
            $bySpecialty[$especialidad]['consultas']++;
        }
        return $bySpecialty;
    }

    private function getIncomeByDoctor($ingresos): array
    {
        $byDoctor = [];
        foreach ($ingresos as $ingreso) {
            $doctor = $ingreso['doctor'];
            if (!isset($byDoctor[$doctor])) {
                $byDoctor[$doctor] = ['total' => 0, 'consultas' => 0];
            }
            $byDoctor[$doctor]['total'] += $ingreso['precio'];
            $byDoctor[$doctor]['consultas']++;
        }
        return $byDoctor;
    }

    private function getMonthlyAttendanceRate($month): float
    {
        $turnos = Turno::where('created_at', '>=', $month)
                       ->whereIn('estado', ['completado', 'no_asistio'])
                       ->get();
        
        return $this->calculateAttendanceRate($turnos);
    }

    private function calculateMonthlyVariation(string $type, Carbon $currentMonth, Carbon $lastMonth): array
    {
        if ($type === 'turnos') {
            $current = Turno::where('created_at', '>=', $currentMonth)->count();
            $previous = Turno::whereBetween('created_at', [$lastMonth, $currentMonth])->count();
        } else {
            $current = Paciente::where('created_at', '>=', $currentMonth)->count();
            $previous = Paciente::whereBetween('created_at', [$lastMonth, $currentMonth])->count();
        }

        $variation = $previous > 0 ? (($current - $previous) / $previous) * 100 : 0;

        return [
            'actual' => $current,
            'anterior' => $previous,
            'variacion_porcentual' => round($variation, 2),
            'tendencia' => $variation > 0 ? 'ascendente' : ($variation < 0 ? 'descendente' : 'estable'),
        ];
    }

    private function getMostDemandedSpecialties(): array
    {
        return Turno::with('doctor.especialidad')
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->get()
                    ->groupBy(function ($turno) {
                        return $turno->doctor->especialidad->nombre ?? 'General';
                    })
                    ->map(function ($group) {
                        return $group->count();
                    })
                    ->sortDesc()
                    ->take(5)
                    ->toArray();
    }

    private function getSystemAlerts(): array
    {
        $alerts = [];

        // Verificar doctores sin turnos en la última semana
        $doctorsSinTurnos = Doctor::whereDoesntHave('turnos', function ($query) {
            $query->where('created_at', '>=', now()->subWeek());
        })->where('activo', true)->count();

        if ($doctorsSinTurnos > 0) {
            $alerts[] = [
                'tipo' => 'warning',
                'mensaje' => "{$doctorsSinTurnos} doctores sin turnos en la última semana",
                'accion_requerida' => 'Revisar disponibilidad de doctores',
            ];
        }

        // Verificar alta tasa de cancelaciones
        $cancelaciones = Turno::where('estado', 'cancelado')
                              ->where('created_at', '>=', now()->subDays(7))
                              ->count();
        
        $totalTurnos = Turno::where('created_at', '>=', now()->subDays(7))->count();
        
        if ($totalTurnos > 0 && ($cancelaciones / $totalTurnos) > 0.15) {
            $alerts[] = [
                'tipo' => 'error',
                'mensaje' => 'Alta tasa de cancelaciones (>' . round(($cancelaciones / $totalTurnos) * 100, 1) . '%)',
                'accion_requerida' => 'Investigar causas de cancelaciones',
            ];
        }

        return $alerts;
    }
}
