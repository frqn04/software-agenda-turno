<?php

namespace App\Services;

use App\Repositories\DoctorRepository;
use App\Models\Doctor;
use App\Models\DoctorContract;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class DoctorService
{
    public function __construct(
        private DoctorRepository $doctorRepository
    ) {}

    /**
     * Crear un nuevo doctor
     */
    public function create(array $data): Doctor
    {
        // Validar que no existe otro doctor con el mismo email
        if ($this->doctorRepository->findByEmail($data['email'])) {
            throw ValidationException::withMessages([
                'email' => 'Ya existe un doctor con este email.'
            ]);
        }

        return $this->doctorRepository->create($data);
    }

    /**
     * Actualizar un doctor existente
     */
    public function update(int $id, array $data): Doctor
    {
        $doctor = $this->doctorRepository->findById($id);
        
        if (!$doctor) {
            throw ValidationException::withMessages([
                'id' => 'El doctor no existe.'
            ]);
        }

        // Validar email único si se está cambiando
        if (isset($data['email']) && $data['email'] !== $doctor->email) {
            if ($this->doctorRepository->findByEmail($data['email'])) {
                throw ValidationException::withMessages([
                    'email' => 'Ya existe un doctor con este email.'
                ]);
            }
        }

        $this->doctorRepository->update($id, $data);
        return $this->doctorRepository->findById($id);
    }

    /**
     * Obtener doctores por especialidad
     */
    public function getDoctoresByEspecialidad(int $especialidadId): array
    {
        return $this->doctorRepository->findByEspecialidad($especialidadId)->toArray();
    }

    /**
     * Obtener doctores activos con contratos vigentes
     */
    public function getActiveDoctors(): array
    {
        return $this->doctorRepository->findActiveWithContracts()->toArray();
    }

    /**
     * Verificar si un doctor tiene contratos activos
     */
    public function hasActiveContracts(int $doctorId): bool
    {
        return $this->doctorRepository->hasActiveContracts($doctorId);
    }

    /**
     * Obtener doctor con sus contratos activos
     */
    public function getDoctorWithActiveContracts(int $id): ?Doctor
    {
        return $this->doctorRepository->findWithActiveContracts($id);
    }

    /**
     * Activar un doctor
     */
    public function activate(int $id): bool
    {
        return $this->doctorRepository->update($id, ['activo' => true]);
    }

    /**
     * Desactivar un doctor
     */
    public function deactivate(int $id): bool
    {
        return $this->doctorRepository->update($id, ['activo' => false]);
    }

    /**
     * Eliminar un doctor (soft delete)
     */
    public function delete(int $id): bool
    {
        // Verificar que no tenga turnos pendientes
        $doctor = $this->doctorRepository->findById($id);
        
        if (!$doctor) {
            throw ValidationException::withMessages([
                'id' => 'El doctor no existe.'
            ]);
        }

        // Aquí podrías agregar validaciones adicionales
        // como verificar turnos futuros, etc.

        return $this->doctorRepository->delete($id);
    }

    /**
     * Buscar doctores por múltiples criterios
     */
    public function searchDoctors(array $criteria): array
    {
        $query = $this->doctorRepository->query();

        if (isset($criteria['especialidad_id'])) {
            $query->where('especialidad_id', $criteria['especialidad_id']);
        }

        if (isset($criteria['activo'])) {
            $query->where('activo', $criteria['activo']);
        }

        if (isset($criteria['search'])) {
            $search = $criteria['search'];
            $query->where(function($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('apellido', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->with(['especialidad', 'contratos'])->get()->toArray();
    }

    /**
     * Obtener estadísticas de un doctor
     */
    public function getDoctorStats(int $doctorId): array
    {
        $doctor = $this->doctorRepository->findById($doctorId);
        
        if (!$doctor) {
            throw ValidationException::withMessages([
                'id' => 'El doctor no existe.'
            ]);
        }

        // Aquí podrías calcular estadísticas como:
        // - Turnos atendidos este mes
        // - Turnos cancelados
        // - Promedio de duración de consultas
        // - etc.

        return [
            'doctor_id' => $doctorId,
            'total_turnos_mes' => 0, // Implementar lógica
            'turnos_completados' => 0, // Implementar lógica
            'turnos_cancelados' => 0, // Implementar lógica
            'contratos_activos' => $this->doctorRepository->hasActiveContracts($doctorId)
        ];
    }
}
