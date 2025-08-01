<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use App\Models\Doctor;
use App\Models\Especialidad;
use App\Models\Turno;
use Carbon\Carbon;

class CacheService
{
    const CACHE_TTL = 3600; // 1 hora
    const DOCTORS_CACHE_KEY = 'doctors_active';
    const SPECIALTIES_CACHE_KEY = 'specialties_active';

    /**
     * Obtener doctores activos desde cache
     */
    public function getActiveDoctors(): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember(self::DOCTORS_CACHE_KEY, self::CACHE_TTL, function () {
            return Doctor::with(['especialidad', 'user'])
                ->where('activo', true)
                ->get();
        });
    }

    /**
     * Obtener especialidades activas desde cache
     */
    public function getActiveSpecialties(): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember(self::SPECIALTIES_CACHE_KEY, self::CACHE_TTL, function () {
            return Especialidad::where('activo', true)
                ->orderBy('nombre')
                ->get();
        });
    }

    /**
     * Obtener horarios disponibles para un doctor en una fecha
     */
    public function getDoctorAvailableSlots(int $doctorId, string $date): array
    {
        $cacheKey = "doctor_{$doctorId}_slots_{$date}";
        
        return Cache::remember($cacheKey, 1800, function () use ($doctorId, $date) { // 30 minutos
            $doctor = Doctor::with('contracts')->findOrFail($doctorId);
            $dateCarbon = Carbon::parse($date);

            // Verificar que sea día laboral
            if ($dateCarbon->isWeekend()) {
                return [];
            }

            // Obtener turnos ocupados para esa fecha
            $ocupados = Turno::where('doctor_id', $doctorId)
                ->whereDate('fecha', $date)
                ->whereIn('estado', ['programado', 'confirmado'])
                ->pluck('hora_inicio')
                ->map(function ($hora) {
                    return Carbon::parse($hora)->format('H:i');
                })
                ->toArray();

            // Generar slots disponibles (8:00 a 18:00, cada 30 minutos)
            $disponibles = [];
            $inicio = Carbon::parse('08:00');
            $fin = Carbon::parse('18:00');

            while ($inicio < $fin) {
                $horaString = $inicio->format('H:i');
                
                if (!in_array($horaString, $ocupados)) {
                    $disponibles[] = $horaString;
                }
                
                $inicio->addMinutes(30);
            }

            return $disponibles;
        });
    }

    /**
     * Limpiar cache relacionado con doctores
     */
    public function clearDoctorsCache(): void
    {
        Cache::forget(self::DOCTORS_CACHE_KEY);
        
        // Limpiar también cache de slots de doctores
        $doctors = Doctor::pluck('id');
        foreach ($doctors as $doctorId) {
            Cache::forget("doctor_{$doctorId}_slots_*");
        }
    }

    /**
     * Limpiar cache relacionado con especialidades
     */
    public function clearSpecialtiesCache(): void
    {
        Cache::forget(self::SPECIALTIES_CACHE_KEY);
    }

    /**
     * Limpiar cache de slots para un doctor específico
     */
    public function clearDoctorSlotsCache(int $doctorId): void
    {
        // Buscar todas las keys que coincidan con el patrón
        $pattern = "doctor_{$doctorId}_slots_*";
        $keys = Cache::getStore()->getRedis()->keys($pattern);
        
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Limpiar todo el cache de la aplicación
     */
    public function clearAllCache(): void
    {
        Cache::flush();
    }

    /**
     * Obtener estadísticas de cache
     */
    public function getCacheStats(): array
    {
        return [
            'doctors_cached' => Cache::has(self::DOCTORS_CACHE_KEY),
            'specialties_cached' => Cache::has(self::SPECIALTIES_CACHE_KEY),
            'cache_driver' => config('cache.default'),
            'last_cleared' => Cache::get('cache_last_cleared', 'Never'),
        ];
    }
}
