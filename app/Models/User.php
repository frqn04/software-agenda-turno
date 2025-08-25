<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Carbon\Carbon;

/**
 * Modelo para gestionar usuarios del sistema médico
 * Maneja autenticación, roles y permisos específicos del sistema
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'telefono',
        'rol',
        'especialidad_medica',
        'numero_empleado',
        'fecha_ingreso',
        'ultimo_acceso',
        'intentos_login_fallidos',
        'bloqueado_hasta',
        'configuracion_usuario',
        'activo',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'fecha_ingreso' => 'date',
            'ultimo_acceso' => 'datetime',
            'intentos_login_fallidos' => 'integer',
            'bloqueado_hasta' => 'datetime',
            'configuracion_usuario' => 'array',
        ];
    }

    // Roles del sistema médico
    const ROL_SUPER_ADMIN = 'super_admin';
    const ROL_ADMIN = 'admin';
    const ROL_MEDICO = 'medico';
    const ROL_ENFERMERO = 'enfermero';
    const ROL_RECEPCIONISTA = 'recepcionista';
    const ROL_SECRETARIO_MEDICO = 'secretario_medico';
    const ROL_FACTURADOR = 'facturador';
    const ROL_AUDITORIA = 'auditoria';
    const ROL_SOLO_LECTURA = 'solo_lectura';

    public static function getRoles(): array
    {
        return [
            self::ROL_SUPER_ADMIN => 'Super Administrador',
            self::ROL_ADMIN => 'Administrador',
            self::ROL_MEDICO => 'Médico',
            self::ROL_ENFERMERO => 'Enfermero/a',
            self::ROL_RECEPCIONISTA => 'Recepcionista',
            self::ROL_SECRETARIO_MEDICO => 'Secretario Médico',
            self::ROL_FACTURADOR => 'Facturador',
            self::ROL_AUDITORIA => 'Auditoría',
            self::ROL_SOLO_LECTURA => 'Solo Lectura',
        ];
    }

    // Permisos por rol
    public static function getPermisosPorRol(): array
    {
        return [
            self::ROL_SUPER_ADMIN => ['*'], // Todos los permisos
            self::ROL_ADMIN => [
                'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar', 'usuarios.ver',
                'doctores.*', 'pacientes.*', 'turnos.*', 'especialidades.*',
                'reportes.*', 'configuracion.*', 'auditoria.ver'
            ],
            self::ROL_MEDICO => [
                'pacientes.ver', 'pacientes.editar', 'pacientes.crear',
                'turnos.ver', 'turnos.editar', 'turnos.crear',
                'historias_clinicas.*', 'evoluciones.*',
                'reportes.propios'
            ],
            self::ROL_ENFERMERO => [
                'pacientes.ver', 'pacientes.editar',
                'turnos.ver', 'turnos.editar',
                'historias_clinicas.ver', 'evoluciones.ver',
                'signos_vitales.*'
            ],
            self::ROL_RECEPCIONISTA => [
                'pacientes.ver', 'pacientes.crear', 'pacientes.editar',
                'turnos.*', 'doctores.ver', 'especialidades.ver',
                'agenda.*'
            ],
            self::ROL_SECRETARIO_MEDICO => [
                'pacientes.ver', 'turnos.*', 'doctores.ver',
                'historias_clinicas.ver', 'reportes.basicos'
            ],
            self::ROL_FACTURADOR => [
                'pacientes.ver', 'turnos.ver', 'facturacion.*',
                'reportes.financieros'
            ],
            self::ROL_AUDITORIA => [
                'auditoria.*', 'reportes.*', 'usuarios.ver',
                'logs.*'
            ],
            self::ROL_SOLO_LECTURA => [
                '*.ver'
            ],
        ];
    }

    // Relaciones
    public function logsAuditoria()
    {
        return $this->hasMany(LogAuditoria::class, 'usuario_id');
    }

    public function turnosCreados()
    {
        return $this->hasMany(Turno::class, 'created_by');
    }

    public function turnosModificados()
    {
        return $this->hasMany(Turno::class, 'updated_by');
    }

    public function doctorProfile()
    {
        return $this->hasOne(Doctor::class, 'email', 'email');
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorRol($query, $rol)
    {
        return $query->where('rol', $rol);
    }

    public function scopeMedicos($query)
    {
        return $query->where('rol', self::ROL_MEDICO);
    }

    public function scopeAdministrativos($query)
    {
        return $query->whereIn('rol', [
            self::ROL_ADMIN,
            self::ROL_RECEPCIONISTA,
            self::ROL_SECRETARIO_MEDICO,
            self::ROL_FACTURADOR
        ]);
    }

    public function scopeBloqueados($query)
    {
        return $query->where('bloqueado_hasta', '>', now());
    }

    public function scopeConAccesoReciente($query, $dias = 30)
    {
        return $query->where('ultimo_acceso', '>=', now()->subDays($dias));
    }

    public function scopeSinAccesoReciente($query, $dias = 30)
    {
        return $query->where('ultimo_acceso', '<', now()->subDays($dias))
            ->orWhereNull('ultimo_acceso');
    }

    // Accessors
    public function getRolTextoAttribute()
    {
        $roles = $this->getRoles();
        return $roles[$this->rol] ?? 'No especificado';
    }

    public function getDiasDesdeUltimoAccesoAttribute()
    {
        if (!$this->ultimo_acceso) {
            return null;
        }
        return $this->ultimo_acceso->diffInDays(now());
    }

    public function getEstaBloqueadoAttribute()
    {
        return $this->bloqueado_hasta && $this->bloqueado_hasta > now();
    }

    public function getTiempoBloqueoRestanteAttribute()
    {
        if (!$this->esta_bloqueado) {
            return null;
        }
        return $this->bloqueado_hasta->diffForHumans();
    }

    // Métodos de permisos
    public function tienePermiso($permiso): bool
    {
        if (!$this->activo || $this->esta_bloqueado) {
            return false;
        }

        $permisos = $this->getPermisosPorRol()[$this->rol] ?? [];
        
        // Super admin tiene todos los permisos
        if (in_array('*', $permisos)) {
            return true;
        }
        
        // Verificar permiso específico
        if (in_array($permiso, $permisos)) {
            return true;
        }
        
        // Verificar permisos con wildcards
        foreach ($permisos as $permisoRol) {
            if (str_ends_with($permisoRol, '.*')) {
                $prefijo = str_replace('.*', '', $permisoRol);
                if (str_starts_with($permiso, $prefijo)) {
                    return true;
                }
            }
            
            if (str_ends_with($permisoRol, '.ver') && str_contains($permiso, '.ver')) {
                return true;
            }
        }
        
        return false;
    }

    public function esAdmin(): bool
    {
        return in_array($this->rol, [self::ROL_SUPER_ADMIN, self::ROL_ADMIN]);
    }

    public function esMedico(): bool
    {
        return $this->rol === self::ROL_MEDICO;
    }

    public function esPersonalMedico(): bool
    {
        return in_array($this->rol, [self::ROL_MEDICO, self::ROL_ENFERMERO]);
    }

    public function esPersonalAdministrativo(): bool
    {
        return in_array($this->rol, [
            self::ROL_RECEPCIONISTA,
            self::ROL_SECRETARIO_MEDICO,
            self::ROL_FACTURADOR
        ]);
    }

    public function puedeGestionarUsuarios(): bool
    {
        return $this->tienePermiso('usuarios.crear');
    }

    public function puedeVerAuditoria(): bool
    {
        return $this->tienePermiso('auditoria.ver');
    }

    public function puedeGestionarTurnos(): bool
    {
        return $this->tienePermiso('turnos.crear');
    }

    // Métodos de seguridad
    public function registrarAcceso(): void
    {
        $this->update([
            'ultimo_acceso' => now(),
            'intentos_login_fallidos' => 0,
            'bloqueado_hasta' => null,
        ]);
    }

    public function registrarIntentoFallido(): void
    {
        $intentos = $this->intentos_login_fallidos + 1;
        
        $updateData = ['intentos_login_fallidos' => $intentos];
        
        // Bloquear después de 5 intentos fallidos
        if ($intentos >= 5) {
            $updateData['bloqueado_hasta'] = now()->addMinutes(30);
        }
        
        $this->update($updateData);
    }

    public function desbloquear(): bool
    {
        return $this->update([
            'intentos_login_fallidos' => 0,
            'bloqueado_hasta' => null,
        ]);
    }

    public function bloquear($minutos = 30, $motivo = null): bool
    {
        $result = $this->update([
            'bloqueado_hasta' => now()->addMinutes($minutos),
        ]);
        
        if ($motivo) {
            LogAuditoria::create([
                'usuario_id' => auth()->id(),
                'tabla' => 'users',
                'registro_id' => $this->id,
                'accion' => 'usuario_bloqueado',
                'valores_nuevos' => json_encode(['motivo' => $motivo]),
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        }
        
        return $result;
    }

    // Métodos de configuración
    public function getConfiguracion($clave, $default = null)
    {
        return $this->configuracion_usuario[$clave] ?? $default;
    }

    public function setConfiguracion($clave, $valor): bool
    {
        $config = $this->configuracion_usuario ?? [];
        $config[$clave] = $valor;
        
        return $this->update(['configuracion_usuario' => $config]);
    }

    public function validarDatos(): array
    {
        $errores = [];
        
        // Validar email único
        $emailExistente = static::where('email', $this->email)
            ->where('id', '!=', $this->id)
            ->exists();
        
        if ($emailExistente) {
            $errores[] = 'Ya existe un usuario con este email';
        }
        
        // Validar rol válido
        if (!in_array($this->rol, array_keys($this->getRoles()))) {
            $errores[] = 'Rol inválido';
        }
        
        // Validar número de empleado único si se proporciona
        if ($this->numero_empleado) {
            $numeroExistente = static::where('numero_empleado', $this->numero_empleado)
                ->where('id', '!=', $this->id)
                ->exists();
            
            if ($numeroExistente) {
                $errores[] = 'Ya existe un usuario con este número de empleado';
            }
        }
        
        return $errores;
    }

    /**
     * Obtener estadísticas de usuarios
     */
    public static function getEstadisticas(): array
    {
        return [
            'total' => static::count(),
            'activos' => static::activos()->count(),
            'bloqueados' => static::bloqueados()->count(),
            'por_rol' => static::selectRaw('rol, COUNT(*) as cantidad')
                ->groupBy('rol')
                ->pluck('cantidad', 'rol')
                ->toArray(),
            'con_acceso_reciente' => static::conAccesoReciente()->count(),
            'sin_acceso_reciente' => static::sinAccesoReciente()->count(),
        ];
    }

    /**
     * Boot method para eventos del modelo
     */
    protected static function boot()
    {
        parent::boot();

        // Establecer valores por defecto
        static::creating(function ($user) {
            if (!$user->rol) {
                $user->rol = self::ROL_SOLO_LECTURA;
            }
            
            if (!$user->fecha_ingreso) {
                $user->fecha_ingreso = now();
            }
            
            // Generar número de empleado si no se proporciona
            if (!$user->numero_empleado) {
                $ultimoNumero = static::max('numero_empleado') ?: 1000;
                $user->numero_empleado = $ultimoNumero + 1;
            }
        });

        // Normalizar datos antes de guardar
        static::saving(function ($user) {
            // Normalizar email
            $user->email = strtolower(trim($user->email));
            
            // Normalizar nombre
            $user->name = ucwords(strtolower(trim($user->name)));
        });
    }
}
