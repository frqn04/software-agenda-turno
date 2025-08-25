<?php

namespace App\Observers;

use App\Models\Especialidad;
use App\Models\LogAuditoria;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Observer para el modelo Especialidad
 * Maneja eventos de las especialidades médicas
 */
class EspecialidadObserver
{
    /**
     * Manejar la creación de una especialidad
     */
    public function created(Especialidad $especialidad): void
    {
        try {
            // Log de auditoría
            LogAuditoria::logActivity(
                'created',
                'especialidades',
                $especialidad->id,
                auth()->id(),
                null,
                [
                    'nombre' => $especialidad->nombre,
                    'descripcion' => $especialidad->descripcion,
                    'is_active' => $especialidad->is_active,
                    'duracion_turno' => $especialidad->duracion_turno,
                ]
            );

            // Invalidar caché de especialidades
            $this->invalidarCacheEspecialidades();

            // Log específico
            Log::info('Nueva especialidad creada', [
                'especialidad_id' => $especialidad->id,
                'nombre' => $especialidad->nombre,
                'duracion_turno' => $especialidad->duracion_turno,
                'usuario' => auth()->user()?->name,
            ]);

            // Verificar duplicados potenciales
            $this->verificarDuplicados($especialidad);

        } catch (\Exception $e) {
            Log::error('Error en EspecialidadObserver::created', [
                'especialidad_id' => $especialidad->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar la actualización de una especialidad
     */
    public function updated(Especialidad $especialidad): void
    {
        try {
            $cambios = $especialidad->getChanges();
            $original = $especialidad->getOriginal();

            // Log de auditoría
            LogAuditoria::logActivity(
                'updated',
                'especialidades',
                $especialidad->id,
                auth()->id(),
                $original,
                $cambios
            );

            // Invalidar caché de especialidades
            $this->invalidarCacheEspecialidades();

            // Detectar cambios importantes
            $this->detectarCambiosImportantes($especialidad, $cambios, $original);

            // Log de cambios
            Log::info('Especialidad actualizada', [
                'especialidad_id' => $especialidad->id,
                'campos_modificados' => array_keys($cambios),
                'usuario' => auth()->user()?->name,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en EspecialidadObserver::updated', [
                'especialidad_id' => $especialidad->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar la eliminación de una especialidad
     */
    public function deleted(Especialidad $especialidad): void
    {
        try {
            // Verificar impacto antes de la eliminación
            $impacto = $this->calcularImpactoEliminacion($especialidad);

            // Log de auditoría
            LogAuditoria::logActivity(
                'deleted',
                'especialidades',
                $especialidad->id,
                auth()->id(),
                [
                    'nombre' => $especialidad->nombre,
                    'descripcion' => $especialidad->descripcion,
                    'is_active' => $especialidad->is_active,
                    'duracion_turno' => $especialidad->duracion_turno,
                    'impacto_eliminacion' => $impacto,
                ],
                null
            );

            // Invalidar caché de especialidades
            $this->invalidarCacheEspecialidades();

            // Log crítico si tiene datos asociados
            if ($impacto['tiene_datos_asociados']) {
                Log::warning('Especialidad eliminada con datos asociados', [
                    'especialidad_id' => $especialidad->id,
                    'nombre' => $especialidad->nombre,
                    'impacto' => $impacto,
                    'usuario' => auth()->user()?->name,
                ]);

                // Crear alerta
                $this->crearAlertaEliminacion($especialidad, $impacto);
            } else {
                Log::info('Especialidad eliminada sin datos asociados', [
                    'especialidad_id' => $especialidad->id,
                    'nombre' => $especialidad->nombre,
                    'usuario' => auth()->user()?->name,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error en EspecialidadObserver::deleted', [
                'especialidad_id' => $especialidad->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar la restauración de una especialidad
     */
    public function restored(Especialidad $especialidad): void
    {
        try {
            // Log de auditoría
            LogAuditoria::logActivity(
                'restored',
                'especialidades',
                $especialidad->id,
                auth()->id(),
                null,
                [
                    'nombre' => $especialidad->nombre,
                    'fecha_restauracion' => now(),
                    'usuario' => auth()->user()?->name,
                ]
            );

            // Invalidar caché de especialidades
            $this->invalidarCacheEspecialidades();

            // Log de restauración
            Log::info('Especialidad restaurada', [
                'especialidad_id' => $especialidad->id,
                'nombre' => $especialidad->nombre,
                'usuario' => auth()->user()?->name,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en EspecialidadObserver::restored', [
                'especialidad_id' => $especialidad->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Verificar duplicados potenciales
     */
    private function verificarDuplicados(Especialidad $especialidad): void
    {
        try {
            // Buscar especialidades con nombres similares
            $similares = Especialidad::where('id', '!=', $especialidad->id)
                ->where(function($query) use ($especialidad) {
                    $query->where('nombre', 'LIKE', '%' . $especialidad->nombre . '%')
                          ->orWhere('nombre', 'LIKE', '%' . substr($especialidad->nombre, 0, -1) . '%');
                })
                ->limit(5)
                ->get(['id', 'nombre']);

            if ($similares->isNotEmpty()) {
                LogAuditoria::logActivity(
                    'warning',
                    'especialidades',
                    $especialidad->id,
                    auth()->id(),
                    null,
                    [
                        'tipo' => 'posibles_duplicados',
                        'especialidades_similares' => $similares->toArray(),
                        'mensaje' => 'Se encontraron especialidades con nombres similares',
                    ]
                );

                Log::info('Posibles duplicados detectados', [
                    'especialidad_nueva' => $especialidad->nombre,
                    'similares' => $similares->pluck('nombre')->toArray(),
                ]);
            }

        } catch (\Exception $e) {
            Log::debug('Error verificando duplicados', [
                'especialidad_id' => $especialidad->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Detectar cambios importantes en la especialidad
     */
    private function detectarCambiosImportantes(Especialidad $especialidad, array $cambios, array $original): void
    {
        $cambiosImportantes = [];

        // Cambio de nombre
        if (isset($cambios['nombre'])) {
            $cambiosImportantes[] = [
                'campo' => 'nombre',
                'anterior' => $original['nombre'],
                'nuevo' => $cambios['nombre'],
                'descripcion' => 'Cambio de nombre de especialidad',
            ];
        }

        // Cambio de duración de turno
        if (isset($cambios['duracion_turno'])) {
            $cambiosImportantes[] = [
                'campo' => 'duracion_turno',
                'anterior' => $original['duracion_turno'],
                'nuevo' => $cambios['duracion_turno'],
                'descripcion' => 'Cambio en duración de turnos',
            ];

            // Log específico para cambio de duración
            Log::info('Duración de turno modificada', [
                'especialidad_id' => $especialidad->id,
                'nombre' => $especialidad->nombre,
                'duracion_anterior' => $original['duracion_turno'],
                'duracion_nueva' => $cambios['duracion_turno'],
                'usuario' => auth()->user()?->name,
            ]);
        }

        // Cambio de estado activo
        if (isset($cambios['is_active'])) {
            $estado = $cambios['is_active'] ? 'activada' : 'desactivada';
            $cambiosImportantes[] = [
                'campo' => 'is_active',
                'anterior' => $original['is_active'],
                'nuevo' => $cambios['is_active'],
                'descripcion' => "Especialidad {$estado}",
            ];

            // Log específico para cambio de estado
            Log::warning("Especialidad {$estado}", [
                'especialidad_id' => $especialidad->id,
                'nombre' => $especialidad->nombre,
                'usuario' => auth()->user()?->name,
            ]);

            // Si se desactiva, verificar impacto
            if (!$cambios['is_active']) {
                $this->verificarImpactoDesactivacion($especialidad);
            }
        }

        // Registrar cambios importantes
        if (!empty($cambiosImportantes)) {
            LogAuditoria::logActivity(
                'important_change',
                'especialidades',
                $especialidad->id,
                auth()->id(),
                null,
                [
                    'tipo' => 'cambios_importantes',
                    'cambios' => $cambiosImportantes,
                ]
            );
        }
    }

    /**
     * Verificar impacto de desactivación
     */
    private function verificarImpactoDesactivacion(Especialidad $especialidad): void
    {
        try {
            $doctoresActivos = $especialidad->doctoresActivos()->count();
            $turnosFuturos = $especialidad->turnosFuturos()->count();

            if ($doctoresActivos > 0 || $turnosFuturos > 0) {
                LogAuditoria::logActivity(
                    'alert',
                    'especialidades',
                    $especialidad->id,
                    auth()->id(),
                    null,
                    [
                        'tipo' => 'impacto_desactivacion',
                        'mensaje' => 'Especialidad desactivada con datos activos',
                        'doctores_activos' => $doctoresActivos,
                        'turnos_futuros' => $turnosFuturos,
                        'requiere_revision' => true,
                    ]
                );

                Log::warning('Especialidad desactivada con impacto', [
                    'especialidad_id' => $especialidad->id,
                    'nombre' => $especialidad->nombre,
                    'doctores_activos' => $doctoresActivos,
                    'turnos_futuros' => $turnosFuturos,
                ]);
            }

        } catch (\Exception $e) {
            Log::debug('Error verificando impacto de desactivación', [
                'especialidad_id' => $especialidad->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Calcular impacto de eliminación
     */
    private function calcularImpactoEliminacion(Especialidad $especialidad): array
    {
        try {
            $doctores = $especialidad->doctores()->count();
            $turnos = $especialidad->turnos()->count();
            $horarios = $especialidad->horarios()->count();

            return [
                'tiene_datos_asociados' => $doctores > 0 || $turnos > 0 || $horarios > 0,
                'doctores_count' => $doctores,
                'turnos_count' => $turnos,
                'horarios_count' => $horarios,
            ];

        } catch (\Exception $e) {
            Log::debug('Error calculando impacto de eliminación', [
                'especialidad_id' => $especialidad->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'tiene_datos_asociados' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Crear alerta para eliminación con datos asociados
     */
    private function crearAlertaEliminacion(Especialidad $especialidad, array $impacto): void
    {
        LogAuditoria::logActivity(
            'critical_alert',
            'especialidades',
            $especialidad->id,
            auth()->id(),
            null,
            [
                'tipo' => 'eliminacion_con_datos_asociados',
                'mensaje' => 'Se eliminó una especialidad con datos médicos asociados',
                'nombre_especialidad' => $especialidad->nombre,
                'impacto' => $impacto,
                'requiere_revision' => true,
                'prioridad' => 'alta',
            ]
        );
    }

    /**
     * Invalidar caché relacionado con especialidades
     */
    private function invalidarCacheEspecialidades(): void
    {
        $cacheKeys = [
            'especialidades_activas',
            'especialidades_all',
            'especialidades_con_doctores',
            'especialidades_dropdown',
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        // También invalidar cache de configuración de turnos
        Cache::forget('configuracion_turnos_por_especialidad');
    }
}
