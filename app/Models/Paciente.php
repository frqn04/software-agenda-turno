<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'email',
        'direccion',
        'observaciones',
        'activo',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'activo' => 'boolean',
    ];

    // Relaciones
    public function historiaClinicas()
    {
        return $this->hasMany(HistoriaClinica::class);
    }

    public function turnos()
    {
        return $this->hasMany(Turno::class);
    }

    // Métodos de ayuda
    public function getNombreCompletoAttribute()
    {
        return $this->nombre . ' ' . $this->apellido;
    }

    public function getEdadAttribute()
    {
        return $this->fecha_nacimiento->age ?? null;
    }
}
