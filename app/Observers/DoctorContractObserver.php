<?php

namespace App\Observers;

use App\Models\DoctorContract;
use App\Models\LogAuditoria;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Observer para el modelo DoctorContract
 * Maneja eventos críticos de los contratos de doctores
 */
class DoctorContractObserver
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Manejar la creación de un contrato
     */
    public function created(DoctorContract $contrato): void
    {
        try {
            // Log de auditoría
            LogAuditoria::logActivity(
                'created',
                'doctor_contracts',
                $contrato->id,
                auth()->id(),
                null,
                [
                    'doctor_id' => $contrato->doctor_id,
                    'tipo_contrato' => $contrato->tipo_contrato,
                    'fecha_inicio' => $contrato->fecha_inicio,
                    'fecha_fin' => $contrato->fecha_fin,
                    'is_active' => $contrato->is_active,
                    'salario_base' => $contrato->salario_base,
                ]
            );

            // Invalidar caché del doctor
            $this->invalidarCacheDoctor($contrato->doctor_id);

            // Log específico
            Log::info('Nuevo contrato de doctor creado', [
                'contrato_id' => $contrato->id,
                'doctor_id' => $contrato->doctor_id,
                'tipo_contrato' => $contrato->tipo_contrato,
                'fecha_inicio' => $contrato->fecha_inicio,
                'fecha_fin' => $contrato->fecha_fin,
                'usuario' => auth()->user()?->name,
            ]);

            // Verificar conflictos con otros contratos
            $this->verificarConflictosContratos($contrato);

            // Verificar fechas del contrato
            $this->verificarFechasContrato($contrato);

            // Notificar al doctor sobre el nuevo contrato
            $this->notificarNuevoContrato($contrato);

        } catch (\Exception $e) {
            Log::error('Error en DoctorContractObserver::created', [
                'contrato_id' => $contrato->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar la actualización de un contrato
     */
    public function updated(DoctorContract $contrato): void
    {
        try {
            $cambios = $contrato->getChanges();
            $original = $contrato->getOriginal();

            // Log de auditoría
            LogAuditoria::logActivity(
                'updated',
                'doctor_contracts',
                $contrato->id,
                auth()->id(),
                $original,
                $cambios
            );

            // Invalidar caché del doctor
            $this->invalidarCacheDoctor($contrato->doctor_id);

            // Detectar cambios críticos
            $this->detectarCambiosCriticos($contrato, $cambios, $original);

            // Log de cambios
            Log::info('Contrato de doctor actualizado', [
                'contrato_id' => $contrato->id,
                'doctor_id' => $contrato->doctor_id,
                'campos_modificados' => array_keys($cambios),
                'usuario' => auth()->user()?->name,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en DoctorContractObserver::updated', [
                'contrato_id' => $contrato->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar la eliminación de un contrato
     */
    public function deleted(DoctorContract $contrato): void
    {
        try {
            // Verificar impacto de la eliminación
            $impacto = $this->calcularImpactoEliminacion($contrato);

            // Log de auditoría
            LogAuditoria::logActivity(
                'deleted',
                'doctor_contracts',
                $contrato->id,
                auth()->id(),
                [
                    'doctor_id' => $contrato->doctor_id,
                    'tipo_contrato' => $contrato->tipo_contrato,
                    'fecha_inicio' => $contrato->fecha_inicio,
                    'fecha_fin' => $contrato->fecha_fin,
                    'is_active' => $contrato->is_active,
                    'salario_base' => $contrato->salario_base,
                    'impacto_eliminacion' => $impacto,
                ],
                null
            );

            // Invalidar caché del doctor
            $this->invalidarCacheDoctor($contrato->doctor_id);

            // Log crítico
            Log::warning('Contrato de doctor eliminado', [
                'contrato_id' => $contrato->id,
                'doctor_id' => $contrato->doctor_id,
                'tipo_contrato' => $contrato->tipo_contrato,
                'estaba_activo' => $contrato->is_active,
                'impacto' => $impacto,
                'usuario' => auth()->user()?->name,
            ]);

            // Crear alerta si era un contrato activo
            if ($contrato->is_active) {
                $this->crearAlertaEliminacionContratoActivo($contrato);
            }

            // Notificar eliminación
            $this->notificarEliminacionContrato($contrato);

        } catch (\Exception $e) {
            Log::error('Error en DoctorContractObserver::deleted', [
                'contrato_id' => $contrato->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar la restauración de un contrato
     */
    public function restored(DoctorContract $contrato): void
    {
        try {
            // Log de auditoría
            LogAuditoria::logActivity(
                'restored',
                'doctor_contracts',
                $contrato->id,
                auth()->id(),
                null,
                [
                    'doctor_id' => $contrato->doctor_id,
                    'tipo_contrato' => $contrato->tipo_contrato,
                    'fecha_restauracion' => now(),
                ]
            );

            // Invalidar caché del doctor
            $this->invalidarCacheDoctor($contrato->doctor_id);

            // Log de restauración
            Log::info('Contrato de doctor restaurado', [
                'contrato_id' => $contrato->id,
                'doctor_id' => $contrato->doctor_id,
                'usuario' => auth()->user()?->name,
            ]);

            // Verificar si la restauración es válida
            $this->verificarValidezRestauracion($contrato);

        } catch (\Exception $e) {
            Log::error('Error en DoctorContractObserver::restored', [
                'contrato_id' => $contrato->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Verificar conflictos con otros contratos del mismo doctor
     */
    private function verificarConflictosContratos(DoctorContract $contrato): void
    {
        try {
            $conflictos = DoctorContract::where('doctor_id', $contrato->doctor_id)
                ->where('id', '!=', $contrato->id)
                ->where('is_active', true)
                ->where(function($query) use ($contrato) {
                    $query->whereBetween('fecha_inicio', [$contrato->fecha_inicio, $contrato->fecha_fin])
                          ->orWhereBetween('fecha_fin', [$contrato->fecha_inicio, $contrato->fecha_fin])
                          ->orWhere(function($q) use ($contrato) {
                              $q->where('fecha_inicio', '<=', $contrato->fecha_inicio)
                                ->where('fecha_fin', '>=', $contrato->fecha_fin);
                          });
                })
                ->get(['id', 'tipo_contrato', 'fecha_inicio', 'fecha_fin']);

            if ($conflictos->isNotEmpty()) {
                LogAuditoria::logActivity(
                    'alert',
                    'doctor_contracts',
                    $contrato->id,
                    auth()->id(),
                    null,
                    [
                        'tipo' => 'conflicto_contratos',
                        'mensaje' => 'Se detectaron conflictos con otros contratos del doctor',
                        'contratos_conflictivos' => $conflictos->toArray(),
                        'requiere_revision' => true,
                    ]
                );

                Log::warning('Conflictos de contrato detectados', [
                    'contrato_id' => $contrato->id,
                    'doctor_id' => $contrato->doctor_id,
                    'conflictos_count' => $conflictos->count(),
                    'conflictos' => $conflictos->toArray(),
                ]);
            }

        } catch (\Exception $e) {
            Log::debug('Error verificando conflictos de contratos', [
                'contrato_id' => $contrato->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Verificar fechas del contrato
     */
    private function verificarFechasContrato(DoctorContract $contrato): void
    {
        $fechaInicio = Carbon::parse($contrato->fecha_inicio);
        $fechaFin = $contrato->fecha_fin ? Carbon::parse($contrato->fecha_fin) : null;
        $alertas = [];

        // Verificar si la fecha de inicio es en el pasado
        if ($fechaInicio->isPast()) {
            $alertas[] = 'Fecha de inicio en el pasado';
        }

        // Verificar si la fecha de fin es válida
        if ($fechaFin) {
            if ($fechaFin->isBefore($fechaInicio)) {
                $alertas[] = 'Fecha de fin anterior a fecha de inicio';
            }

            if ($fechaFin->isPast()) {
                $alertas[] = 'Fecha de fin en el pasado';
            }

            // Verificar contratos muy cortos (menos de 30 días)
            if ($fechaInicio->diffInDays($fechaFin) < 30) {
                $alertas[] = 'Contrato de duración muy corta (menos de 30 días)';
            }
        }

        // Verificar contratos muy largos (más de 5 años)
        if ($fechaFin && $fechaInicio->diffInYears($fechaFin) > 5) {
            $alertas[] = 'Contrato de duración muy larga (más de 5 años)';
        }

        if (!empty($alertas)) {
            LogAuditoria::logActivity(
                'warning',
                'doctor_contracts',
                $contrato->id,
                auth()->id(),
                null,
                [
                    'tipo' => 'fechas_contrato_inusuales',
                    'alertas' => $alertas,
                    'fecha_inicio' => $contrato->fecha_inicio,
                    'fecha_fin' => $contrato->fecha_fin,
                ]
            );

            Log::info('Fechas de contrato inusuales detectadas', [
                'contrato_id' => $contrato->id,
                'alertas' => $alertas,
            ]);
        }
    }

    /**
     * Detectar cambios críticos en el contrato
     */
    private function detectarCambiosCriticos(DoctorContract $contrato, array $cambios, array $original): void
    {
        $cambiosCriticos = [];

        // Cambio de salario
        if (isset($cambios['salario_base'])) {
            $cambiosCriticos[] = [
                'campo' => 'salario_base',
                'anterior' => $original['salario_base'],
                'nuevo' => $cambios['salario_base'],
                'descripcion' => 'Modificación de salario base',
            ];

            Log::info('Salario de contrato modificado', [
                'contrato_id' => $contrato->id,
                'doctor_id' => $contrato->doctor_id,
                'salario_anterior' => $original['salario_base'],
                'salario_nuevo' => $cambios['salario_base'],
            ]);
        }

        // Cambio de fechas
        if (isset($cambios['fecha_inicio']) || isset($cambios['fecha_fin'])) {
            $cambiosCriticos[] = [
                'campo' => 'fechas_contrato',
                'anterior' => [
                    'inicio' => $original['fecha_inicio'] ?? null,
                    'fin' => $original['fecha_fin'] ?? null,
                ],
                'nuevo' => [
                    'inicio' => $cambios['fecha_inicio'] ?? $contrato->fecha_inicio,
                    'fin' => $cambios['fecha_fin'] ?? $contrato->fecha_fin,
                ],
                'descripcion' => 'Modificación de fechas de contrato',
            ];

            Log::warning('Fechas de contrato modificadas', [
                'contrato_id' => $contrato->id,
                'doctor_id' => $contrato->doctor_id,
                'cambios_fechas' => [
                    'anterior' => [
                        'inicio' => $original['fecha_inicio'] ?? null,
                        'fin' => $original['fecha_fin'] ?? null,
                    ],
                    'nuevo' => [
                        'inicio' => $cambios['fecha_inicio'] ?? $contrato->fecha_inicio,
                        'fin' => $cambios['fecha_fin'] ?? $contrato->fecha_fin,
                    ],
                ],
            ]);
        }

        // Cambio de estado activo
        if (isset($cambios['is_active'])) {
            $estado = $cambios['is_active'] ? 'activado' : 'desactivado';
            $cambiosCriticos[] = [
                'campo' => 'is_active',
                'anterior' => $original['is_active'],
                'nuevo' => $cambios['is_active'],
                'descripcion' => "Contrato {$estado}",
            ];

            Log::warning("Contrato {$estado}", [
                'contrato_id' => $contrato->id,
                'doctor_id' => $contrato->doctor_id,
                'usuario' => auth()->user()?->name,
            ]);
        }

        // Cambio de tipo de contrato
        if (isset($cambios['tipo_contrato'])) {
            $cambiosCriticos[] = [
                'campo' => 'tipo_contrato',
                'anterior' => $original['tipo_contrato'],
                'nuevo' => $cambios['tipo_contrato'],
                'descripcion' => 'Cambio de tipo de contrato',
            ];
        }

        // Registrar cambios críticos
        if (!empty($cambiosCriticos)) {
            LogAuditoria::logActivity(
                'important_change',
                'doctor_contracts',
                $contrato->id,
                auth()->id(),
                null,
                [
                    'tipo' => 'cambios_criticos_contrato',
                    'cambios' => $cambiosCriticos,
                    'doctor_id' => $contrato->doctor_id,
                ]
            );

            // Notificar cambios importantes
            $this->notificarCambiosImportantes($contrato, $cambiosCriticos);
        }
    }

    /**
     * Calcular impacto de eliminación de contrato
     */
    private function calcularImpactoEliminacion(DoctorContract $contrato): array
    {
        try {
            $turnosFuturos = 0;
            $horariosActivos = 0;

            if ($contrato->doctor) {
                $turnosFuturos = $contrato->doctor->turnos()
                    ->where('fecha', '>=', now()->toDateString())
                    ->count();

                $horariosActivos = $contrato->doctor->horariosActivos()->count();
            }

            return [
                'tiene_impacto' => $contrato->is_active || $turnosFuturos > 0 || $horariosActivos > 0,
                'es_contrato_activo' => $contrato->is_active,
                'turnos_futuros' => $turnosFuturos,
                'horarios_activos' => $horariosActivos,
            ];

        } catch (\Exception $e) {
            return [
                'tiene_impacto' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verificar validez de restauración
     */
    private function verificarValidezRestauracion(DoctorContract $contrato): void
    {
        $fechaFin = $contrato->fecha_fin ? Carbon::parse($contrato->fecha_fin) : null;

        if ($fechaFin && $fechaFin->isPast()) {
            Log::warning('Contrato restaurado con fecha de fin pasada', [
                'contrato_id' => $contrato->id,
                'doctor_id' => $contrato->doctor_id,
                'fecha_fin' => $contrato->fecha_fin,
                'usuario' => auth()->user()?->name,
            ]);

            LogAuditoria::logActivity(
                'alert',
                'doctor_contracts',
                $contrato->id,
                auth()->id(),
                null,
                [
                    'tipo' => 'restauracion_contrato_vencido',
                    'mensaje' => 'Se restauró un contrato con fecha de fin pasada',
                    'fecha_fin' => $contrato->fecha_fin,
                ]
            );
        }
    }

    /**
     * Notificaciones
     */
    private function notificarNuevoContrato(DoctorContract $contrato): void
    {
        try {
            if ($contrato->doctor && $contrato->doctor->user) {
                $this->notificationService->enviarNotificacionContrato($contrato, 'nuevo');
            }
        } catch (\Exception $e) {
            Log::debug('Error enviando notificación de nuevo contrato', [
                'contrato_id' => $contrato->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notificarCambiosImportantes(DoctorContract $contrato, array $cambios): void
    {
        try {
            if ($contrato->doctor && $contrato->doctor->user) {
                $this->notificationService->enviarNotificacionContrato($contrato, 'modificado', $cambios);
            }
        } catch (\Exception $e) {
            Log::debug('Error enviando notificación de cambios en contrato', [
                'contrato_id' => $contrato->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notificarEliminacionContrato(DoctorContract $contrato): void
    {
        try {
            if ($contrato->doctor && $contrato->doctor->user) {
                $this->notificationService->enviarNotificacionContrato($contrato, 'eliminado');
            }
        } catch (\Exception $e) {
            Log::debug('Error enviando notificación de eliminación de contrato', [
                'contrato_id' => $contrato->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Crear alerta para eliminación de contrato activo
     */
    private function crearAlertaEliminacionContratoActivo(DoctorContract $contrato): void
    {
        LogAuditoria::logActivity(
            'critical_alert',
            'doctor_contracts',
            $contrato->id,
            auth()->id(),
            null,
            [
                'tipo' => 'eliminacion_contrato_activo',
                'mensaje' => 'Se eliminó un contrato activo de doctor',
                'doctor_id' => $contrato->doctor_id,
                'tipo_contrato' => $contrato->tipo_contrato,
                'requiere_revision_inmediata' => true,
                'prioridad' => 'critica',
            ]
        );
    }

    /**
     * Invalidar caché relacionado con el doctor
     */
    private function invalidarCacheDoctor(int $doctorId): void
    {
        $cacheKeys = [
            "doctor_{$doctorId}_contratos",
            "doctor_{$doctorId}_contrato_activo",
            "doctor_{$doctorId}_estadisticas",
            'doctores_con_contratos_activos',
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }
}
