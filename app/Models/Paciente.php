<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

/**
 * Modelo para gestionar pacientes del sistema médico
 * Maneja información personal, contacto y relaciones médicas
 */
class Paciente extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre',
        'apellido',
        'dni',
        'fecha_nacimiento',
        'sexo',
        'telefono',
        'telefono_emergencia',
        'email',
        'direccion',
        'ciudad',
        'provincia',
        'codigo_postal',
        'profesion',
        'estado_civil',
        'numero_afiliado',
        'obra_social',
        'contacto_emergencia_nombre',
        'contacto_emergencia_telefono',
        'contacto_emergencia_relacion',
        'observaciones',
        'activo',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'activo' => 'boolean',
    ];

    // Constantes para sexo
    const SEXO_MASCULINO = 'M';
    const SEXO_FEMENINO = 'F';
    const SEXO_OTRO = 'O';

    public static function getSexos(): array
    {
        return [
            self::SEXO_MASCULINO => 'Masculino',
            self::SEXO_FEMENINO => 'Femenino',
            self::SEXO_OTRO => 'Otro',
        ];
    }

    // Constantes para estado civil
    const ESTADO_CIVIL_SOLTERO = 'soltero';
    const ESTADO_CIVIL_CASADO = 'casado';
    const ESTADO_CIVIL_DIVORCIADO = 'divorciado';
    const ESTADO_CIVIL_VIUDO = 'viudo';
    const ESTADO_CIVIL_UNION_CONVIVENCIAL = 'union_convivencial';

    public static function getEstadosCiviles(): array
    {
        return [
            self::ESTADO_CIVIL_SOLTERO => 'Soltero/a',
            self::ESTADO_CIVIL_CASADO => 'Casado/a',
            self::ESTADO_CIVIL_DIVORCIADO => 'Divorciado/a',
            self::ESTADO_CIVIL_VIUDO => 'Viudo/a',
            self::ESTADO_CIVIL_UNION_CONVIVENCIAL => 'Unión Convivencial',
        ];
    }

    // Relaciones
    public function historiaClinicas()
    {
        return $this->hasMany(HistoriaClinica::class);
    }

    public function historiaClinicaActiva()
    {
        return $this->hasOne(HistoriaClinica::class)
            ->where('activa', true)
            ->where('estado', HistoriaClinica::ESTADO_ACTIVA);
    }

    public function turnos()
    {
        return $this->hasMany(Turno::class);
    }

    public function turnosProgramados()
    {
        return $this->hasMany(Turno::class)
            ->where('estado', Turno::ESTADO_PROGRAMADO)
            ->where('fecha', '>=', now());
    }

    public function turnosHistorial()
    {
        return $this->hasMany(Turno::class)
            ->whereIn('estado', [Turno::ESTADO_REALIZADO, Turno::ESTADO_CANCELADO])
            ->orderBy('fecha', 'desc');
    }

    public function ultimoTurno()
    {
        return $this->hasOne(Turno::class)
            ->where('estado', Turno::ESTADO_REALIZADO)
            ->orderBy('fecha', 'desc')
            ->orderBy('hora_inicio', 'desc');
    }

    public function proximoTurno()
    {
        return $this->hasOne(Turno::class)
            ->where('estado', Turno::ESTADO_PROGRAMADO)
            ->where('fecha', '>=', now())
            ->orderBy('fecha')
            ->orderBy('hora_inicio');
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorNombre($query, $nombre)
    {
        return $query->where(function ($q) use ($nombre) {
            $q->where('nombre', 'like', '%' . $nombre . '%')
              ->orWhere('apellido', 'like', '%' . $nombre . '%')
              ->orWhereRaw("CONCAT(nombre, ' ', apellido) LIKE ?", ['%' . $nombre . '%']);
        });
    }

    public function scopePorDni($query, $dni)
    {
        return $query->where('dni', 'like', '%' . $dni . '%');
    }

    public function scopePorEdad($query, $edadMin, $edadMax = null)
    {
        $fechaMax = now()->subYears($edadMin)->endOfYear();
        $fechaMin = $edadMax ? now()->subYears($edadMax + 1)->startOfYear() : null;
        
        $query->where('fecha_nacimiento', '<=', $fechaMax);
        
        if ($fechaMin) {
            $query->where('fecha_nacimiento', '>=', $fechaMin);
        }
        
        return $query;
    }

    public function scopeConTurnosEn($query, $fechaInicio, $fechaFin)
    {
        return $query->whereHas('turnos', function ($q) use ($fechaInicio, $fechaFin) {
            $q->whereBetween('fecha', [$fechaInicio, $fechaFin]);
        });
    }

    public function scopeConHistoriaClinica($query)
    {
        return $query->whereHas('historiaClinicas');
    }

    // Accessors
    public function getNombreCompletoAttribute()
    {
        return trim($this->nombre . ' ' . $this->apellido);
    }

    public function getEdadAttribute()
    {
        return $this->fecha_nacimiento ? $this->fecha_nacimiento->age : null;
    }

    public function getSexoTextoAttribute()
    {
        $sexos = $this->getSexos();
        return $sexos[$this->sexo] ?? 'No especificado';
    }

    public function getEstadoCivilTextoAttribute()
    {
        $estados = $this->getEstadosCiviles();
        return $estados[$this->estado_civil] ?? 'No especificado';
    }

    public function getDireccionCompletaAttribute()
    {
        $direccion = $this->direccion;
        
        if ($this->ciudad) {
            $direccion .= ', ' . $this->ciudad;
        }
        
        if ($this->provincia) {
            $direccion .= ', ' . $this->provincia;
        }
        
        if ($this->codigo_postal) {
            $direccion .= ' (' . $this->codigo_postal . ')';
        }
        
        return $direccion;
    }

    public function getContactoEmergenciaCompletoAttribute()
    {
        if (!$this->contacto_emergencia_nombre) {
            return 'No especificado';
        }
        
        $contacto = $this->contacto_emergencia_nombre;
        
        if ($this->contacto_emergencia_relacion) {
            $contacto .= ' (' . $this->contacto_emergencia_relacion . ')';
        }
        
        if ($this->contacto_emergencia_telefono) {
            $contacto .= ' - ' . $this->contacto_emergencia_telefono;
        }
        
        return $contacto;
    }

    // Métodos auxiliares
    public function tieneHistoriaClinica(): bool
    {
        return $this->historiaClinicas()->exists();
    }

    public function tieneHistoriaClinicaActiva(): bool
    {
        return $this->historiaClinicaActiva !== null;
    }

    public function tieneTurnosProgramados(): bool
    {
        return $this->turnosProgramados()->exists();
    }

    public function getCantidadTurnos($estado = null): int
    {
        $query = $this->turnos();
        
        if ($estado) {
            $query->where('estado', $estado);
        }
        
        return $query->count();
    }

    public function getUltimaConsulta(): ?Turno
    {
        return $this->turnos()
            ->where('estado', Turno::ESTADO_REALIZADO)
            ->orderBy('fecha', 'desc')
            ->orderBy('hora_inicio', 'desc')
            ->first();
    }

    public function getDiasDesdeUltimaConsulta(): ?int
    {
        $ultimaConsulta = $this->getUltimaConsulta();
        
        if (!$ultimaConsulta) {
            return null;
        }
        
        return now()->diffInDays($ultimaConsulta->fecha);
    }

    public function esNuevoPaciente(): bool
    {
        // Considera nuevo si no tiene turnos realizados o si su primer turno fue hace menos de 30 días
        $primerTurno = $this->turnos()
            ->where('estado', Turno::ESTADO_REALIZADO)
            ->orderBy('fecha')
            ->first();
        
        if (!$primerTurno) {
            return true; // No tiene turnos realizados
        }
        
        return $primerTurno->fecha >= now()->subDays(30);
    }

    public function esMayorDeEdad(): bool
    {
        return $this->edad >= 18;
    }

    public function requiereAutorizacion(): bool
    {
        // Los menores de edad requieren autorización de tutor
        return !$this->esMayorDeEdad();
    }

    public function validarDatos(): array
    {
        $errores = [];
        
        // Validar DNI único
        $dniExistente = static::where('dni', $this->dni)
            ->where('id', '!=', $this->id)
            ->exists();
        
        if ($dniExistente) {
            $errores[] = 'Ya existe un paciente con este DNI';
        }
        
        // Validar edad coherente
        if ($this->fecha_nacimiento && $this->fecha_nacimiento > now()) {
            $errores[] = 'La fecha de nacimiento no puede ser futura';
        }
        
        if ($this->edad && $this->edad > 120) {
            $errores[] = 'La edad parece incorrecta';
        }
        
        // Validar email único si se proporciona
        if ($this->email) {
            $emailExistente = static::where('email', $this->email)
                ->where('id', '!=', $this->id)
                ->exists();
            
            if ($emailExistente) {
                $errores[] = 'Ya existe un paciente con este email';
            }
        }
        
        return $errores;
    }

    public function generarNumeroAfiliado(): string
    {
        // Generar número de afiliado único basado en DNI y fecha actual
        $anio = now()->year;
        $dni = str_pad($this->dni, 8, '0', STR_PAD_LEFT);
        $numero = substr($dni, -4) . $anio;
        
        // Verificar unicidad
        $contador = 1;
        $numeroOriginal = $numero;
        
        while (static::where('numero_afiliado', $numero)->where('id', '!=', $this->id)->exists()) {
            $numero = $numeroOriginal . str_pad($contador, 2, '0', STR_PAD_LEFT);
            $contador++;
        }
        
        return $numero;
    }

    public function crearHistoriaClinica($doctorId): HistoriaClinica
    {
        return $this->historiaClinicas()->create([
            'doctor_id' => $doctorId,
            'fecha_apertura' => now(),
            'estado' => HistoriaClinica::ESTADO_ACTIVA,
            'activa' => true,
        ]);
    }

    public function desactivar($motivo = null): bool
    {
        // Verificar que no tenga turnos programados
        if ($this->tieneTurnosProgramados()) {
            return false; // No se puede desactivar con turnos pendientes
        }
        
        $this->activo = false;
        
        if ($motivo) {
            $this->observaciones = ($this->observaciones ? $this->observaciones . "\n" : '') . 
                                 "Desactivado: " . $motivo . " - " . now()->format('d/m/Y H:i');
        }
        
        return $this->save();
    }

    public function getEstadisticasTurnos($fechaInicio = null, $fechaFin = null): array
    {
        $query = $this->turnos();
        
        if ($fechaInicio && $fechaFin) {
            $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
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
            'porcentaje_asistencia' => $total > 0 ? round((($realizados) / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Boot method para eventos del modelo
     */
    protected static function boot()
    {
        parent::boot();

        // Generar número de afiliado automáticamente
        static::creating(function ($paciente) {
            if (!$paciente->numero_afiliado && $paciente->dni) {
                $paciente->numero_afiliado = $paciente->generarNumeroAfiliado();
            }
        });

        // Normalizar datos antes de guardar
        static::saving(function ($paciente) {
            // Normalizar nombres
            $paciente->nombre = ucwords(strtolower(trim($paciente->nombre)));
            $paciente->apellido = ucwords(strtolower(trim($paciente->apellido)));
            
            // Normalizar DNI (solo números)
            $paciente->dni = preg_replace('/[^0-9]/', '', $paciente->dni);
            
            // Normalizar teléfonos
            if ($paciente->telefono) {
                $paciente->telefono = preg_replace('/[^0-9\-\+\(\)\s]/', '', $paciente->telefono);
            }
            
            if ($paciente->telefono_emergencia) {
                $paciente->telefono_emergencia = preg_replace('/[^0-9\-\+\(\)\s]/', '', $paciente->telefono_emergencia);
            }
            
            // Normalizar email
            if ($paciente->email) {
                $paciente->email = strtolower(trim($paciente->email));
            }
        });
    }
}
