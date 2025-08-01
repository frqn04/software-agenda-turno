<?php

namespace App\Repositories;

use App\Models\Paciente;
use Illuminate\Database\Eloquent\Collection;

class PacienteRepository
{
    public function __construct(
        private Paciente $model
    ) {}

    /**
     * Crear un nuevo paciente
     */
    public function create(array $data): Paciente
    {
        return $this->model->create($data);
    }

    /**
     * Buscar paciente por ID
     */
    public function findById(int $id): ?Paciente
    {
        return $this->model->find($id);
    }

    /**
     * Buscar paciente por email
     */
    public function findByEmail(string $email): ?Paciente
    {
        return $this->model->where('email', $email)->first();
    }

    /**
     * Buscar paciente por DNI
     */
    public function findByDni(string $dni): ?Paciente
    {
        return $this->model->where('dni', $dni)->first();
    }

    /**
     * Actualizar un paciente
     */
    public function update(int $id, array $data): bool
    {
        return $this->model->where('id', $id)->update($data);
    }

    /**
     * Eliminar un paciente (soft delete)
     */
    public function delete(int $id): bool
    {
        $paciente = $this->findById($id);
        return $paciente ? $paciente->delete() : false;
    }

    /**
     * Obtener todos los pacientes
     */
    public function getAll(): Collection
    {
        return $this->model->all();
    }

    /**
     * Buscar pacientes activos
     */
    public function findActive(): Collection
    {
        return $this->model->where('activo', true)->get();
    }

    /**
     * Buscar pacientes por múltiples criterios
     */
    public function search(array $criteria): Collection
    {
        $query = $this->model->newQuery();

        if (isset($criteria['search'])) {
            $search = $criteria['search'];
            $query->where(function($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('apellido', 'like', "%{$search}%")
                  ->orWhere('dni', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (isset($criteria['activo'])) {
            $query->where('activo', $criteria['activo']);
        }

        if (isset($criteria['fecha_nacimiento_desde'])) {
            $query->where('fecha_nacimiento', '>=', $criteria['fecha_nacimiento_desde']);
        }

        if (isset($criteria['fecha_nacimiento_hasta'])) {
            $query->where('fecha_nacimiento', '<=', $criteria['fecha_nacimiento_hasta']);
        }

        return $query->orderBy('apellido')->orderBy('nombre')->get();
    }

    /**
     * Obtener pacientes con sus turnos
     */
    public function findWithTurnos(int $id): ?Paciente
    {
        return $this->model->with(['turnos' => function($query) {
            $query->orderBy('fecha', 'desc')->orderBy('hora_inicio', 'desc');
        }])->find($id);
    }

    /**
     * Obtener pacientes con historial clínico
     */
    public function findWithHistoriaClinica(int $id): ?Paciente
    {
        return $this->model->with(['historiaClinica.evoluciones' => function($query) {
            $query->orderBy('fecha', 'desc');
        }])->find($id);
    }

    /**
     * Contar pacientes por estado
     */
    public function countByStatus(): array
    {
        return [
            'activos' => $this->model->where('activo', true)->count(),
            'inactivos' => $this->model->where('activo', false)->count(),
            'total' => $this->model->count()
        ];
    }

    /**
     * Obtener pacientes recientes (últimos 30 días)
     */
    public function getRecent(int $days = 30): Collection
    {
        return $this->model
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Verificar si existe un paciente con email o DNI
     */
    public function existsWithEmailOrDni(string $email, string $dni, int $excludeId = null): bool
    {
        $query = $this->model->where(function($q) use ($email, $dni) {
            $q->where('email', $email)->orWhere('dni', $dni);
        });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Obtener modelo base para queries personalizadas
     */
    public function query()
    {
        return $this->model->newQuery();
    }
}
