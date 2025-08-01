<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogAuditoria extends Model
{
    use HasFactory;

    protected $table = 'logs_auditoria';
    public $timestamps = false; // No usar timestamps automáticos

    protected $fillable = [
        'usuario_id',
        'tabla',
        'registro_id',
        'accion',
        'datos_anteriores',
        'datos_nuevos',
        'ip_address',
        'user_agent',
        'fecha_hora',
    ];

    protected $casts = [
        'datos_anteriores' => 'array',
        'datos_nuevos' => 'array',
        'fecha_hora' => 'datetime',
    ];

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    // Scopes
    public function scopeForModel($query, $modelType, $modelId = null)
    {
        $query->where('tabla', $modelType);
        
        if ($modelId) {
            $query->where('registro_id', $modelId);
        }
        
        return $query;
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('usuario_id', $userId);
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('accion', $action);
    }

    // Métodos auxiliares
    public function getModelClass(): string
    {
        return $this->tabla;
    }

    public function getChangedFields(): array
    {
        if (!$this->datos_anteriores || !$this->datos_nuevos) {
            return [];
        }

        $changed = [];
        foreach ($this->datos_nuevos as $field => $newValue) {
            $oldValue = $this->datos_anteriores[$field] ?? null;
            if ($oldValue !== $newValue) {
                $changed[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue
                ];
            }
        }

        return $changed;
    }

    public static function logActivity(string $action, Model $model, ?array $oldValues = null, ?array $newValues = null): void
    {
        $user = auth()->user();
        $request = request();

        static::create([
            'usuario_id' => $user?->id,
            'tabla' => get_class($model),
            'registro_id' => $model->id,
            'accion' => $action,
            'datos_anteriores' => $oldValues,
            'datos_nuevos' => $newValues,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'fecha_hora' => now(),
        ]);
    }
}
