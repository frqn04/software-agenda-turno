<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

/**
 * Modelo para gestionar las evoluciones de pacientes
 * Registra cada consulta, tratamiento y seguimiento médico
 */
class Evolucion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'evoluciones';

    protected $fillable = [
        'historia_clinica_id',
        'turno_id',
        'doctor_id',
        'fecha_evolucion',
        'hora_evolucion',
        'motivo_consulta',
        'enfermedad_actual',
        'examen_fisico',
        'signos_vitales',
        'diagnostico_principal',
        'diagnosticos_secundarios',
        'plan_tratamiento',
        'medicamentos_recetados',
        'estudios_solicitados',
        'proxima_cita',
        'observaciones',
        'estado_paciente',
        'tipo_consulta',
        'duracion_consulta',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'fecha_evolucion' => 'date',
        'hora_evolucion' => 'datetime:H:i',
        'signos_vitales' => 'array',
        'diagnosticos_secundarios' => 'array',
        'medicamentos_recetados' => 'array',
        'estudios_solicitados' => 'array',
        'proxima_cita' => 'datetime',
        'duracion_consulta' => 'integer', // en minutos
    ];

    // Tipos de consulta
    const TIPO_PRIMERA_VEZ = 'primera_vez';
    const TIPO_CONTROL = 'control';
    const TIPO_URGENCIA = 'urgencia';
    const TIPO_PROCEDIMIENTO = 'procedimiento';
    const TIPO_INTERCONSULTA = 'interconsulta';

    public static function getTiposConsulta(): array
    {
        return [
            self::TIPO_PRIMERA_VEZ => 'Primera vez',
            self::TIPO_CONTROL => 'Control',
            self::TIPO_URGENCIA => 'Urgencia',
            self::TIPO_PROCEDIMIENTO => 'Procedimiento',
            self::TIPO_INTERCONSULTA => 'Interconsulta',
        ];
    }

    // Estados del paciente
    const ESTADO_ESTABLE = 'estable';
    const ESTADO_MEJORADO = 'mejorado';
    const ESTADO_EMPEORADO = 'empeorado';
    const ESTADO_CRITICO = 'critico';
    const ESTADO_ALTA = 'alta';
    const ESTADO_DERIVADO = 'derivado';

    public static function getEstadosPaciente(): array
    {
        return [
            self::ESTADO_ESTABLE => 'Estable',
            self::ESTADO_MEJORADO => 'Mejorado',
            self::ESTADO_EMPEORADO => 'Empeorado',
            self::ESTADO_CRITICO => 'Crítico',
            self::ESTADO_ALTA => 'Alta médica',
            self::ESTADO_DERIVADO => 'Derivado',
        ];
    }

    // Relaciones
    public function historiaClinica()
    {
        return $this->belongsTo(HistoriaClinica::class);
    }

    public function turno()
    {
        return $this->belongsTo(Turno::class);
    }

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

    // Obtener paciente a través de historia clínica
    public function paciente()
    {
        return $this->hasOneThrough(
            Paciente::class,
            HistoriaClinica::class,
            'id',
            'id',
            'historia_clinica_id',
            'paciente_id'
        );
    }

    // Scopes
    public function scopePorFecha($query, $fecha)
    {
        return $query->whereDate('fecha_evolucion', $fecha);
    }

    public function scopePorDoctor($query, $doctorId)
    {
        return $query->where('doctor_id', $doctorId);
    }

    public function scopePorPaciente($query, $pacienteId)
    {
        return $query->whereHas('historiaClinica', function ($q) use ($pacienteId) {
            $q->where('paciente_id', $pacienteId);
        });
    }

    public function scopePorTipoConsulta($query, $tipo)
    {
        return $query->where('tipo_consulta', $tipo);
    }

    public function scopeEntreFechas($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha_evolucion', [$fechaInicio, $fechaFin]);
    }

    public function scopeRecientes($query, $dias = 30)
    {
        return $query->where('fecha_evolucion', '>=', now()->subDays($dias));
    }

    // Accessors
    public function getFechaCompletaAttribute()
    {
        return $this->fecha_evolucion->format('d/m/Y') . ' ' . 
               Carbon::parse($this->hora_evolucion)->format('H:i');
    }

    public function getDuracionFormateadaAttribute()
    {
        if (!$this->duracion_consulta) return 'No especificada';
        
        $horas = floor($this->duracion_consulta / 60);
        $minutos = $this->duracion_consulta % 60;
        
        if ($horas > 0) {
            return $horas . 'h ' . ($minutos > 0 ? $minutos . 'm' : '');
        }
        
        return $minutos . 'min';
    }

    public function getSignosVitalesFormateadosAttribute()
    {
        if (!is_array($this->signos_vitales)) return [];
        
        $formateados = [];
        $signos = $this->signos_vitales;
        
        if (isset($signos['presion_arterial'])) {
            $formateados['Presión Arterial'] = $signos['presion_arterial'];
        }
        if (isset($signos['frecuencia_cardiaca'])) {
            $formateados['Frecuencia Cardíaca'] = $signos['frecuencia_cardiaca'] . ' lpm';
        }
        if (isset($signos['temperatura'])) {
            $formateados['Temperatura'] = $signos['temperatura'] . ' °C';
        }
        if (isset($signos['peso'])) {
            $formateados['Peso'] = $signos['peso'] . ' kg';
        }
        if (isset($signos['altura'])) {
            $formateados['Altura'] = $signos['altura'] . ' cm';
        }
        if (isset($signos['saturacion_oxigeno'])) {
            $formateados['Saturación O2'] = $signos['saturacion_oxigeno'] . '%';
        }
        
        return $formateados;
    }

    // Métodos auxiliares
    public function tieneMedicamentosRecetados(): bool
    {
        return is_array($this->medicamentos_recetados) && count($this->medicamentos_recetados) > 0;
    }

    public function tieneEstudiosSolicitados(): bool
    {
        return is_array($this->estudios_solicitados) && count($this->estudios_solicitados) > 0;
    }

    public function requiereProximaCita(): bool
    {
        return $this->proxima_cita !== null;
    }

    public function esUrgencia(): bool
    {
        return $this->tipo_consulta === self::TIPO_URGENCIA;
    }

    public function esPrimeraVez(): bool
    {
        return $this->tipo_consulta === self::TIPO_PRIMERA_VEZ;
    }

    public function getMedicamentosTexto(): string
    {
        if (!$this->tieneMedicamentosRecetados()) {
            return 'Sin medicamentos recetados';
        }
        
        $medicamentos = [];
        foreach ($this->medicamentos_recetados as $medicamento) {
            if (is_array($medicamento)) {
                $texto = $medicamento['nombre'] ?? '';
                if (isset($medicamento['dosis'])) {
                    $texto .= ' - ' . $medicamento['dosis'];
                }
                if (isset($medicamento['frecuencia'])) {
                    $texto .= ' - ' . $medicamento['frecuencia'];
                }
                $medicamentos[] = $texto;
            } else {
                $medicamentos[] = $medicamento;
            }
        }
        
        return implode("\n", $medicamentos);
    }

    public function getEstudiosTexto(): string
    {
        if (!$this->tieneEstudiosSolicitados()) {
            return 'Sin estudios solicitados';
        }
        
        return implode(", ", $this->estudios_solicitados);
    }

    public function validarSignosVitales(): array
    {
        $errores = [];
        
        if (!is_array($this->signos_vitales)) {
            return $errores;
        }
        
        $signos = $this->signos_vitales;
        
        // Validar presión arterial
        if (isset($signos['presion_arterial'])) {
            $pa = $signos['presion_arterial'];
            if (preg_match('/(\d+)\/(\d+)/', $pa, $matches)) {
                $sistolica = (int) $matches[1];
                $diastolica = (int) $matches[2];
                
                if ($sistolica > 180 || $diastolica > 110) {
                    $errores[] = 'Presión arterial en niveles críticos';
                } elseif ($sistolica < 90 || $diastolica < 60) {
                    $errores[] = 'Presión arterial baja';
                }
            }
        }
        
        // Validar frecuencia cardíaca
        if (isset($signos['frecuencia_cardiaca'])) {
            $fc = (int) $signos['frecuencia_cardiaca'];
            if ($fc > 120) {
                $errores[] = 'Taquicardia detectada';
            } elseif ($fc < 50) {
                $errores[] = 'Bradicardia detectada';
            }
        }
        
        // Validar temperatura
        if (isset($signos['temperatura'])) {
            $temp = (float) $signos['temperatura'];
            if ($temp >= 38) {
                $errores[] = 'Fiebre detectada';
            } elseif ($temp < 35) {
                $errores[] = 'Hipotermia detectada';
            }
        }
        
        // Validar saturación de oxígeno
        if (isset($signos['saturacion_oxigeno'])) {
            $sat = (int) $signos['saturacion_oxigeno'];
            if ($sat < 90) {
                $errores[] = 'Saturación de oxígeno baja';
            }
        }
        
        return $errores;
    }

    public function generarResumen(): string
    {
        $resumen = "Evolución del {$this->fecha_completa}\n";
        $resumen .= "Doctor: " . ($this->doctor ? $this->doctor->nombre_completo : 'No especificado') . "\n\n";
        
        if ($this->motivo_consulta) {
            $resumen .= "Motivo: {$this->motivo_consulta}\n";
        }
        
        if ($this->diagnostico_principal) {
            $resumen .= "Diagnóstico: {$this->diagnostico_principal}\n";
        }
        
        if ($this->plan_tratamiento) {
            $resumen .= "Plan: {$this->plan_tratamiento}\n";
        }
        
        if ($this->tieneMedicamentosRecetados()) {
            $resumen .= "Medicamentos: " . $this->getMedicamentosTexto() . "\n";
        }
        
        return $resumen;
    }

    /**
     * Boot method para eventos del modelo
     */
    protected static function boot()
    {
        parent::boot();

        // Establecer valores por defecto
        static::creating(function ($evolucion) {
            if (!$evolucion->fecha_evolucion) {
                $evolucion->fecha_evolucion = now()->toDateString();
            }
            
            if (!$evolucion->hora_evolucion) {
                $evolucion->hora_evolucion = now()->toTimeString();
            }
            
            if (!$evolucion->tipo_consulta) {
                $evolucion->tipo_consulta = self::TIPO_CONTROL;
            }
            
            if (!$evolucion->estado_paciente) {
                $evolucion->estado_paciente = self::ESTADO_ESTABLE;
            }
            
            // Asignar usuario que crea
            $evolucion->created_by = auth()->id();
        });

        // Actualizar usuario que modifica
        static::updating(function ($evolucion) {
            $evolucion->updated_by = auth()->id();
        });
    }
}
