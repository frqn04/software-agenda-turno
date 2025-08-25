<?php

namespace App\Observers;

use App\Models\LogAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Observer genérico para auditoría automática de modelos
 * Proporciona logging automático para todos los eventos del modelo
 */
class AuditObserver
{
    /**
     * Manejar evento de creación del modelo
     */
    public function created(Model $model): void
    {
        try {
            $tableName = $model->getTable();
            
            LogAuditoria::logActivity(
                'created',
                $tableName,
                $model->getKey(),
                auth()->id(),
                null,
                $this->sanitizeData($model->toArray())
            );
        } catch (\Exception $e) {
            Log::error('Error en AuditObserver::created', [
                'model' => get_class($model),
                'id' => $model->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar evento de actualización del modelo
     */
    public function updated(Model $model): void
    {
        try {
            $oldValues = $model->getOriginal();
            $newValues = $model->getChanges();

            // Solo registrar si hay cambios reales
            if (!empty($newValues)) {
                $tableName = $model->getTable();
                
                LogAuditoria::logActivity(
                    'updated',
                    $tableName,
                    $model->getKey(),
                    auth()->id(),
                    $this->sanitizeData($oldValues),
                    $this->sanitizeData($newValues)
                );
            }
        } catch (\Exception $e) {
            Log::error('Error en AuditObserver::updated', [
                'model' => get_class($model),
                'id' => $model->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar evento de eliminación del modelo
     */
    public function deleted(Model $model): void
    {
        try {
            $tableName = $model->getTable();
            
            LogAuditoria::logActivity(
                'deleted',
                $tableName,
                $model->getKey(),
                auth()->id(),
                $this->sanitizeData($model->toArray()),
                null
            );
        } catch (\Exception $e) {
            Log::error('Error en AuditObserver::deleted', [
                'model' => get_class($model),
                'id' => $model->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar evento de restauración del modelo
     */
    public function restored(Model $model): void
    {
        try {
            $tableName = $model->getTable();
            
            LogAuditoria::logActivity(
                'restored',
                $tableName,
                $model->getKey(),
                auth()->id(),
                null,
                $this->sanitizeData($model->toArray())
            );
        } catch (\Exception $e) {
            Log::error('Error en AuditObserver::restored', [
                'model' => get_class($model),
                'id' => $model->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar evento de eliminación permanente del modelo
     */
    public function forceDeleted(Model $model): void
    {
        try {
            $tableName = $model->getTable();
            
            LogAuditoria::logActivity(
                'force_deleted',
                $tableName,
                $model->getKey(),
                auth()->id(),
                $this->sanitizeData($model->toArray()),
                null
            );
        } catch (\Exception $e) {
            Log::error('Error en AuditObserver::forceDeleted', [
                'model' => get_class($model),
                'id' => $model->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sanitizar datos sensibles antes de guardar en auditoría
     */
    private function sanitizeData(array $data): array
    {
        // Campos sensibles que no deben guardarse en logs
        $camposSensibles = [
            'password',
            'remember_token',
            'api_token',
            'email_verified_at',
            'two_factor_secret',
            'two_factor_recovery_codes',
        ];

        foreach ($camposSensibles as $campo) {
            if (isset($data[$campo])) {
                $data[$campo] = '[OCULTO]';
            }
        }

        return $data;
    }
}
