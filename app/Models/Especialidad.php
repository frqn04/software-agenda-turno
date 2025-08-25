<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo para gestionar las especialidades médicas
 * Maneja la información de especialidades y sus relaciones con doctores
 */
class Especialidad extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'especialidades';

    protected $fillable = [
        'nombre',
        'descripcion',
        'codigo',
        'duracion_cita_default',
        'precio_base',
        'requiere_autorizacion',
        'color_identificacion',
        'icono',
        'activo',
        'orden_visualizacion',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'requiere_autorizacion' => 'boolean',
        'duracion_cita_default' => 'integer', // en minutos
        'precio_base' => 'decimal:2',
        'orden_visualizacion' => 'integer',
    ];

    // Relaciones
    public function doctores()
    {
        return $this->hasMany(Doctor::class);
    }

    public function doctoresActivos()
    {
        return $this->hasMany(Doctor::class)->where('activo', true);
    }

    public function turnos()
    {
        return $this->hasManyThrough(Turno::class, Doctor::class);
    }

    // Scopes
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopeOrdenadas($query)
    {
        return $query->orderBy('orden_visualizacion')->orderBy('nombre');
    }

    public function scopeConDoctores($query)
    {
        return $query->whereHas('doctores');
    }

    public function scopeConDoctoresActivos($query)
    {
        return $query->whereHas('doctoresActivos');
    }

    public function scopePorNombre($query, $nombre)
    {
        return $query->where('nombre', 'like', '%' . $nombre . '%');
    }

    // Accessors
    public function getDuracionFormateadaAttribute()
    {
        if (!$this->duracion_cita_default) return 'No especificada';
        
        $horas = floor($this->duracion_cita_default / 60);
        $minutos = $this->duracion_cita_default % 60;
        
        if ($horas > 0) {
            return $horas . 'h ' . ($minutos > 0 ? $minutos . 'm' : '');
        }
        
        return $minutos . 'min';
    }

    public function getPrecioFormateadoAttribute()
    {
        return $this->precio_base ? '$' . number_format($this->precio_base, 2) : 'No especificado';
    }

    public function getColorHexAttribute()
    {
        return $this->color_identificacion ?: '#007bff';
    }

    // Métodos auxiliares
    public function tieneDoctoresActivos(): bool
    {
        return $this->doctoresActivos()->count() > 0;
    }

    public function getCantidadDoctores(): int
    {
        return $this->doctores()->count();
    }

    public function getCantidadDoctoresActivos(): int
    {
        return $this->doctoresActivos()->count();
    }

    public function getTurnosDelMes($mes = null, $anio = null): int
    {
        $mes = $mes ?: now()->month;
        $anio = $anio ?: now()->year;
        
        return $this->turnos()
            ->whereMonth('fecha', $mes)
            ->whereYear('fecha', $anio)
            ->count();
    }

    public function getEstadisticasTurnos($fechaInicio = null, $fechaFin = null): array
    {
        $query = $this->turnos();
        
        if ($fechaInicio && $fechaFin) {
            $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
        } else {
            // Por defecto, últimos 30 días
            $query->where('fecha', '>=', now()->subDays(30));
        }
        
        $total = $query->count();
        $realizados = $query->where('estado', Turno::ESTADO_REALIZADO)->count();
        $cancelados = $query->where('estado', Turno::ESTADO_CANCELADO)->count();
        
        return [
            'total' => $total,
            'realizados' => $realizados,
            'cancelados' => $cancelados,
            'programados' => $total - $realizados - $cancelados,
            'porcentaje_realizados' => $total > 0 ? round(($realizados / $total) * 100, 2) : 0,
            'porcentaje_cancelados' => $total > 0 ? round(($cancelados / $total) * 100, 2) : 0,
        ];
    }

    public function puedeSerEliminada(): bool
    {
        // No se puede eliminar si tiene doctores asociados o turnos registrados
        return $this->doctores()->count() === 0 && $this->turnos()->count() === 0;
    }

    public function activar(): bool
    {
        $this->activo = true;
        return $this->save();
    }

    public function desactivar(): bool
    {
        // Solo se puede desactivar si no tiene turnos programados a futuro
        $turnosFuturos = $this->turnos()
            ->where('fecha', '>', now())
            ->where('estado', Turno::ESTADO_PROGRAMADO)
            ->count();
        
        if ($turnosFuturos > 0) {
            return false; // No se puede desactivar
        }
        
        $this->activo = false;
        return $this->save();
    }

    public function getDoctoresPorHorario($diaSemana = null): array
    {
        $diaSemana = $diaSemana ?: now()->dayOfWeek;
        
        return $this->doctoresActivos()
            ->whereHas('horarios', function ($query) use ($diaSemana) {
                $query->where('day_of_week', $diaSemana)
                      ->where('is_active', true);
            })
            ->with(['horarios' => function ($query) use ($diaSemana) {
                $query->where('day_of_week', $diaSemana)
                      ->where('is_active', true)
                      ->orderBy('start_time');
            }])
            ->get()
            ->toArray();
    }

    /**
     * Obtiene las especialidades más populares basado en cantidad de turnos
     */
    public static function getMasPopulares($limite = 5): \Illuminate\Support\Collection
    {
        return static::withCount(['turnos' => function ($query) {
            $query->where('fecha', '>=', now()->subMonths(3)); // Últimos 3 meses
        }])
        ->orderBy('turnos_count', 'desc')
        ->limit($limite)
        ->get();
    }

    /**
     * Generar código único para la especialidad
     */
    public static function generarCodigo($nombre): string
    {
        // Tomar las primeras 3 letras del nombre
        $codigo = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $nombre), 0, 3));
        
        // Buscar si ya existe
        $contador = 1;
        $codigoOriginal = $codigo;
        
        while (static::where('codigo', $codigo)->exists()) {
            $codigo = $codigoOriginal . str_pad($contador, 2, '0', STR_PAD_LEFT);
            $contador++;
        }
        
        return $codigo;
    }

    /**
     * Boot method para eventos del modelo
     */
    protected static function boot()
    {
        parent::boot();

        // Generar código automáticamente si no se proporciona
        static::creating(function ($especialidad) {
            if (!$especialidad->codigo && $especialidad->nombre) {
                $especialidad->codigo = static::generarCodigo($especialidad->nombre);
            }
            
            // Establecer valores por defecto
            if (!$especialidad->duracion_cita_default) {
                $especialidad->duracion_cita_default = 30; // 30 minutos por defecto
            }
            
            if (!$especialidad->color_identificacion) {
                // Colores predefinidos para especialidades médicas
                $colores = [
                    '#007bff', '#28a745', '#dc3545', '#ffc107', 
                    '#17a2b8', '#6f42c1', '#fd7e14', '#20c997'
                ];
                $especialidad->color_identificacion = $colores[array_rand($colores)];
            }
            
            // Establecer orden de visualización
            if (!$especialidad->orden_visualizacion) {
                $ultimoOrden = static::max('orden_visualizacion') ?: 0;
                $especialidad->orden_visualizacion = $ultimoOrden + 1;
            }
        });
    }
}
