<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

/**
 * Modelo para gestionar doctores del sistema médico
 * Maneja información profesional, especialidades, contratos y horarios
 */
class Doctor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'doctores';

    protected $fillable = [
        'nombre',
        'apellido',
        'dni',
        'matricula',
        'especialidad_id',
        'telefono',
        'telefono_emergencia',
        'email',
        'direccion',
        'fecha_nacimiento',
        'sexo',
        'titulo_profesional',
        'universidad',
        'anio_graduacion',
        'numero_colegio_medico',
        'observaciones',
        'foto_perfil',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'fecha_nacimiento' => 'date',
        'anio_graduacion' => 'integer',
    ];

    // Relaciones
    public function especialidad()
    {
        return $this->belongsTo(Especialidad::class);
    }

    public function contratos()
    {
        return $this->hasMany(DoctorContract::class);
    }

    public function contratoActivo()
    {
        return $this->hasOne(DoctorContract::class)
            ->where('is_active', true)
            ->where('fecha_inicio', '<=', now())
            ->where('fecha_fin', '>=', now());
    }

    public function horarios()
    {
        return $this->hasMany(DoctorScheduleSlot::class);
    }

    public function horariosActivos()
    {
        return $this->hasMany(DoctorScheduleSlot::class)->where('is_active', true);
    }

    public function turnos()
    {
        return $this->hasMany(Turno::class);
    }

    public function turnosHoy()
    {
        return $this->hasMany(Turno::class)
            ->whereDate('fecha', today())
            ->orderBy('hora_inicio');
    }

    public function turnosProgramados()
    {
        return $this->hasMany(Turno::class)
            ->where('estado', Turno::ESTADO_PROGRAMADO)
            ->where('fecha', '>=', now())
            ->orderBy('fecha')
            ->orderBy('hora_inicio');
    }

    public function historiaClinicas()
    {
        return $this->hasMany(HistoriaClinica::class);
    }

    public function evoluciones()
    {
        return $this->hasMany(Evolucion::class);
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorEspecialidad($query, $especialidadId)
    {
        return $query->where('especialidad_id', $especialidadId);
    }

    public function scopeConContratoActivo($query)
    {
        return $query->whereHas('contratoActivo');
    }

    public function scopeConHorarios($query)
    {
        return $query->whereHas('horariosActivos');
    }

    public function scopeDisponibleEn($query, $fecha, $hora = null)
    {
        $diaSemana = Carbon::parse($fecha)->dayOfWeek;
        
        $query->whereHas('horariosActivos', function ($q) use ($diaSemana, $hora) {
            $q->where('day_of_week', $diaSemana);
            
            if ($hora) {
                $q->where('start_time', '<=', $hora)
                  ->where('end_time', '>', $hora);
            }
        });
        
        // Verificar que no tenga turno en esa fecha/hora
        if ($hora) {
            $query->whereDoesntHave('turnos', function ($q) use ($fecha, $hora) {
                $q->where('fecha', $fecha)
                  ->where('hora_inicio', $hora)
                  ->where('estado', Turno::ESTADO_PROGRAMADO);
            });
        }
        
        return $query;
    }

    public function scopePorNombre($query, $nombre)
    {
        return $query->where(function ($q) use ($nombre) {
            $q->where('nombre', 'like', '%' . $nombre . '%')
              ->orWhere('apellido', 'like', '%' . $nombre . '%')
              ->orWhereRaw("CONCAT(nombre, ' ', apellido) LIKE ?", ['%' . $nombre . '%']);
        });
    }

    // Accessors
    public function getNombreCompletoAttribute()
    {
        return trim($this->nombre . ' ' . $this->apellido);
    }

    public function getNombreProfesionalAttribute()
    {
        $titulo = $this->titulo_profesional ?: 'Dr.';
        return $titulo . ' ' . $this->nombre_completo;
    }

    public function getEdadAttribute()
    {
        return $this->fecha_nacimiento ? $this->fecha_nacimiento->age : null;
    }

    public function getAniosExperienciaAttribute()
    {
        return $this->anio_graduacion ? now()->year - $this->anio_graduacion : null;
    }

    public function getFotoPerfilUrlAttribute()
    {
        if ($this->foto_perfil) {
            return asset('storage/doctores/' . $this->foto_perfil);
        }
        
        // Imagen por defecto
        return asset('images/default-doctor.png');
    }

    // Métodos auxiliares
    public function tieneContratoActivo($fecha = null): bool
    {
        $fecha = $fecha ?? now()->format('Y-m-d');
        
        return $this->contratos()
            ->where('is_active', true)
            ->where('fecha_inicio', '<=', $fecha)
            ->where('fecha_fin', '>=', $fecha)
            ->exists();
    }

    public function estaDisponibleEn($fecha, $hora): bool
    {
        $diaSemana = Carbon::parse($fecha)->dayOfWeek;
        
        // Verificar horario laboral
        $tieneHorario = $this->horariosActivos()
            ->where('day_of_week', $diaSemana)
            ->where('start_time', '<=', $hora)
            ->where('end_time', '>', $hora)
            ->exists();
        
        if (!$tieneHorario) {
            return false;
        }
        
        // Verificar que no tenga turno ocupado
        $tieneOcupado = $this->turnos()
            ->where('fecha', $fecha)
            ->where('hora_inicio', $hora)
            ->where('estado', Turno::ESTADO_PROGRAMADO)
            ->exists();
        
        return !$tieneOcupado;
    }

    public function getHorariosDelDia($fecha): array
    {
        $diaSemana = Carbon::parse($fecha)->dayOfWeek;
        
        return $this->horariosActivos()
            ->where('day_of_week', $diaSemana)
            ->orderBy('start_time')
            ->get()
            ->map(function ($horario) {
                return $horario->getTimeSlots();
            })
            ->flatten(1)
            ->toArray();
    }

    public function getHorariosDisponibles($fecha): array
    {
        $horarios = $this->getHorariosDelDia($fecha);
        $turnosOcupados = $this->turnos()
            ->where('fecha', $fecha)
            ->where('estado', Turno::ESTADO_PROGRAMADO)
            ->pluck('hora_inicio')
            ->map(function ($hora) {
                return Carbon::parse($hora)->format('H:i');
            })
            ->toArray();
        
        return array_filter($horarios, function ($horario) use ($turnosOcupados) {
            return !in_array($horario['start'], $turnosOcupados);
        });
    }

    public function getCantidadTurnos($estado = null, $fechaInicio = null, $fechaFin = null): int
    {
        $query = $this->turnos();
        
        if ($estado) {
            $query->where('estado', $estado);
        }
        
        if ($fechaInicio && $fechaFin) {
            $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
        }
        
        return $query->count();
    }

    public function getTurnosDelDia($fecha = null): \Illuminate\Database\Eloquent\Collection
    {
        $fecha = $fecha ?? today();
        
        return $this->turnos()
            ->where('fecha', $fecha)
            ->with(['paciente'])
            ->orderBy('hora_inicio')
            ->get();
    }

    public function getProximoTurno(): ?Turno
    {
        return $this->turnos()
            ->where('estado', Turno::ESTADO_PROGRAMADO)
            ->where('fecha', '>=', now()->toDateString())
            ->where(function ($query) {
                $query->where('fecha', '>', now()->toDateString())
                    ->orWhere(function ($q) {
                        $q->where('fecha', now()->toDateString())
                          ->where('hora_inicio', '>=', now()->toTimeString());
                    });
            })
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->first();
    }

    public function getEstadisticasTurnos($fechaInicio = null, $fechaFin = null): array
    {
        $query = $this->turnos();
        
        if ($fechaInicio && $fechaFin) {
            $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
        } else {
            // Por defecto, último mes
            $query->where('fecha', '>=', now()->subMonth());
        }
        
        $total = $query->count();
        $realizados = $query->where('estado', Turno::ESTADO_REALIZADO)->count();
        $cancelados = $query->where('estado', Turno::ESTADO_CANCELADO)->count();
        $noAsistio = $query->where('estado', Turno::ESTADO_NO_ASISTIO)->count();
        
        return [
            'total' => $total,
            'realizados' => $realizados,
            'cancelados' => $cancelados,
            'no_asistio' => $noAsistio,
            'programados' => $total - $realizados - $cancelados - $noAsistio,
            'porcentaje_efectividad' => $total > 0 ? round(($realizados / $total) * 100, 2) : 0,
        ];
    }

    public function puedeSerDesactivado(): bool
    {
        // No se puede desactivar si tiene turnos programados a futuro
        return !$this->turnosProgramados()->exists();
    }

    public function desactivar($motivo = null): bool
    {
        if (!$this->puedeSerDesactivado()) {
            return false;
        }
        
        $this->activo = false;
        
        // Desactivar también los horarios
        $this->horarios()->update(['is_active' => false]);
        
        // Desactivar contratos
        $this->contratos()->where('is_active', true)->update(['is_active' => false]);
        
        if ($motivo) {
            $this->observaciones = ($this->observaciones ? $this->observaciones . "\n" : '') . 
                                 "Desactivado: " . $motivo . " - " . now()->format('d/m/Y H:i');
        }
        
        return $this->save();
    }

    public function activar(): bool
    {
        $this->activo = true;
        return $this->save();
    }

    public function validarDatos(): array
    {
        $errores = [];
        
        // Validar DNI único
        $dniExistente = static::where('dni', $this->dni)
            ->where('id', '!=', $this->id)
            ->exists();
        
        if ($dniExistente) {
            $errores[] = 'Ya existe un doctor con este DNI';
        }
        
        // Validar matrícula única
        $matriculaExistente = static::where('matricula', $this->matricula)
            ->where('id', '!=', $this->id)
            ->exists();
        
        if ($matriculaExistente) {
            $errores[] = 'Ya existe un doctor con esta matrícula';
        }
        
        // Validar email único si se proporciona
        if ($this->email) {
            $emailExistente = static::where('email', $this->email)
                ->where('id', '!=', $this->id)
                ->exists();
            
            if ($emailExistente) {
                $errores[] = 'Ya existe un doctor con este email';
            }
        }
        
        // Validar año de graduación
        if ($this->anio_graduacion && ($this->anio_graduacion > now()->year || $this->anio_graduacion < 1950)) {
            $errores[] = 'El año de graduación parece incorrecto';
        }
        
        return $errores;
    }

    public function crearContrato(array $datosContrato): DoctorContract
    {
        // Desactivar contratos anteriores
        $this->contratos()->where('is_active', true)->update(['is_active' => false]);
        
        // Crear nuevo contrato
        $datosContrato['doctor_id'] = $this->id;
        $datosContrato['is_active'] = true;
        
        return $this->contratos()->create($datosContrato);
    }

    public function agregarHorario(array $datosHorario): DoctorScheduleSlot
    {
        $datosHorario['doctor_id'] = $this->id;
        return $this->horarios()->create($datosHorario);
    }

    /**
     * Boot method para eventos del modelo
     */
    protected static function boot()
    {
        parent::boot();

        // Normalizar datos antes de guardar
        static::saving(function ($doctor) {
            // Normalizar nombres
            $doctor->nombre = ucwords(strtolower(trim($doctor->nombre)));
            $doctor->apellido = ucwords(strtolower(trim($doctor->apellido)));
            
            // Normalizar DNI (solo números)
            $doctor->dni = preg_replace('/[^0-9]/', '', $doctor->dni);
            
            // Normalizar matrícula
            if ($doctor->matricula) {
                $doctor->matricula = strtoupper(trim($doctor->matricula));
            }
            
            // Normalizar teléfonos
            if ($doctor->telefono) {
                $doctor->telefono = preg_replace('/[^0-9\-\+\(\)\s]/', '', $doctor->telefono);
            }
            
            // Normalizar email
            if ($doctor->email) {
                $doctor->email = strtolower(trim($doctor->email));
            }
        });
    }
}
