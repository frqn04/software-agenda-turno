<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Doctor;
use App\Models\Paciente;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

/**
 * Repository empresarial para el modelo User
 * Maneja operaciones optimizadas con cache, validaciones de seguridad y auditoría
 * Incluye funcionalidades específicas para gestión de usuarios del sistema médico
 */
class UserRepository
{
    protected User $model;
    protected int $cacheMinutes = 30;
    protected string $cachePrefix = 'user_';

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    /**
     * Crear un nuevo usuario con validaciones empresariales
     */
    public function create(array $data): User
    {
        try {
            DB::beginTransaction();

            // Validar unicidad de email
            if ($this->existsByEmail($data['email'])) {
                throw new \Exception("Ya existe un usuario con este email");
            }

            // Hashear la contraseña si se proporciona
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            // Establecer estado activo por defecto
            $data['activo'] = $data['activo'] ?? true;
            
            // Establecer fecha de verificación de email si se marca como verificado
            if (isset($data['email_verified']) && $data['email_verified']) {
                $data['email_verified_at'] = now();
            }

            $user = $this->model->create($data);

            // Limpiar cache relacionado
            $this->clearRelatedCache();

            DB::commit();

            Log::info('Usuario creado exitosamente', [
                'user_id' => $user->id,
                'email' => $user->email,
                'rol' => $user->rol
            ]);

            return $user;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear usuario', [
                'error' => $e->getMessage(),
                'data' => array_except($data, ['password'])
            ]);
            throw $e;
        }
    }

    /**
     * Buscar usuario por ID con cache
     */
    public function findById(int $id): ?User
    {
        return Cache::remember(
            $this->cachePrefix . "id_{$id}",
            $this->cacheMinutes,
            function () use ($id) {
                return $this->model->find($id);
            }
        );
    }

    /**
     * Buscar usuario por email con cache
     */
    public function findByEmail(string $email): ?User
    {
        return Cache::remember(
            $this->cachePrefix . "email_" . md5($email),
            $this->cacheMinutes,
            function () use ($email) {
                return $this->model->where('email', $email)->first();
            }
        );
    }

    /**
     * Actualizar un usuario con auditoría
     */
    public function update(int $id, array $data): bool
    {
        try {
            DB::beginTransaction();

            $user = $this->findById($id);
            if (!$user) {
                throw new \Exception("Usuario no encontrado con ID: {$id}");
            }

            // Validar unicidad de email (excluyendo el usuario actual)
            if (isset($data['email']) && $this->existsByEmail($data['email'], $id)) {
                throw new \Exception("Ya existe otro usuario con este email");
            }

            // Hashear la contraseña si se proporciona
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            // Manejar verificación de email
            if (isset($data['email_verified'])) {
                if ($data['email_verified'] && !$user->email_verified_at) {
                    $data['email_verified_at'] = now();
                } elseif (!$data['email_verified']) {
                    $data['email_verified_at'] = null;
                }
                unset($data['email_verified']);
            }

            $updated = $user->update($data);

            // Limpiar cache específico
            $this->clearUserCache($id);
            $this->clearRelatedCache();

            DB::commit();

            Log::info('Usuario actualizado exitosamente', [
                'user_id' => $id,
                'updated_fields' => array_keys(array_except($data, ['password']))
            ]);

            return $updated;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar usuario', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'data' => array_except($data, ['password'])
            ]);
            throw $e;
        }
    }

    /**
     * Eliminar un usuario (soft delete) con validaciones
     */
    public function delete(int $id): bool
    {
        try {
            DB::beginTransaction();

            $user = $this->findById($id);
            if (!$user) {
                throw new \Exception("Usuario no encontrado con ID: {$id}");
            }

            // Verificar dependencias según el rol del usuario
            if ($user->rol === 'doctor') {
                $hasActiveDoctorProfile = Doctor::where('user_id', $id)
                    ->where('activo', true)
                    ->exists();
                
                if ($hasActiveDoctorProfile) {
                    throw new \Exception("No se puede eliminar el usuario porque tiene un perfil de doctor activo");
                }
            }

            if ($user->rol === 'paciente') {
                $hasActivePacienteProfile = Paciente::where('user_id', $id)
                    ->where('activo', true)
                    ->exists();
                
                if ($hasActivePacienteProfile) {
                    throw new \Exception("No se puede eliminar el usuario porque tiene un perfil de paciente activo");
                }
            }

            $deleted = $user->delete();

            // Limpiar cache
            $this->clearUserCache($id);
            $this->clearRelatedCache();

            DB::commit();

            Log::warning('Usuario eliminado', [
                'user_id' => $id,
                'email' => $user->email,
                'rol' => $user->rol
            ]);

            return $deleted;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar usuario', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtener usuarios paginados con filtros
     */
    public function getPaginated(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->model->newQuery();

        // Aplicar filtros
        $query = $this->applyFilters($query, $filters);

        return $query->orderBy('created_at', 'desc')
                    ->paginate($perPage);
    }

    /**
     * Buscar usuarios activos
     */
    public function findActive(): Collection
    {
        return Cache::remember(
            $this->cachePrefix . 'all_active',
            $this->cacheMinutes,
            function () {
                return $this->model->where('activo', true)
                    ->orderBy('name')
                    ->get();
            }
        );
    }

    /**
     * Buscar usuarios por rol
     */
    public function findByRole(string $rol): Collection
    {
        return Cache::remember(
            $this->cachePrefix . "role_{$rol}",
            $this->cacheMinutes,
            function () use ($rol) {
                return $this->model->where('rol', $rol)
                    ->where('activo', true)
                    ->orderBy('name')
                    ->get();
            }
        );
    }

    /**
     * Buscar usuarios por múltiples criterios
     */
    public function search(array $criteria): Collection
    {
        $query = $this->model->newQuery();

        if (!empty($criteria['search'])) {
            $search = $criteria['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $this->applyFilters($query, $criteria)
                   ->orderBy('name')
                   ->get();
    }

    /**
     * Obtener usuarios recientes (últimos N días)
     */
    public function getRecent(int $days = 30): Collection
    {
        return Cache::remember(
            $this->cachePrefix . "recent_{$days}",
            30,
            function () use ($days) {
                return $this->model->where('created_at', '>=', now()->subDays($days))
                    ->orderBy('created_at', 'desc')
                    ->get();
            }
        );
    }

    /**
     * Obtener estadísticas de usuarios
     */
    public function getStatistics(): array
    {
        return Cache::remember(
            $this->cachePrefix . 'statistics',
            120, // 2 horas
            function () {
                $total = $this->model->count();
                $activos = $this->model->where('activo', true)->count();
                $verificados = $this->model->whereNotNull('email_verified_at')->count();

                // Estadísticas por rol
                $porRol = $this->model->select('rol', DB::raw('COUNT(*) as total'))
                    ->groupBy('rol')
                    ->pluck('total', 'rol')
                    ->toArray();

                // Usuarios registrados este mes
                $esteMes = $this->model->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count();

                // Usuarios que se conectaron en los últimos 30 días
                $activosRecientes = $this->model->where('last_login_at', '>=', now()->subDays(30))
                    ->count();

                return [
                    'total' => $total,
                    'activos' => $activos,
                    'inactivos' => $total - $activos,
                    'verificados' => $verificados,
                    'sin_verificar' => $total - $verificados,
                    'este_mes' => $esteMes,
                    'activos_recientes' => $activosRecientes,
                    'por_rol' => $porRol,
                    'tasa_verificacion' => $total > 0 ? ($verificados / $total) * 100 : 0,
                    'tasa_actividad_reciente' => $total > 0 ? ($activosRecientes / $total) * 100 : 0,
                ];
            }
        );
    }

    /**
     * Verificar si existe un usuario por email
     */
    public function existsByEmail(string $email, int $excludeId = null): bool
    {
        $query = $this->model->where('email', $email);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Actualizar último login
     */
    public function updateLastLogin(int $id): bool
    {
        try {
            $updated = $this->model->where('id', $id)->update([
                'last_login_at' => now()
            ]);

            // Limpiar cache específico
            $this->clearUserCache($id);

            Log::info('Último login actualizado', [
                'user_id' => $id,
                'timestamp' => now()
            ]);

            return $updated;

        } catch (\Exception $e) {
            Log::error('Error al actualizar último login', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Cambiar contraseña
     */
    public function changePassword(int $id, string $newPassword): bool
    {
        try {
            DB::beginTransaction();

            $user = $this->findById($id);
            if (!$user) {
                throw new \Exception("Usuario no encontrado con ID: {$id}");
            }

            $updated = $user->update([
                'password' => Hash::make($newPassword),
                'password_changed_at' => now()
            ]);

            // Limpiar cache específico
            $this->clearUserCache($id);

            DB::commit();

            Log::info('Contraseña cambiada exitosamente', [
                'user_id' => $id
            ]);

            return $updated;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al cambiar contraseña', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Activar/desactivar usuario
     */
    public function toggleStatus(int $id): bool
    {
        try {
            DB::beginTransaction();

            $user = $this->findById($id);
            if (!$user) {
                throw new \Exception("Usuario no encontrado con ID: {$id}");
            }

            $newStatus = !$user->activo;
            $updated = $user->update(['activo' => $newStatus]);

            // Limpiar cache
            $this->clearUserCache($id);
            $this->clearRelatedCache();

            DB::commit();

            Log::info('Estado del usuario cambiado', [
                'user_id' => $id,
                'new_status' => $newStatus ? 'activo' : 'inactivo'
            ]);

            return $updated;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al cambiar estado del usuario', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Verificar email del usuario
     */
    public function verifyEmail(int $id): bool
    {
        try {
            DB::beginTransaction();

            $user = $this->findById($id);
            if (!$user) {
                throw new \Exception("Usuario no encontrado con ID: {$id}");
            }

            if ($user->email_verified_at) {
                throw new \Exception("El email ya está verificado");
            }

            $updated = $user->update(['email_verified_at' => now()]);

            // Limpiar cache específico
            $this->clearUserCache($id);
            $this->clearRelatedCache();

            DB::commit();

            Log::info('Email verificado exitosamente', [
                'user_id' => $id,
                'email' => $user->email
            ]);

            return $updated;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al verificar email', [
                'user_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Obtener usuarios con perfiles relacionados
     */
    public function findWithProfiles(int $id): ?User
    {
        return Cache::remember(
            $this->cachePrefix . "with_profiles_{$id}",
            $this->cacheMinutes,
            function () use ($id) {
                return $this->model->with(['doctor', 'paciente'])->find($id);
            }
        );
    }

    /**
     * Obtener modelo base para queries personalizadas
     */
    public function query(): Builder
    {
        return $this->model->newQuery();
    }

    /**
     * Aplicar filtros a la query
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (isset($filters['activo'])) {
            $query->where('activo', $filters['activo']);
        }

        if (isset($filters['rol'])) {
            if (is_array($filters['rol'])) {
                $query->whereIn('rol', $filters['rol']);
            } else {
                $query->where('rol', $filters['rol']);
            }
        }

        if (isset($filters['email_verified'])) {
            if ($filters['email_verified']) {
                $query->whereNotNull('email_verified_at');
            } else {
                $query->whereNull('email_verified_at');
            }
        }

        if (isset($filters['created_desde'])) {
            $query->where('created_at', '>=', $filters['created_desde']);
        }

        if (isset($filters['created_hasta'])) {
            $query->where('created_at', '<=', $filters['created_hasta']);
        }

        if (isset($filters['last_login_desde'])) {
            $query->where('last_login_at', '>=', $filters['last_login_desde']);
        }

        if (isset($filters['activos_recientes'])) {
            $days = $filters['activos_recientes'];
            $query->where('last_login_at', '>=', now()->subDays($days));
        }

        return $query;
    }

    /**
     * Limpiar cache específico del usuario
     */
    private function clearUserCache(int $userId): void
    {
        $user = $this->model->find($userId);
        if ($user) {
            $keys = [
                $this->cachePrefix . "id_{$userId}",
                $this->cachePrefix . "email_" . md5($user->email),
                $this->cachePrefix . "with_profiles_{$userId}",
            ];

            foreach ($keys as $key) {
                Cache::forget($key);
            }
        }
    }

    /**
     * Limpiar cache relacionado
     */
    private function clearRelatedCache(): void
    {
        Cache::forget($this->cachePrefix . 'all_active');
        Cache::forget($this->cachePrefix . 'statistics');
        Cache::forget($this->cachePrefix . 'recent_30');
        
        // Limpiar cache por roles
        $roles = ['admin', 'doctor', 'paciente', 'recepcionista'];
        foreach ($roles as $rol) {
            Cache::forget($this->cachePrefix . "role_{$rol}");
        }
    }
}
