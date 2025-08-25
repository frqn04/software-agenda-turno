<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

/**
 * Modelo para gestionar las Historias Clínicas de los pacientes
 * Maneja tanto la información básica como las relaciones con evoluciones
 */
class HistoriaClinica extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'historias_clinicas';

    protected $fillable = [
        'paciente_id',
        'doctor_id',
        'fecha_apertura',
        'numero_historia',
        'antecedentes_personales',
        'antecedentes_familiares',
        'antecedentes_quirurgicos',
        'medicamentos_habituales',
        'alergias',
        'vacunas',
        'grupo_sanguineo',
        'factor_rh',
        'peso',
        'altura',
        'imc',
        'presion_arterial',
        'frecuencia_cardiaca',
        'temperatura',
        'observaciones_generales',
        'estado',
        'activa',
    ];

    protected $casts = [
        'fecha_apertura' => 'date',
        'antecedentes_personales' => 'array',
        'antecedentes_familiares' => 'array',
        'antecedentes_quirurgicos' => 'array',
        'medicamentos_habituales' => 'array',
        'alergias' => 'array',
        'vacunas' => 'array',
        'peso' => 'decimal:2',
        'altura' => 'decimal:2',
        'imc' => 'decimal:2',
        'presion_arterial' => 'array', // ['sistolica' => 120, 'diastolica' => 80]
        'frecuencia_cardiaca' => 'integer',
        'temperatura' => 'decimal:1',
        'activa' => 'boolean',
    ];

    // Estados de la historia clínica
    const ESTADO_ACTIVA = 'activa';
    const ESTADO_CERRADA = 'cerrada';
    const ESTADO_ARCHIVADA = 'archivada';
    const ESTADO_TRANSFERIDA = 'transferida';

    public static function getEstados(): array
    {
        return [
            self::ESTADO_ACTIVA => 'Activa',
            self::ESTADO_CERRADA => 'Cerrada',
            self::ESTADO_ARCHIVADA => 'Archivada',
            self::ESTADO_TRANSFERIDA => 'Transferida',
        ];
    }

    // Relaciones
    public function paciente()
    {
        return $this->belongsTo(Paciente::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function evoluciones()
    {
        return $this->hasMany(Evolucion::class)->orderBy('fecha_evolucion', 'desc');
    }

    public function evolucionesRecientes()
    {
        return $this->hasMany(Evolucion::class)
            ->orderBy('fecha_evolucion', 'desc')
            ->limit(10);
    }

    public function ultimaEvolucion()
    {
        return $this->hasOne(Evolucion::class)
            ->orderBy('fecha_evolucion', 'desc')
            ->orderBy('id', 'desc');
    }

    // Scopes
    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    public function scopePorPaciente($query, $pacienteId)
    {
        return $query->where('paciente_id', $pacienteId);
    }

    public function scopePorDoctor($query, $doctorId)
    {
        return $query->where('doctor_id', $doctorId);
    }

    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    // Accessors
    public function getEdadPacienteAttribute()
    {
        return $this->paciente ? $this->paciente->edad : null;
    }

    public function getNombreCompletoAttribute()
    {
        return $this->numero_historia . ' - ' . ($this->paciente ? $this->paciente->nombre_completo : 'N/A');
    }

    public function getImcCalculadoAttribute()
    {
        if ($this->peso && $this->altura) {
            $alturaEnMetros = $this->altura / 100;
            return round($this->peso / ($alturaEnMetros * $alturaEnMetros), 2);
        }
        return null;
    }

    public function getEstadoImcAttribute()
    {
        $imc = $this->imc_calculado;
        if (!$imc) return null;

        if ($imc < 18.5) return 'Bajo peso';
        if ($imc < 25) return 'Normal';
        if ($imc < 30) return 'Sobrepeso';
        return 'Obesidad';
    }

    public function getPresionArterialFormateadaAttribute()
    {
        if (is_array($this->presion_arterial) && 
            isset($this->presion_arterial['sistolica']) && 
            isset($this->presion_arterial['diastolica'])) {
            return $this->presion_arterial['sistolica'] . '/' . $this->presion_arterial['diastolica'];
        }
        return 'No registrada';
    }

    // Métodos auxiliares
    public function calcularIMC(): ?float
    {
        if ($this->peso && $this->altura) {
            $alturaEnMetros = $this->altura / 100;
            $imc = $this->peso / ($alturaEnMetros * $alturaEnMetros);
            
            // Actualizar el IMC en la base de datos
            $this->update(['imc' => round($imc, 2)]);
            
            return round($imc, 2);
        }
        return null;
    }

    public function agregarEvolucion(array $datosEvolucion): Evolucion
    {
        $datosEvolucion['historia_clinica_id'] = $this->id;
        $datosEvolucion['doctor_id'] = $datosEvolucion['doctor_id'] ?? $this->doctor_id;
        
        return $this->evoluciones()->create($datosEvolucion);
    }

    public function tieneAlergias(): bool
    {
        return is_array($this->alergias) && count($this->alergias) > 0;
    }

    public function getAlergiasTexto(): string
    {
        if (!$this->tieneAlergias()) {
            return 'Sin alergias conocidas';
        }
        
        return implode(', ', $this->alergias);
    }

    public function puedeSerEditada(): bool
    {
        return $this->estado === self::ESTADO_ACTIVA;
    }

    public function cerrarHistoria($motivo = null): bool
    {
        $this->estado = self::ESTADO_CERRADA;
        $this->activa = false;
        
        if ($motivo) {
            $this->observaciones_generales = ($this->observaciones_generales ? $this->observaciones_generales . "\n" : '') . 
                                           "Cerrada: " . $motivo . " - " . now()->format('d/m/Y H:i');
        }
        
        return $this->save();
    }

    public function archivarHistoria($motivo = null): bool
    {
        $this->estado = self::ESTADO_ARCHIVADA;
        $this->activa = false;
        
        if ($motivo) {
            $this->observaciones_generales = ($this->observaciones_generales ? $this->observaciones_generales . "\n" : '') . 
                                           "Archivada: " . $motivo . " - " . now()->format('d/m/Y H:i');
        }
        
        return $this->save();
    }

    // Validaciones personalizadas
    public function validarSignosVitales(): array
    {
        $errores = [];
        
        // Validar presión arterial
        if (is_array($this->presion_arterial)) {
            $sistolica = $this->presion_arterial['sistolica'] ?? 0;
            $diastolica = $this->presion_arterial['diastolica'] ?? 0;
            
            if ($sistolica > 180 || $diastolica > 110) {
                $errores[] = 'Presión arterial en niveles críticos';
            }
        }
        
        // Validar frecuencia cardíaca
        if ($this->frecuencia_cardiaca && ($this->frecuencia_cardiaca < 40 || $this->frecuencia_cardiaca > 120)) {
            $errores[] = 'Frecuencia cardíaca fuera del rango normal';
        }
        
        // Validar temperatura
        if ($this->temperatura && ($this->temperatura < 35 || $this->temperatura > 39)) {
            $errores[] = 'Temperatura corporal anormal';
        }
        
        return $errores;
    }

    /**
     * Generar número de historia clínica único
     */
    public static function generarNumeroHistoria($pacienteId): string
    {
        $anio = now()->year;
        $paciente = Paciente::find($pacienteId);
        $iniciales = $paciente ? strtoupper(substr($paciente->nombre, 0, 1) . substr($paciente->apellido, 0, 1)) : 'XX';
        
        // Buscar el último número para este año
        $ultimaHistoria = static::where('numero_historia', 'like', "HC{$anio}{$iniciales}%")
            ->orderBy('numero_historia', 'desc')
            ->first();
        
        $numero = 1;
        if ($ultimaHistoria) {
            $ultimoNumero = (int) substr($ultimaHistoria->numero_historia, -4);
            $numero = $ultimoNumero + 1;
        }
        
        return "HC{$anio}{$iniciales}" . str_pad($numero, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Boot method para eventos del modelo
     */
    protected static function boot()
    {
        parent::boot();

        // Generar número de historia automáticamente
        static::creating(function ($historia) {
            if (!$historia->numero_historia) {
                $historia->numero_historia = static::generarNumeroHistoria($historia->paciente_id);
            }
            
            if (!$historia->fecha_apertura) {
                $historia->fecha_apertura = now();
            }
            
            if (!$historia->estado) {
                $historia->estado = static::ESTADO_ACTIVA;
            }
        });
    }
}
