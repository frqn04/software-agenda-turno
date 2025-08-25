<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

/**
 * Modelo para gestionar contratos de doctores
 * Maneja períodos de trabajo, tipos de contrato y condiciones laborales
 */
class DoctorContract extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'doctor_id',
        'fecha_inicio',
        'fecha_fin',
        'tipo_contrato',
        'salario_base',
        'porcentaje_comision',
        'horas_semanales',
        'dias_vacaciones',
        'observaciones',
        'clausulas_especiales',
        'renovacion_automatica',
        'periodo_renovacion_meses',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'salario_base' => 'decimal:2',
        'porcentaje_comision' => 'decimal:2',
        'horas_semanales' => 'integer',
        'dias_vacaciones' => 'integer',
        'periodo_renovacion_meses' => 'integer',
        'renovacion_automatica' => 'boolean',
        'is_active' => 'boolean',
        'clausulas_especiales' => 'array',
    ];

    // Tipos de contrato
    const TIPO_PLANTA_PERMANENTE = 'planta_permanente';
    const TIPO_CONTRATADO = 'contratado';
    const TIPO_POR_CONSULTA = 'por_consulta';
    const TIPO_GUARDIA = 'guardia';
    const TIPO_HONORARIOS = 'honorarios';
    const TIPO_FREELANCE = 'freelance';

    public static function getTiposContrato(): array
    {
        return [
            self::TIPO_PLANTA_PERMANENTE => 'Planta Permanente',
            self::TIPO_CONTRATADO => 'Contratado',
            self::TIPO_POR_CONSULTA => 'Por Consulta',
            self::TIPO_GUARDIA => 'Guardia Médica',
            self::TIPO_HONORARIOS => 'Por Honorarios',
            self::TIPO_FREELANCE => 'Freelance',
        ];
    }

    // Relaciones
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVigentes($query, $fecha = null)
    {
        $fecha = $fecha ?? now();
        
        return $query->where('is_active', true)
            ->where('fecha_inicio', '<=', $fecha)
            ->where('fecha_fin', '>=', $fecha);
    }

    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo_contrato', $tipo);
    }

    public function scopeProximosAVencer($query, $dias = 30)
    {
        $fechaLimite = now()->addDays($dias);
        
        return $query->where('is_active', true)
            ->where('fecha_fin', '<=', $fechaLimite)
            ->where('fecha_fin', '>=', now());
    }

    public function scopeVencidos($query)
    {
        return $query->where('fecha_fin', '<', now());
    }

    public function scopeParaRenovacion($query)
    {
        return $query->where('renovacion_automatica', true)
            ->proximosAVencer();
    }

    // Accessors
    public function getTipoContratoTextoAttribute()
    {
        $tipos = $this->getTiposContrato();
        return $tipos[$this->tipo_contrato] ?? 'No especificado';
    }

    public function getDuracionEnMesesAttribute()
    {
        return $this->fecha_inicio->diffInMonths($this->fecha_fin);
    }

    public function getDuracionEnDiasAttribute()
    {
        return $this->fecha_inicio->diffInDays($this->fecha_fin);
    }

    public function getDiasRestantesAttribute()
    {
        if ($this->fecha_fin < now()) {
            return 0;
        }
        return now()->diffInDays($this->fecha_fin);
    }

    public function getPorcentajeTranscurridoAttribute()
    {
        $duracionTotal = $this->fecha_inicio->diffInDays($this->fecha_fin);
        $diasTranscurridos = $this->fecha_inicio->diffInDays(now());
        
        if ($duracionTotal == 0) return 100;
        
        return min(100, round(($diasTranscurridos / $duracionTotal) * 100, 2));
    }

    public function getSalarioFormateadoAttribute()
    {
        return $this->salario_base ? '$' . number_format($this->salario_base, 2) : 'No especificado';
    }

    // Métodos auxiliares
    public function isActiveAt($fecha): bool
    {
        $fecha = Carbon::parse($fecha);
        
        return $this->is_active && 
               $this->fecha_inicio <= $fecha && 
               $this->fecha_fin >= $fecha;
    }

    public function estaVigente(): bool
    {
        return $this->isActiveAt(now());
    }

    public function estaProximoAVencer($dias = 30): bool
    {
        return $this->estaVigente() && 
               $this->dias_restantes <= $dias && 
               $this->dias_restantes > 0;
    }

    public function estaVencido(): bool
    {
        return $this->fecha_fin < now();
    }

    public function puedeSerRenovado(): bool
    {
        return $this->estaProximoAVencer() || 
               ($this->estaVencido() && $this->renovacion_automatica);
    }

    public function puedeSerActivado(): bool
    {
        // Verificar que no haya otro contrato activo en las mismas fechas
        return !$this->doctor->contratos()
            ->where('id', '!=', $this->id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereBetween('fecha_inicio', [$this->fecha_inicio, $this->fecha_fin])
                    ->orWhereBetween('fecha_fin', [$this->fecha_inicio, $this->fecha_fin])
                    ->orWhere(function ($q) {
                        $q->where('fecha_inicio', '<=', $this->fecha_inicio)
                          ->where('fecha_fin', '>=', $this->fecha_fin);
                    });
            })
            ->exists();
    }

    public function calcularSalarioMensual(): float
    {
        if (!$this->salario_base) return 0;
        
        switch ($this->tipo_contrato) {
            case self::TIPO_POR_CONSULTA:
                // Estimar basado en consultas promedio
                $consultasPromedioPorMes = 60; // Asumiendo ~3 consultas por día hábil
                return $this->salario_base * $consultasPromedioPorMes;
                
            case self::TIPO_GUARDIA:
                // Calcular basado en guardias por mes
                $guardiasPromedioPorMes = 8;
                return $this->salario_base * $guardiasPromedioPorMes;
                
            default:
                return $this->salario_base;
        }
    }

    public function calcularComisionMes($ventasMes = 0): float
    {
        if (!$this->porcentaje_comision || !$ventasMes) return 0;
        
        return ($ventasMes * $this->porcentaje_comision) / 100;
    }

    public function renovar($nuevaFechaFin = null, $nuevoSalario = null): self
    {
        // Crear nuevo contrato basado en el actual
        $nuevosAtributos = $this->toArray();
        
        // Remover campos que no deben copiarse
        unset($nuevosAtributos['id'], $nuevosAtributos['created_at'], $nuevosAtributos['updated_at'], $nuevosAtributos['deleted_at']);
        
        // Establecer nuevas fechas
        $nuevosAtributos['fecha_inicio'] = $this->fecha_fin->addDay();
        $nuevosAtributos['fecha_fin'] = $nuevaFechaFin ?? $nuevosAtributos['fecha_inicio']->copy()->addMonths($this->periodo_renovacion_meses ?? 12);
        
        // Actualizar salario si se proporciona
        if ($nuevoSalario) {
            $nuevosAtributos['salario_base'] = $nuevoSalario;
        }
        
        // Agregar información de renovación
        $nuevosAtributos['observaciones'] = ($nuevosAtributos['observaciones'] ?? '') . 
            "\nRenovado automáticamente desde contrato ID: {$this->id} - " . now()->format('d/m/Y');
        
        // Desactivar contrato actual
        $this->update(['is_active' => false]);
        
        // Crear nuevo contrato
        return static::create($nuevosAtributos);
    }

    public function terminar($motivo = null, $fechaTerminacion = null): bool
    {
        $fechaTerminacion = $fechaTerminacion ?? now();
        
        $this->fecha_fin = $fechaTerminacion;
        $this->is_active = false;
        
        if ($motivo) {
            $this->observaciones = ($this->observaciones ? $this->observaciones . "\n" : '') . 
                                 "Terminado: " . $motivo . " - " . now()->format('d/m/Y H:i');
        }
        
        return $this->save();
    }

    public function validarDatos(): array
    {
        $errores = [];
        
        // Validar fechas
        if ($this->fecha_inicio >= $this->fecha_fin) {
            $errores[] = 'La fecha de inicio debe ser anterior a la fecha de fin';
        }
        
        // Validar superposición con otros contratos activos
        if ($this->is_active && !$this->puedeSerActivado()) {
            $errores[] = 'Ya existe un contrato activo en este período';
        }
        
        // Validar tipo de contrato
        if (!in_array($this->tipo_contrato, array_keys($this->getTiposContrato()))) {
            $errores[] = 'Tipo de contrato inválido';
        }
        
        // Validar salario
        if ($this->salario_base < 0) {
            $errores[] = 'El salario no puede ser negativo';
        }
        
        // Validar porcentaje de comisión
        if ($this->porcentaje_comision < 0 || $this->porcentaje_comision > 100) {
            $errores[] = 'El porcentaje de comisión debe estar entre 0% y 100%';
        }
        
        return $errores;
    }

    /**
     * Obtener contratos que requieren atención (próximos a vencer, vencidos, etc.)
     */
    public static function getContratosRequierenAtencion(): array
    {
        return [
            'proximos_a_vencer' => static::proximosAVencer(30)->with('doctor')->get(),
            'vencidos' => static::vencidos()->where('is_active', true)->with('doctor')->get(),
            'para_renovacion' => static::paraRenovacion()->with('doctor')->get(),
        ];
    }

    /**
     * Boot method para eventos del modelo
     */
    protected static function boot()
    {
        parent::boot();

        // Establecer valores por defecto
        static::creating(function ($contrato) {
            if (!$contrato->tipo_contrato) {
                $contrato->tipo_contrato = self::TIPO_CONTRATADO;
            }
            
            if (!isset($contrato->periodo_renovacion_meses)) {
                $contrato->periodo_renovacion_meses = 12;
            }
            
            // Asignar usuario que crea
            $contrato->created_by = auth()->id();
        });

        // Actualizar usuario que modifica
        static::updating(function ($contrato) {
            $contrato->updated_by = auth()->id();
        });

        // Validar antes de guardar
        static::saving(function ($contrato) {
            $errores = $contrato->validarDatos();
            if (!empty($errores)) {
                throw new \InvalidArgumentException('Errores de validación: ' . implode(', ', $errores));
            }
        });
    }
}
