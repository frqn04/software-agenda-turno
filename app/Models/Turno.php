<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Turno extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'turnos';

    protected $fillable = [
        'paciente_id',
        'doctor_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'motivo',
        'observaciones',
        'estado',
        'duracion_minutos',
        'precio',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'fecha' => 'date',
        'hora_inicio' => 'datetime:H:i',
        'hora_fin' => 'datetime:H:i',
        'precio' => 'decimal:2',
        'duracion_minutos' => 'integer'
    ];

    protected $dates = [
        'fecha',
        'deleted_at'
    ];

    // Estados válidos para turnos
    const ESTADO_PROGRAMADO = 'programado';
    const ESTADO_REALIZADO = 'realizado';
    const ESTADO_CANCELADO = 'cancelado';
    const ESTADO_NO_ASISTIO = 'no_asistio';
    const ESTADO_REPROGRAMADO = 'reprogramado';

    public static function getEstados(): array
    {
        return [
            self::ESTADO_PROGRAMADO => 'Programado',
            self::ESTADO_REALIZADO => 'Realizado',
            self::ESTADO_CANCELADO => 'Cancelado',
            self::ESTADO_NO_ASISTIO => 'No Asistió',
            self::ESTADO_REPROGRAMADO => 'Reprogramado'
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

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopePorFecha($query, $fecha)
    {
        return $query->whereDate('fecha', $fecha);
    }

    public function scopePorDoctor($query, $doctorId)
    {
        return $query->where('doctor_id', $doctorId);
    }

    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    public function scopeProgramados($query)
    {
        return $query->where('estado', self::ESTADO_PROGRAMADO);
    }

    public function scopeRealizados($query)
    {
        return $query->where('estado', self::ESTADO_REALIZADO);
    }

    public function scopeHoy($query)
    {
        return $query->whereDate('fecha', today());
    }

    public function scopeEntreFechas($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
    }

    // Métodos auxiliares
    public function esProgramado(): bool
    {
        return $this->estado === self::ESTADO_PROGRAMADO;
    }

    public function esRealizado(): bool
    {
        return $this->estado === self::ESTADO_REALIZADO;
    }

    public function esCancelado(): bool
    {
        return $this->estado === self::ESTADO_CANCELADO;
    }

    public function getFechaCompleta(): string
    {
        return $this->fecha->format('d/m/Y') . ' ' . $this->hora_inicio->format('H:i');
    }

    public function getDuracionFormateada(): string
    {
        $horas = floor($this->duracion_minutos / 60);
        $minutos = $this->duracion_minutos % 60;
        
        if ($horas > 0) {
            return $horas . 'h ' . ($minutos > 0 ? $minutos . 'm' : '');
        }
        
        return $minutos . 'min';
    }

    public function puedeSerCancelado(): bool
    {
        return $this->estado === self::ESTADO_PROGRAMADO && 
               $this->fecha->isFuture();
    }

    public function puedeSerRealizado(): bool
    {
        return $this->estado === self::ESTADO_PROGRAMADO;
    }

    public function puedeSerReprogramado(): bool
    {
        return in_array($this->estado, [
            self::ESTADO_PROGRAMADO, 
            self::ESTADO_CANCELADO
        ]);
    }

    // Mutators
    public function setEstadoAttribute($value)
    {
        if (!in_array($value, array_keys(self::getEstados()))) {
            throw new \InvalidArgumentException("Estado inválido: {$value}");
        }
        
        $this->attributes['estado'] = $value;
    }

    // Métodos de estado
    public function marcarComoRealizado($observaciones = null): bool
    {
        $this->estado = self::ESTADO_REALIZADO;
        if ($observaciones) {
            $this->observaciones = $observaciones;
        }
        return $this->save();
    }

    public function cancelar($motivo = null): bool
    {
        $this->estado = self::ESTADO_CANCELADO;
        if ($motivo) {
            $this->observaciones = ($this->observaciones ? $this->observaciones . "\n" : '') . 
                                 "Cancelado: " . $motivo;
        }
        return $this->save();
    }

    public function reprogramar($nuevaFecha, $nuevaHora): bool
    {
        $this->fecha = $nuevaFecha;
        $this->hora_inicio = $nuevaHora;
        $this->estado = self::ESTADO_PROGRAMADO;
        return $this->save();
    }

    /**
     * Boot method to add model events
     */
    protected static function boot()
    {
        parent::boot();

        // Evento cuando se crea un turno
        static::created(function ($turno) {
            // Programar recordatorio para 24 horas antes
            $reminderTime = Carbon::parse($turno->fecha . ' ' . $turno->hora_inicio)->subDay();
            
            if ($reminderTime->isFuture()) {
                \App\Jobs\SendAppointmentReminderJob::dispatch($turno)->delay($reminderTime);
            }

            // Notificar creación
            app(\App\Services\NotificationService::class)->notifyAppointmentCreated($turno);
        });

        // Evento cuando se actualiza un turno
        static::updated(function ($turno) {
            // Si se canceló, notificar
            if ($turno->isDirty('estado') && $turno->estado === self::ESTADO_CANCELADO) {
                app(\App\Services\NotificationService::class)->notifyAppointmentCancelled($turno);
            }
        });
    }
}
