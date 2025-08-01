<?php

namespace App\Services;

use App\Repositories\PacienteRepository;
use App\Models\Paciente;
use Illuminate\Validation\ValidationException;

class PacienteService
{
    public function __construct(
        private PacienteRepository $pacienteRepository
    ) {}

    /**
     * Crear un nuevo paciente
     */
    public function create(array $data): Paciente
    {
        // Validar que no existe otro paciente con el mismo email o DNI
        if ($this->pacienteRepository->existsWithEmailOrDni($data['email'], $data['dni'])) {
            throw ValidationException::withMessages([
                'email' => 'Ya existe un paciente con este email o DNI.'
            ]);
        }

        return $this->pacienteRepository->create($data);
    }

    /**
     * Actualizar un paciente existente
     */
    public function update(int $id, array $data): Paciente
    {
        $paciente = $this->pacienteRepository->findById($id);
        
        if (!$paciente) {
            throw ValidationException::withMessages([
                'id' => 'El paciente no existe.'
            ]);
        }

        // Validar email y DNI únicos si se están cambiando
        if (isset($data['email']) || isset($data['dni'])) {
            $email = $data['email'] ?? $paciente->email;
            $dni = $data['dni'] ?? $paciente->dni;
            
            if ($this->pacienteRepository->existsWithEmailOrDni($email, $dni, $id)) {
                throw ValidationException::withMessages([
                    'email' => 'Ya existe un paciente con este email o DNI.'
                ]);
            }
        }

        $this->pacienteRepository->update($id, $data);
        return $this->pacienteRepository->findById($id);
    }

    /**
     * Obtener paciente por ID con información completa
     */
    public function getById(int $id): ?Paciente
    {
        return $this->pacienteRepository->findById($id);
    }

    /**
     * Obtener paciente con historial de turnos
     */
    public function getWithTurnos(int $id): ?Paciente
    {
        return $this->pacienteRepository->findWithTurnos($id);
    }

    /**
     * Obtener paciente con historia clínica
     */
    public function getWithHistoriaClinica(int $id): ?Paciente
    {
        return $this->pacienteRepository->findWithHistoriaClinica($id);
    }

    /**
     * Buscar pacientes
     */
    public function search(array $criteria): array
    {
        return $this->pacienteRepository->search($criteria)->toArray();
    }

    /**
     * Obtener todos los pacientes activos
     */
    public function getActive(): array
    {
        return $this->pacienteRepository->findActive()->toArray();
    }

    /**
     * Activar un paciente
     */
    public function activate(int $id): bool
    {
        return $this->pacienteRepository->update($id, ['activo' => true]);
    }

    /**
     * Desactivar un paciente
     */
    public function deactivate(int $id): bool
    {
        return $this->pacienteRepository->update($id, ['activo' => false]);
    }

    /**
     * Eliminar un paciente
     */
    public function delete(int $id): bool
    {
        $paciente = $this->pacienteRepository->findById($id);
        
        if (!$paciente) {
            throw ValidationException::withMessages([
                'id' => 'El paciente no existe.'
            ]);
        }

        // Verificar si tiene turnos futuros
        // Aquí podrías agregar validaciones adicionales

        return $this->pacienteRepository->delete($id);
    }

    /**
     * Obtener estadísticas de pacientes
     */
    public function getStats(): array
    {
        $stats = $this->pacienteRepository->countByStatus();
        $recent = $this->pacienteRepository->getRecent(30);

        return array_merge($stats, [
            'nuevos_ultimo_mes' => $recent->count(),
            'promedio_edad' => $this->calculateAverageAge()
        ]);
    }

    /**
     * Calcular edad promedio de pacientes activos
     */
    private function calculateAverageAge(): float
    {
        $pacientes = $this->pacienteRepository->findActive();
        
        if ($pacientes->isEmpty()) {
            return 0;
        }

        $totalAge = $pacientes->sum(function($paciente) {
            return $paciente->fecha_nacimiento 
                ? now()->diffInYears($paciente->fecha_nacimiento) 
                : 0;
        });

        return round($totalAge / $pacientes->count(), 1);
    }

    /**
     * Validar disponibilidad de email y DNI para nuevo paciente
     */
    public function validateAvailability(string $email, string $dni, int $excludeId = null): array
    {
        $emailExists = $this->pacienteRepository->findByEmail($email);
        $dniExists = $this->pacienteRepository->findByDni($dni);

        $errors = [];

        if ($emailExists && ($excludeId === null || $emailExists->id !== $excludeId)) {
            $errors['email'] = 'Ya existe un paciente con este email.';
        }

        if ($dniExists && ($excludeId === null || $dniExists->id !== $excludeId)) {
            $errors['dni'] = 'Ya existe un paciente con este DNI.';
        }

        return $errors;
    }
}
