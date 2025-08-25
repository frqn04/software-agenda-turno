<?php

namespace App\Observers;

use App\Models\User;
use App\Models\LogAuditoria;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

/**
 * Observer para el modelo User
 * Maneja eventos de seguridad y auditoría de usuarios
 */
class UserObserver
{
    /**
     * Manejar la creación de un usuario
     */
    public function created(User $user): void
    {
        try {
            // Log de auditoría
            LogAuditoria::logActivity(
                'created',
                'users',
                $user->id,
                auth()->id(),
                null,
                [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'created_by' => auth()->user()?->name,
                ]
            );

            // Log específico para creación de usuario
            Log::info('Nuevo usuario creado', [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'created_by' => auth()->user()?->name,
                'ip' => request()->ip(),
            ]);

            // Verificar rol asignado
            $this->verificarRolAsignado($user);

            // Alerta para usuarios con roles administrativos
            if (in_array($user->role, ['administrador', 'super_admin'])) {
                $this->alertaUsuarioAdministrativo($user);
            }

        } catch (\Exception $e) {
            Log::error('Error en UserObserver::created', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar la actualización de un usuario
     */
    public function updated(User $user): void
    {
        try {
            $cambios = $user->getChanges();
            $original = $user->getOriginal();

            // Sanitizar datos sensibles
            $cambiosSanitizados = $this->sanitizarDatosUsuario($cambios);
            $originalSanitizado = $this->sanitizarDatosUsuario($original);

            // Log de auditoría
            LogAuditoria::logActivity(
                'updated',
                'users',
                $user->id,
                auth()->id(),
                $originalSanitizado,
                $cambiosSanitizados
            );

            // Detectar cambios críticos de seguridad
            $this->detectarCambiosCriticos($user, $cambios, $original);

            // Log de cambios
            Log::info('Usuario actualizado', [
                'user_id' => $user->id,
                'campos_modificados' => array_keys($cambios),
                'modified_by' => auth()->user()?->name,
                'ip' => request()->ip(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error en UserObserver::updated', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar la eliminación de un usuario
     */
    public function deleted(User $user): void
    {
        try {
            // Log de auditoría crítico
            LogAuditoria::logActivity(
                'deleted',
                'users',
                $user->id,
                auth()->id(),
                [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'ultimo_acceso' => $user->ultimo_acceso,
                ],
                null
            );

            // Log crítico para eliminación de usuario
            Log::warning('Usuario eliminado', [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'deleted_by' => auth()->user()?->name,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            // Alerta especial para usuarios administrativos
            if (in_array($user->role, ['administrador', 'super_admin'])) {
                $this->alertaEliminacionAdministrativo($user);
            }

            // Verificar si tenía datos médicos asociados
            $this->verificarDatosMedicosAsociados($user);

        } catch (\Exception $e) {
            Log::error('Error en UserObserver::deleted', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Manejar la restauración de un usuario
     */
    public function restored(User $user): void
    {
        try {
            // Log de auditoría
            LogAuditoria::logActivity(
                'restored',
                'users',
                $user->id,
                auth()->id(),
                null,
                [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'fecha_restauracion' => now(),
                    'restored_by' => auth()->user()?->name,
                ]
            );

            // Log de restauración
            Log::info('Usuario restaurado', [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'restored_by' => auth()->user()?->name,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en UserObserver::restored', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Detectar cambios críticos de seguridad
     */
    private function detectarCambiosCriticos(User $user, array $cambios, array $original): void
    {
        $cambiosCriticos = [];

        // Cambio de contraseña
        if (isset($cambios['password'])) {
            $cambiosCriticos[] = 'Contraseña modificada';
            
            Log::warning('Contraseña de usuario cambiada', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'changed_by' => auth()->user()?->name,
                'ip' => request()->ip(),
            ]);
        }

        // Cambio de email
        if (isset($cambios['email'])) {
            $cambiosCriticos[] = 'Email modificado';
            
            Log::warning('Email de usuario cambiado', [
                'user_id' => $user->id,
                'email_anterior' => $original['email'],
                'email_nuevo' => $cambios['email'],
                'changed_by' => auth()->user()?->name,
            ]);
        }

        // Cambio de rol
        if (isset($cambios['role'])) {
            $cambiosCriticos[] = 'Rol modificado';
            
            Log::warning('Rol de usuario cambiado', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'rol_anterior' => $original['role'],
                'rol_nuevo' => $cambios['role'],
                'changed_by' => auth()->user()?->name,
            ]);

            // Alerta especial para elevación de privilegios
            if ($this->esElevacionPrivilegios($original['role'], $cambios['role'])) {
                $this->alertaElevacionPrivilegios($user, $original['role'], $cambios['role']);
            }
        }

        // Cambio de estado activo
        if (isset($cambios['is_active'])) {
            $estado = $cambios['is_active'] ? 'activado' : 'desactivado';
            $cambiosCriticos[] = "Usuario {$estado}";
            
            Log::warning("Usuario {$estado}", [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'estado_anterior' => $original['is_active'] ? 'activo' : 'inactivo',
                'estado_nuevo' => $cambios['is_active'] ? 'activo' : 'inactivo',
                'changed_by' => auth()->user()?->name,
            ]);
        }

        // Registro de cambios críticos
        if (!empty($cambiosCriticos)) {
            LogAuditoria::logActivity(
                'security_change',
                'users',
                $user->id,
                auth()->id(),
                null,
                [
                    'tipo' => 'cambios_criticos_seguridad',
                    'cambios' => $cambiosCriticos,
                    'requiere_revision' => true,
                    'prioridad' => 'alta',
                ]
            );
        }
    }

    /**
     * Verificar si es una elevación de privilegios
     */
    private function esElevacionPrivilegios(string $rolAnterior, string $rolNuevo): bool
    {
        $jerarquiaRoles = [
            'paciente' => 1,
            'doctor' => 2,
            'recepcionista' => 3,
            'administrador' => 4,
            'super_admin' => 5,
        ];

        $nivelAnterior = $jerarquiaRoles[$rolAnterior] ?? 0;
        $nivelNuevo = $jerarquiaRoles[$rolNuevo] ?? 0;

        return $nivelNuevo > $nivelAnterior;
    }

    /**
     * Alerta para elevación de privilegios
     */
    private function alertaElevacionPrivilegios(User $user, string $rolAnterior, string $rolNuevo): void
    {
        LogAuditoria::logActivity(
            'security_alert',
            'users',
            $user->id,
            auth()->id(),
            null,
            [
                'tipo' => 'elevacion_privilegios',
                'mensaje' => 'Se detectó elevación de privilegios de usuario',
                'rol_anterior' => $rolAnterior,
                'rol_nuevo' => $rolNuevo,
                'user_email' => $user->email,
                'requiere_revision_inmediata' => true,
                'prioridad' => 'critica',
            ]
        );

        Log::critical('Elevación de privilegios detectada', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'rol_anterior' => $rolAnterior,
            'rol_nuevo' => $rolNuevo,
            'changed_by' => auth()->user()?->name,
            'ip' => request()->ip(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Verificar rol asignado a nuevo usuario
     */
    private function verificarRolAsignado(User $user): void
    {
        $rolesValidos = ['paciente', 'doctor', 'recepcionista', 'administrador', 'super_admin'];
        
        if (!in_array($user->role, $rolesValidos)) {
            Log::warning('Usuario creado con rol no válido', [
                'user_id' => $user->id,
                'rol_asignado' => $user->role,
                'roles_validos' => $rolesValidos,
            ]);
        }
    }

    /**
     * Alerta para usuario administrativo creado
     */
    private function alertaUsuarioAdministrativo(User $user): void
    {
        LogAuditoria::logActivity(
            'admin_alert',
            'users',
            $user->id,
            auth()->id(),
            null,
            [
                'tipo' => 'usuario_administrativo_creado',
                'mensaje' => 'Se creó un usuario con privilegios administrativos',
                'role' => $user->role,
                'email' => $user->email,
                'requiere_revision' => true,
            ]
        );

        Log::warning('Usuario administrativo creado', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'created_by' => auth()->user()?->name,
        ]);
    }

    /**
     * Alerta para eliminación de usuario administrativo
     */
    private function alertaEliminacionAdministrativo(User $user): void
    {
        LogAuditoria::logActivity(
            'critical_alert',
            'users',
            $user->id,
            auth()->id(),
            null,
            [
                'tipo' => 'eliminacion_usuario_administrativo',
                'mensaje' => 'Se eliminó un usuario con privilegios administrativos',
                'role' => $user->role,
                'email' => $user->email,
                'requiere_revision_inmediata' => true,
                'prioridad' => 'critica',
            ]
        );

        Log::critical('Usuario administrativo eliminado', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'deleted_by' => auth()->user()?->name,
            'ip' => request()->ip(),
        ]);
    }

    /**
     * Verificar datos médicos asociados al usuario eliminado
     */
    private function verificarDatosMedicosAsociados(User $user): void
    {
        try {
            $datosAsociados = [];

            // Si es doctor
            if ($user->role === 'doctor' && $user->doctor) {
                $turnos = $user->doctor->turnos()->count();
                $evoluciones = $user->doctor->evoluciones()->count();
                
                if ($turnos > 0 || $evoluciones > 0) {
                    $datosAsociados['doctor'] = [
                        'turnos' => $turnos,
                        'evoluciones' => $evoluciones,
                    ];
                }
            }

            // Si es paciente
            if ($user->role === 'paciente' && $user->paciente) {
                $turnos = $user->paciente->turnos()->count();
                $historias = $user->paciente->historiasClinicas()->count();
                
                if ($turnos > 0 || $historias > 0) {
                    $datosAsociados['paciente'] = [
                        'turnos' => $turnos,
                        'historias_clinicas' => $historias,
                    ];
                }
            }

            if (!empty($datosAsociados)) {
                LogAuditoria::logActivity(
                    'data_alert',
                    'users',
                    $user->id,
                    auth()->id(),
                    null,
                    [
                        'tipo' => 'datos_medicos_asociados',
                        'mensaje' => 'Usuario eliminado tenía datos médicos asociados',
                        'datos_asociados' => $datosAsociados,
                        'requiere_revision' => true,
                    ]
                );
            }

        } catch (\Exception $e) {
            Log::debug('Error verificando datos médicos asociados', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sanitizar datos sensibles del usuario
     */
    private function sanitizarDatosUsuario(array $datos): array
    {
        $camposSensibles = [
            'password' => '[CONTRASEÑA OCULTA]',
            'remember_token' => '[TOKEN OCULTO]',
            'api_token' => '[API TOKEN OCULTO]',
            'two_factor_secret' => '[2FA SECRET OCULTO]',
            'two_factor_recovery_codes' => '[CÓDIGOS RECUPERACIÓN OCULTOS]',
        ];

        foreach ($camposSensibles as $campo => $valorOculto) {
            if (isset($datos[$campo])) {
                $datos[$campo] = $valorOculto;
            }
        }

        return $datos;
    }
}
