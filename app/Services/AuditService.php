<?php

namespace App\Services;

use App\Models\LogAuditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Log de actividad con contexto completo
     */
    public static function logActivity(
        string $action,
        string $table,
        ?int $recordId = null,
        ?int $userId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        array $context = []
    ): void {
        $userId = $userId ?? Auth::id();
        $ipAddress = $ipAddress ?? request()->ip();
        $userAgent = $userAgent ?? request()->userAgent();

        LogAuditoria::create([
            'accion' => $action,
            'tabla' => $table,
            'registro_id' => $recordId,
            'usuario_id' => $userId,
            'valores_anteriores' => $oldValues ? json_encode($oldValues) : null,
            'valores_nuevos' => $newValues ? json_encode($newValues) : null,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'contexto' => !empty($context) ? json_encode($context) : null,
        ]);
    }

    /**
     * Log de acceso al sistema
     */
    public static function logAccess(string $action, ?int $userId = null, array $context = []): void
    {
        self::logActivity(
            $action,
            'system_access',
            null,
            $userId,
            null,
            null,
            request()->ip(),
            request()->userAgent(),
            array_merge($context, [
                'timestamp' => now()->toDateTimeString(),
                'route' => request()->route()?->getName(),
                'method' => request()->method(),
            ])
        );
    }

    /**
     * Log de operaciones críticas
     */
    public static function logCriticalOperation(
        string $operation,
        array $data = [],
        ?int $userId = null
    ): void {
        self::logActivity(
            'critical_operation',
            'system_operations',
            null,
            $userId,
            null,
            ['operation' => $operation, 'data' => $data],
            request()->ip(),
            request()->userAgent(),
            [
                'severity' => 'critical',
                'requires_review' => true,
                'timestamp' => now()->toDateTimeString(),
            ]
        );
    }

    /**
     * Log de errores de seguridad
     */
    public static function logSecurityEvent(
        string $event,
        string $severity = 'medium',
        array $details = []
    ): void {
        self::logActivity(
            'security_event',
            'security_logs',
            null,
            Auth::id(),
            null,
            [
                'event' => $event,
                'severity' => $severity,
                'details' => $details,
            ],
            request()->ip(),
            request()->userAgent(),
            [
                'requires_investigation' => in_array($severity, ['high', 'critical']),
                'timestamp' => now()->toDateTimeString(),
            ]
        );
    }
}
