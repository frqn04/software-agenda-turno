<?php

namespace App\Observers;

use App\Models\Evolucion;
use App\Models\LogAuditoria;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Observer para el modelo Evolucion
 * Maneja eventos de las evoluciones médicas
 */
class EvolucionObserver
{
    /**
     * Manejar la creación de una evolución
     */
    public function created(Evolucion $evolucion): void
    {
        try {
            // Log de auditoría
            LogAuditoria::logActivity(
                'created',
                'evoluciones',
                $evolucion->id,
                auth()->id(),
                null,
                [
                    'historia_clinica_id' => $evolucion->historia_clinica_id,
                    'turno_id' => $evolucion->turno_id,
                    'doctor_id' => $evolucion->doctor_id,
                    'fecha_consulta' => $evolucion->fecha_consulta,
                    'tipo_consulta' => $evolucion->tipo_consulta,
                    'tiene_diagnostico' => !empty($evolucion->diagnostico),
                    'tiene_tratamiento' => !empty($evolucion->tratamiento),
                    'tiene_medicamentos' => !empty($evolucion->medicamentos_recetados),
                ]
            );

            // Invalidar caché relacionado
            $this->invalidarCacheRelacionado($evolucion);

            // Log específico
            Log::info('Nueva evolución médica registrada', [
                'evolucion_id' => $evolucion->id,
                'historia_clinica_id' => $evolucion->historia_clinica_id,
                'doctor_id' => $evolucion->doctor_id,
                'tipo_consulta' => $evolucion->tipo_consulta,
                'usuario' => auth()->user()?->name,
            ]);

            // Verificar evolución crítica
            $this->verificarEvolucionCritica($evolucion);

            // Actualizar estadísticas del doctor
            $this->actualizarEstadisticasDoctor($evolucion);

        } catch (\Exception $e) {
            Log::error('Error en EvolucionObserver::created', [
                'evolucion_id' => $evolucion->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar la actualización de una evolución
     */
    public function updated(Evolucion $evolucion): void
    {
        try {
            $cambios = $evolucion->getChanges();
            $original = $evolucion->getOriginal();

            // Sanitizar datos médicos para auditoría
            $cambiosSanitizados = $this->sanitizarDatosMedicos($cambios);
            $originalSanitizado = $this->sanitizarDatosMedicos($original);

            // Log de auditoría
            LogAuditoria::logActivity(
                'updated',
                'evoluciones',
                $evolucion->id,
                auth()->id(),
                $originalSanitizado,
                $cambiosSanitizados
            );

            // Invalidar caché relacionado
            $this->invalidarCacheRelacionado($evolucion);

            // Detectar cambios importantes
            $this->detectarCambiosImportantes($evolucion, $cambios);

            // Log de cambios
            Log::info('Evolución médica actualizada', [
                'evolucion_id' => $evolucion->id,
                'campos_modificados' => array_keys($cambios),
                'doctor_id' => $evolucion->doctor_id,
                'usuario' => auth()->user()?->name,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en EvolucionObserver::updated', [
                'evolucion_id' => $evolucion->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar la eliminación de una evolución
     */
    public function deleted(Evolucion $evolucion): void
    {
        try {
            // Log de auditoría crítico
            LogAuditoria::logActivity(
                'deleted',
                'evoluciones',
                $evolucion->id,
                auth()->id(),
                [
                    'historia_clinica_id' => $evolucion->historia_clinica_id,
                    'turno_id' => $evolucion->turno_id,
                    'doctor_id' => $evolucion->doctor_id,
                    'fecha_consulta' => $evolucion->fecha_consulta,
                    'tipo_consulta' => $evolucion->tipo_consulta,
                    'tenia_diagnostico' => !empty($evolucion->diagnostico),
                    'tenia_tratamiento' => !empty($evolucion->tratamiento),
                ],
                null
            );

            // Log crítico para eliminación de evolución
            Log::warning('Evolución médica eliminada', [
                'evolucion_id' => $evolucion->id,
                'historia_clinica_id' => $evolucion->historia_clinica_id,
                'doctor_id' => $evolucion->doctor_id,
                'fecha_consulta' => $evolucion->fecha_consulta,
                'usuario' => auth()->user()?->name,
                'ip' => request()->ip(),
            ]);

            // Invalidar caché relacionado
            $this->invalidarCacheRelacionado($evolucion);

            // Crear alerta para supervisión médica
            $this->crearAlertaEliminacion($evolucion);

        } catch (\Exception $e) {
            Log::error('Error en EvolucionObserver::deleted', [
                'evolucion_id' => $evolucion->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar la restauración de una evolución
     */
    public function restored(Evolucion $evolucion): void
    {
        try {
            // Log de auditoría
            LogAuditoria::logActivity(
                'restored',
                'evoluciones',
                $evolucion->id,
                auth()->id(),
                null,
                [
                    'historia_clinica_id' => $evolucion->historia_clinica_id,
                    'turno_id' => $evolucion->turno_id,
                    'doctor_id' => $evolucion->doctor_id,
                    'fecha_restauracion' => now(),
                ]
            );

            // Log de restauración
            Log::info('Evolución médica restaurada', [
                'evolucion_id' => $evolucion->id,
                'historia_clinica_id' => $evolucion->historia_clinica_id,
                'usuario' => auth()->user()?->name,
            ]);

            // Invalidar caché relacionado
            $this->invalidarCacheRelacionado($evolucion);

        } catch (\Exception $e) {
            Log::error('Error en EvolucionObserver::restored', [
                'evolucion_id' => $evolucion->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Verificar si la evolución contiene información crítica
     */
    private function verificarEvolucionCritica(Evolucion $evolucion): void
    {
        $alertas = [];
        
        // Palabras clave que indican situaciones críticas
        $palabrasCriticas = [
            'urgente' => 'Situación marcada como urgente',
            'grave' => 'Condición grave detectada',
            'critico' => 'Estado crítico',
            'emergencia' => 'Emergencia médica',
            'hospitalizar' => 'Requiere hospitalización',
            'derivar' => 'Requiere derivación',
            'alergia' => 'Nueva alergia detectada',
            'reaccion adversa' => 'Reacción adversa medicamentosa',
        ];

        // Verificar en campos relevantes
        $camposAVerificar = [
            $evolucion->motivo_consulta,
            $evolucion->diagnostico,
            $evolucion->tratamiento,
            $evolucion->observaciones,
        ];

        $textoCompleto = strtolower(implode(' ', array_filter($camposAVerificar)));

        foreach ($palabrasCriticas as $palabra => $descripcion) {
            if (str_contains($textoCompleto, $palabra)) {
                $alertas[] = $descripcion;
            }
        }

        // Verificar medicamentos críticos
        if (!empty($evolucion->medicamentos_recetados)) {
            $medicamentosCriticos = ['warfarina', 'insulina', 'morfina', 'digoxina'];
            $textoMedicamentos = strtolower($evolucion->medicamentos_recetados);
            
            foreach ($medicamentosCriticos as $medicamento) {
                if (str_contains($textoMedicamentos, $medicamento)) {
                    $alertas[] = "Medicamento crítico recetado: {$medicamento}";
                }
            }
        }

        // Registrar alertas si las hay
        if (!empty($alertas)) {
            LogAuditoria::logActivity(
                'alert',
                'evoluciones',
                $evolucion->id,
                auth()->id(),
                null,
                [
                    'tipo' => 'evolucion_critica',
                    'alertas' => $alertas,
                    'historia_clinica_id' => $evolucion->historia_clinica_id,
                    'doctor_id' => $evolucion->doctor_id,
                    'requiere_revision' => true,
                ]
            );

            Log::warning('Evolución crítica detectada', [
                'evolucion_id' => $evolucion->id,
                'alertas' => $alertas,
                'doctor_id' => $evolucion->doctor_id,
            ]);
        }
    }

    /**
     * Detectar cambios importantes en la evolución
     */
    private function detectarCambiosImportantes(Evolucion $evolucion, array $cambios): void
    {
        $camposImportantes = [
            'diagnostico' => 'Modificación de diagnóstico',
            'tratamiento' => 'Modificación de tratamiento',
            'medicamentos_recetados' => 'Modificación de medicamentos',
            'indicaciones_medicas' => 'Modificación de indicaciones médicas',
            'proxima_consulta' => 'Modificación de próxima consulta',
        ];

        $cambiosImportantes = array_intersect_key($cambios, $camposImportantes);

        if (!empty($cambiosImportantes)) {
            $descripciones = [];
            foreach ($cambiosImportantes as $campo => $valor) {
                $descripciones[] = $camposImportantes[$campo];
            }

            LogAuditoria::logActivity(
                'important_change',
                'evoluciones',
                $evolucion->id,
                auth()->id(),
                null,
                [
                    'tipo' => 'cambios_importantes_evolucion',
                    'campos_modificados' => array_keys($cambiosImportantes),
                    'descripciones' => $descripciones,
                    'doctor_id' => $evolucion->doctor_id,
                ]
            );

            Log::info('Cambios importantes en evolución médica', [
                'evolucion_id' => $evolucion->id,
                'cambios' => $descripciones,
                'doctor_id' => $evolucion->doctor_id,
                'usuario' => auth()->user()?->name,
            ]);
        }
    }

    /**
     * Sanitizar datos médicos sensibles
     */
    private function sanitizarDatosMedicos(array $datos): array
    {
        $camposSensibles = [
            'observaciones_privadas',
            'notas_confidenciales',
        ];

        foreach ($camposSensibles as $campo) {
            if (isset($datos[$campo]) && !empty($datos[$campo])) {
                $datos[$campo] = '[DATOS MÉDICOS CONFIDENCIALES - ' . strlen($datos[$campo]) . ' caracteres]';
            }
        }

        return $datos;
    }

    /**
     * Invalidar caché relacionado con la evolución
     */
    private function invalidarCacheRelacionado(Evolucion $evolucion): void
    {
        $cacheKeys = [
            "historia_clinica_{$evolucion->historia_clinica_id}_evoluciones",
            "doctor_{$evolucion->doctor_id}_evoluciones_recientes",
            "turno_{$evolucion->turno_id}_evolucion",
        ];

        if ($evolucion->historiaClinica) {
            $cacheKeys[] = "paciente_{$evolucion->historiaClinica->paciente_id}_evoluciones";
        }

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Actualizar estadísticas del doctor
     */
    private function actualizarEstadisticasDoctor(Evolucion $evolucion): void
    {
        try {
            // Incrementar contador de consultas del doctor
            Cache::increment("doctor_{$evolucion->doctor_id}_consultas_mes_" . now()->format('Y-m'));
            
            // Invalidar estadísticas del doctor para recalcular
            Cache::forget("doctor_{$evolucion->doctor_id}_estadisticas");
            
        } catch (\Exception $e) {
            Log::debug('Error actualizando estadísticas del doctor', [
                'doctor_id' => $evolucion->doctor_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Crear alerta para eliminación de evolución
     */
    private function crearAlertaEliminacion(Evolucion $evolucion): void
    {
        LogAuditoria::logActivity(
            'medical_alert',
            'evoluciones',
            $evolucion->id,
            auth()->id(),
            null,
            [
                'tipo' => 'eliminacion_evolucion',
                'mensaje' => 'Se eliminó una evolución médica',
                'historia_clinica_id' => $evolucion->historia_clinica_id,
                'doctor_id' => $evolucion->doctor_id,
                'fecha_consulta' => $evolucion->fecha_consulta,
                'requiere_revision' => true,
                'prioridad' => 'media',
            ]
        );
    }
}
