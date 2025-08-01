<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'email',
        'observaciones',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
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
            ->where('activo', true)
            ->where('fecha_inicio', '<=', now())
            ->where('fecha_fin', '>=', now());
    }

    public function horarios()
    {
        return $this->hasMany(DoctorScheduleSlot::class);
    }

    public function turnos()
    {
        return $this->hasMany(Turno::class);
    }

    public function historiaClinicas()
    {
        return $this->hasMany(HistoriaClinica::class);
    }

    // Métodos de ayuda
    public function getNombreCompletoAttribute()
    {
        return $this->nombre . ' ' . $this->apellido;
    }

    public function tieneContratoActivo($fecha = null)
    {
        $fecha = $fecha ?? now()->format('Y-m-d');
        
        return $this->contratos()
            ->where('activo', true)
            ->where('fecha_inicio', '<=', $fecha)
            ->where('fecha_fin', '>=', $fecha)
            ->exists();
    }
}
