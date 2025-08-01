<?php

namespace App\Repositories;

use App\Models\Doctor;
use App\Models\DoctorContract;
use App\Models\Especialidad;
use Illuminate\Database\Eloquent\Collection;

class DoctorRepository
{
    /**
     * Obtener todos los doctores activos con su especialidad
     */
    public function getAllActive(): Collection
    {
        return Doctor::with('especialidad')
            ->where('activo', true)
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Buscar doctores por especialidad
     */
    public function findByEspecialidad(int $especialidadId): Collection
    {
        return Doctor::with('especialidad')
            ->where('especialidad_id', $especialidadId)
            ->where('activo', true)
            ->get();
    }

    /**
     * Obtener doctor con contratos activos
     */
    public function findWithActiveContracts(int $id): ?Doctor
    {
        return Doctor::with(['contracts' => function ($query) {
            $query->where('is_active', true)
                  ->where('fecha_inicio', '<=', now())
                  ->where(function ($q) {
                      $q->whereNull('fecha_fin')
                        ->orWhere('fecha_fin', '>=', now());
                  });
        }])->find($id);
    }

    /**
     * Obtener doctor con horarios
     */
    public function findWithSchedules(int $id): ?Doctor
    {
        return Doctor::with(['scheduleSlots' => function ($query) {
            $query->where('is_active', true)
                  ->orderBy('day_of_week')
                  ->orderBy('start_time');
        }])->find($id);
    }

    /**
     * Crear nuevo doctor
     */
    public function create(array $data): Doctor
    {
        return Doctor::create($data);
    }

    /**
     * Actualizar doctor
     */
    public function update(int $id, array $data): bool
    {
        return Doctor::where('id', $id)->update($data);
    }

    /**
     * Encontrar doctor por matrícula
     */
    public function findByMatricula(string $matricula): ?Doctor
    {
        return Doctor::where('matricula', $matricula)->first();
    }

    /**
     * Verificar si doctor tiene contratos activos
     */
    public function hasActiveContracts(int $doctorId): bool
    {
        return DoctorContract::where('doctor_id', $doctorId)
            ->where('is_active', true)
            ->where('fecha_inicio', '<=', now())
            ->where(function ($query) {
                $query->whereNull('fecha_fin')
                      ->orWhere('fecha_fin', '>=', now());
            })
            ->exists();
    }
}
