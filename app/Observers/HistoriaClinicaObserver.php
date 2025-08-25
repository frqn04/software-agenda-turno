<?php

namespace App\Observers;

use App\Models\HistoriaClinica;
use App\Models\LogAuditoria;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Observer para el modelo HistoriaClinica
 * Maneja eventos críticos de las historias clínicas
 */
class HistoriaClinicaObserver
{
    /**
     * Manejar la creación de una historia clínica
     */
    public function created(HistoriaClinica $historia): void
    {
        try {
            // Log de auditoría con datos sanitizados
            LogAuditoria::logActivity(
                'created',
                'historias_clinicas',
                $historia->id,
                auth()->id(),
                null,
                [
                    'paciente_id' => $historia->paciente_id,
                    'numero_historia' => $historia->numero_historia,
                    'fecha_apertura' => $historia->fecha_apertura,
                    'estado' => $historia->estado,
                    'tiene_antecedentes' => !empty($historia->antecedentes_personales),
                    'tiene_alergias' => !empty($historia->alergias),
                ]
            );

            // Invalidar caché del paciente
            $this->invalidarCachePaciente($historia->paciente_id);

            // Log específico para nueva historia
            Log::info('Nueva historia clínica creada', [
                'historia_id' => $historia->id,
                'paciente_id' => $historia->paciente_id,
                'numero_historia' => $historia->numero_historia,
                'usuario_creador' => auth()->user()?->name,
            ]);

            // Verificar datos críticos
            $this->verificarDatosCriticos($historia);

        } catch (\Exception $e) {
            Log::error('Error en HistoriaClinicaObserver::created', [
                'historia_id' => $historia->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar la actualización de una historia clínica
     */
    public function updated(HistoriaClinica $historia): void
    {
        try {
            $cambios = $historia->getChanges();
            $original = $historia->getOriginal();

            // Sanitizar datos médicos sensibles para auditoría
            $cambiosSanitizados = $this->sanitizarDatosMedicos($cambios);
            $originalSanitizado = $this->sanitizarDatosMedicos($original);

            // Log de auditoría
            LogAuditoria::logActivity(
                'updated',
                'historias_clinicas',
                $historia->id,
                auth()->id(),
                $originalSanitizado,
                $cambiosSanitizados
            );

            // Invalidar caché del paciente
            $this->invalidarCachePaciente($historia->paciente_id);

            // Detectar cambios importantes
            $this->detectarCambiosImportantes($historia, $cambios);

            // Log de cambios
            Log::info('Historia clínica actualizada', [
                'historia_id' => $historia->id,
                'campos_modificados' => array_keys($cambios),
                'usuario' => auth()->user()?->name,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en HistoriaClinicaObserver::updated', [
                'historia_id' => $historia->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar la eliminación de una historia clínica
     */
    public function deleted(HistoriaClinica $historia): void
    {
        try {
            // Log de auditoría crítico - eliminación de historia clínica es muy sensible
            LogAuditoria::logActivity(
                'deleted',
                'historias_clinicas',
                $historia->id,
                auth()->id(),
                [
                    'paciente_id' => $historia->paciente_id,
                    'numero_historia' => $historia->numero_historia,
                    'fecha_apertura' => $historia->fecha_apertura,
                    'estado' => $historia->estado,
                    'tenia_evoluciones' => $historia->evoluciones()->count() > 0,
                ],
                null
            );

            // Log crítico para eliminación
            Log::critical('Historia clínica eliminada', [
                'historia_id' => $historia->id,
                'paciente_id' => $historia->paciente_id,
                'numero_historia' => $historia->numero_historia,
                'usuario' => auth()->user()?->name,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            // Invalidar caché del paciente
            $this->invalidarCachePaciente($historia->paciente_id);

            // Crear alerta para administradores
            $this->crearAlertaEliminacion($historia);

        } catch (\Exception $e) {
            Log::error('Error en HistoriaClinicaObserver::deleted', [
                'historia_id' => $historia->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar la restauración de una historia clínica
     */
    public function restored(HistoriaClinica $historia): void
    {
        try {
            // Log de auditoría
            LogAuditoria::logActivity(
                'restored',
                'historias_clinicas',
                $historia->id,
                auth()->id(),
                null,
                [
                    'paciente_id' => $historia->paciente_id,
                    'numero_historia' => $historia->numero_historia,
                    'fecha_restauracion' => now(),
                ]
            );

            // Log de restauración
            Log::warning('Historia clínica restaurada', [
                'historia_id' => $historia->id,
                'paciente_id' => $historia->paciente_id,
                'usuario' => auth()->user()?->name,
            ]);

            // Invalidar caché del paciente
            $this->invalidarCachePaciente($historia->paciente_id);

        } catch (\Exception $e) {
            Log::error('Error en HistoriaClinicaObserver::restored', [
                'historia_id' => $historia->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar la eliminación permanente de una historia clínica
     */
    public function forceDeleted(HistoriaClinica $historia): void
    {
        try {
            // Log de auditoría crítico
            LogAuditoria::logActivity(
                'force_deleted',
                'historias_clinicas',
                $historia->id,
                auth()->id(),
                [
                    'paciente_id' => $historia->paciente_id,
                    'numero_historia' => $historia->numero_historia,
                    'fecha_apertura' => $historia->fecha_apertura,
                    'eliminacion_permanente' => true,
                ],
                null
            );

            // Log crítico para eliminación permanente
            Log::critical('Historia clínica eliminada permanentemente', [
                'historia_id' => $historia->id,
                'paciente_id' => $historia->paciente_id,
                'numero_historia' => $historia->numero_historia,
                'usuario' => auth()->user()?->name,
                'ip' => request()->ip(),
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error en HistoriaClinicaObserver::forceDeleted', [
                'historia_id' => $historia->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Verificar datos críticos de la historia clínica
     */
    private function verificarDatosCriticos(HistoriaClinica $historia): void
    {
        $alertas = [];

        // Verificar alergias críticas
        if (!empty($historia->alergias)) {
            $alergiasCriticas = ['penicilina', 'aspirina', 'latex', 'anestesia'];
            $textoAlergias = strtolower($historia->alergias);
            
            foreach ($alergiasCriticas as $alergia) {
                if (str_contains($textoAlergias, $alergia)) {
                    $alertas[] = "Alergia crítica detectada: {$alergia}";
                }
            }
        }

        // Verificar antecedentes críticos
        if (!empty($historia->antecedentes_personales)) {
            $antecedentesRiesgo = ['diabetes', 'hipertension', 'cardiopatia', 'epilepsia'];
            $textoAntecedentes = strtolower($historia->antecedentes_personales);
            
            foreach ($antecedentesRiesgo as $antecedente) {
                if (str_contains($textoAntecedentes, $antecedente)) {
                    $alertas[] = "Antecedente de riesgo: {$antecedente}";
                }
            }
        }

        // Registrar alertas si las hay
        if (!empty($alertas)) {
            LogAuditoria::logActivity(
                'alert',
                'historias_clinicas',
                $historia->id,
                auth()->id(),
                null,
                [
                    'tipo' => 'datos_criticos_detectados',
                    'alertas' => $alertas,
                    'paciente_id' => $historia->paciente_id,
                ]
            );

            Log::warning('Datos críticos detectados en historia clínica', [
                'historia_id' => $historia->id,
                'alertas' => $alertas,
            ]);
        }
    }

    /**
     * Detectar cambios importantes en la historia clínica
     */
    private function detectarCambiosImportantes(HistoriaClinica $historia, array $cambios): void
    {
        $camposImportantes = [
            'alergias' => 'Modificación de alergias',
            'antecedentes_personales' => 'Modificación de antecedentes personales',
            'antecedentes_familiares' => 'Modificación de antecedentes familiares',
            'medicamentos_actuales' => 'Modificación de medicamentos actuales',
            'estado' => 'Cambio de estado de historia',
        ];

        $cambiosImportantes = array_intersect_key($cambios, $camposImportantes);

        if (!empty($cambiosImportantes)) {
            $descripciones = [];
            foreach ($cambiosImportantes as $campo => $valor) {
                $descripciones[] = $camposImportantes[$campo];
            }

            LogAuditoria::logActivity(
                'important_change',
                'historias_clinicas',
                $historia->id,
                auth()->id(),
                null,
                [
                    'tipo' => 'cambios_importantes',
                    'campos_modificados' => array_keys($cambiosImportantes),
                    'descripciones' => $descripciones,
                ]
            );

            Log::warning('Cambios importantes en historia clínica', [
                'historia_id' => $historia->id,
                'cambios' => $descripciones,
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
            'notas_internas',
        ];

        foreach ($camposSensibles as $campo) {
            if (isset($datos[$campo]) && !empty($datos[$campo])) {
                $datos[$campo] = '[DATOS MÉDICOS - ' . strlen($datos[$campo]) . ' caracteres]';
            }
        }

        return $datos;
    }

    /**
     * Invalidar caché relacionado con el paciente
     */
    private function invalidarCachePaciente(int $pacienteId): void
    {
        $cacheKeys = [
            "paciente_{$pacienteId}_historia_clinica",
            "paciente_{$pacienteId}_evoluciones",
            "paciente_{$pacienteId}_summary",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Crear alerta para eliminación de historia clínica
     */
    private function crearAlertaEliminacion(HistoriaClinica $historia): void
    {
        LogAuditoria::logActivity(
            'critical_alert',
            'historias_clinicas',
            $historia->id,
            auth()->id(),
            null,
            [
                'tipo' => 'eliminacion_historia_clinica',
                'mensaje' => 'Se eliminó una historia clínica',
                'paciente_id' => $historia->paciente_id,
                'numero_historia' => $historia->numero_historia,
                'requiere_revision' => true,
                'prioridad' => 'alta',
            ]
        );
    }
}
