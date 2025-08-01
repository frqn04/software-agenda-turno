<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DoctorContract extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'doctor_id',
        'fecha_inicio',
        'fecha_fin',
        'tipo_contrato',
        'observaciones',
        'is_active',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'is_active' => 'boolean',
    ];

    // Relaciones
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    // Métodos de ayuda
    public function isActiveAt($fecha): bool
    {
        return $this->is_active && 
               $this->fecha_inicio <= $fecha && 
               $this->fecha_fin >= $fecha;
    }
}
