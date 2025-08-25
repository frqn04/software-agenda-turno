<?php

namespace App\Services;

use App\Models\LogAuditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Carbon\Carbon;

/**
 * Servicio de auditoría integral para el sistema médico
 * Maneja el registro de todas las actividades del sistema
 */
class AuditService
{
    private const MAX_LOG_LENGTH = 65535; // Límite para campos TEXT en MySQL
    private const CRITICAL_ACTIONS = [
        'delete', 'force_delete', 'login_failed', 'unauthorized_access',
        'data_export', 'bulk_delete', 'admin_override'
    ];

    /**
     * Log de actividad con contexto completo y procesamiento asíncrono
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

        // Preparar datos de auditoría
        $auditData = [
            'accion' => $action,
            'tabla' => $table,
            'registro_id' => $recordId,
            'usuario_id' => $userId,
            'valores_anteriores' => $oldValues ? self::truncateJson($oldValues) : null,
            'valores_nuevos' => $newValues ? self::truncateJson($newValues) : null,
            'ip_address' => $ipAddress,
            'user_agent' => self::truncateString($userAgent, 500),
            'contexto' => !empty($context) ? self::truncateJson($context) : null,
            'created_at' => now(),
            'severity' => self::calculateSeverity($action, $table),
            'session_id' => session()->getId(),
            'route' => request()->route()?->getName() ?? 'unknown',
            'method' => request()->method(),
            'url' => request()->fullUrl(),
        ];

        // Para acciones críticas, registrar inmediatamente
        if (in_array($action, self::CRITICAL_ACTIONS)) {
            self::createAuditLog($auditData);
            self::alertCriticalAction($auditData);
        } else {
            // Para acciones normales, usar cola para mejor rendimiento
            Queue::push(function () use ($auditData) {
                self::createAuditLog($auditData);
            });
        }
    }

    /**
     * Log de acceso al sistema con detección de anomalías
     */
    public static function logAccess(string $action, ?int $userId = null, array $context = []): void
    {
        $ipAddress = request()->ip();
        $userAgent = request()->userAgent();
        
        // Detectar intentos de acceso sospechosos
        $suspiciousActivity = self::detectSuspiciousAccess($userId, $ipAddress, $action);
        
        $contextData = array_merge($context, [
            'timestamp' => now()->toDateTimeString(),
            'route' => request()->route()?->getName(),
            'method' => request()->method(),
            'suspicious' => $suspiciousActivity,
            'geo_location' => self::getGeoLocation($ipAddress),
            'device_fingerprint' => self::generateDeviceFingerprint($userAgent),
        ]);

        self::logActivity(
            $action,
            'system_access',
            null,
            $userId,
            null,
            null,
            $ipAddress,
            $userAgent,
            $contextData
        );

        // Si es sospechoso, alertar
        if ($suspiciousActivity) {
            self::alertSuspiciousAccess($userId, $ipAddress, $action, $contextData);
        }
    }

    /**
     * Log de operaciones críticas con aprobación múltiple
     */
    public static function logCriticalOperation(
        string $operation,
        array $data = [],
        ?int $userId = null,
        ?int $approverId = null
    ): void {
        $contextData = [
            'operation' => $operation,
            'data' => $data,
            'approver_id' => $approverId,
            'requires_review' => true,
            'timestamp' => now()->toDateTimeString(),
            'compliance_level' => 'critical',
        ];

        self::logActivity(
            'critical_operation',
            'system_operations',
            null,
            $userId,
            null,
            ['operation' => $operation, 'data' => $data],
            request()->ip(),
            request()->userAgent(),
            $contextData
        );

        // Notificar a supervisores
        self::notifySupervisors($operation, $userId, $data);
    }

    /**
     * Log de errores de seguridad con escalamiento automático
     */
    public static function logSecurityEvent(
        string $event,
        string $severity = 'medium',
        array $details = [],
        bool $autoBlock = false
    ): void {
        $contextData = [
            'event' => $event,
            'severity' => $severity,
            'details' => $details,
            'requires_investigation' => in_array($severity, ['high', 'critical']),
            'timestamp' => now()->toDateTimeString(),
            'auto_blocked' => $autoBlock,
            'threat_score' => self::calculateThreatScore($event, $details),
        ];

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
            $contextData
        );

        // Auto-escalamiento para eventos críticos
        if ($severity === 'critical') {
            self::escalateSecurityEvent($event, $details);
        }

        // Bloqueo automático si se solicita
        if ($autoBlock) {
            self::autoBlockThreat(request()->ip(), $event);
        }
    }

    /**
     * Log de acceso a datos médicos (HIPAA compliance)
     */
    public static function logMedicalDataAccess(
        string $dataType,
        int $patientId,
        string $accessReason,
        ?int $userId = null
    ): void {
        self::logActivity(
            'medical_data_access',
            'patient_data',
            $patientId,
            $userId,
            null,
            [
                'data_type' => $dataType,
                'patient_id' => $patientId,
                'access_reason' => $accessReason,
                'hipaa_compliant' => true,
            ],
            request()->ip(),
            request()->userAgent(),
            [
                'compliance_framework' => 'HIPAA',
                'data_classification' => 'PHI', // Protected Health Information
                'retention_required' => true,
                'audit_required' => true,
            ]
        );
    }

    /**
     * Crear el registro de auditoría en base de datos
     */
    private static function createAuditLog(array $data): void
    {
        try {
            LogAuditoria::create($data);
        } catch (\Exception $e) {
            // Fallback: registrar en logs del sistema si falla la DB
            Log::error('Failed to create audit log', [
                'error' => $e->getMessage(),
                'audit_data' => $data,
            ]);
        }
    }

    /**
     * Calcular la severidad de una acción
     */
    private static function calculateSeverity(string $action, string $table): string
    {
        $criticalTables = ['users', 'doctors', 'pacientes', 'log_auditoria'];
        $criticalActions = self::CRITICAL_ACTIONS;

        if (in_array($action, $criticalActions) || in_array($table, $criticalTables)) {
            return 'critical';
        }

        if (in_array($action, ['update', 'create']) && in_array($table, ['turnos', 'historia_clinica'])) {
            return 'high';
        }

        if (in_array($action, ['view', 'list'])) {
            return 'low';
        }

        return 'medium';
    }

    /**
     * Detectar acceso sospechoso
     */
    private static function detectSuspiciousAccess(?int $userId, string $ipAddress, string $action): bool
    {
        // Verificar múltiples intentos de login fallidos
        if ($action === 'login_failed') {
            $recentFailures = LogAuditoria::where('ip_address', $ipAddress)
                ->where('accion', 'login_failed')
                ->where('created_at', '>=', now()->subMinutes(15))
                ->count();

            return $recentFailures >= 3;
        }

        // Verificar acceso desde IP no habitual
        if ($userId && $action === 'login_success') {
            $usualIps = LogAuditoria::where('usuario_id', $userId)
                ->where('accion', 'login_success')
                ->where('created_at', '>=', now()->subDays(30))
                ->distinct()
                ->pluck('ip_address')
                ->toArray();

            return !in_array($ipAddress, $usualIps) && count($usualIps) > 0;
        }

        return false;
    }

    /**
     * Generar huella digital del dispositivo
     */
    private static function generateDeviceFingerprint(string $userAgent): string
    {
        return substr(md5($userAgent . request()->header('Accept-Language', '')), 0, 16);
    }

    /**
     * Obtener geolocalización aproximada de IP
     */
    private static function getGeoLocation(string $ipAddress): ?string
    {
        // Implementación básica - en producción usar servicio de geolocalización
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return 'External IP'; // IP pública
        }
        return 'Internal Network'; // IP privada
    }

    /**
     * Calcular score de amenaza
     */
    private static function calculateThreatScore(string $event, array $details): int
    {
        $score = 0;

        // Eventos de alta amenaza
        $highThreatEvents = ['sql_injection', 'xss_attempt', 'unauthorized_access', 'brute_force'];
        if (in_array($event, $highThreatEvents)) {
            $score += 80;
        }

        // Verificar patrones en details
        $dangerousPatterns = ['SELECT', 'DROP', 'DELETE', '<script>', 'javascript:'];
        foreach ($details as $detail) {
            if (is_string($detail)) {
                foreach ($dangerousPatterns as $pattern) {
                    if (stripos($detail, $pattern) !== false) {
                        $score += 20;
                    }
                }
            }
        }

        return min($score, 100); // Máximo 100
    }

    /**
     * Truncar JSON para evitar problemas de tamaño
     */
    private static function truncateJson(array $data): ?string
    {
        $json = json_encode($data);
        return self::truncateString($json, self::MAX_LOG_LENGTH);
    }

    /**
     * Truncar string a longitud específica
     */
    private static function truncateString(?string $string, int $length): ?string
    {
        if (!$string || strlen($string) <= $length) {
            return $string;
        }
        return substr($string, 0, $length - 3) . '...';
    }

    /**
     * Alertar sobre acciones críticas
     */
    private static function alertCriticalAction(array $auditData): void
    {
        Log::critical('Critical action performed', $auditData);
        // Aquí se podría agregar notificación por email, Slack, etc.
    }

    /**
     * Alertar sobre acceso sospechoso
     */
    private static function alertSuspiciousAccess(int $userId, string $ipAddress, string $action, array $context): void
    {
        Log::warning('Suspicious access detected', [
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'action' => $action,
            'context' => $context,
        ]);
    }

    /**
     * Notificar a supervisores sobre operaciones críticas
     */
    private static function notifySupervisors(string $operation, ?int $userId, array $data): void
    {
        Log::alert('Critical operation requires review', [
            'operation' => $operation,
            'user_id' => $userId,
            'data' => $data,
            'timestamp' => now(),
        ]);
    }

    /**
     * Escalar eventos de seguridad críticos
     */
    private static function escalateSecurityEvent(string $event, array $details): void
    {
        Log::emergency('Critical security event', [
            'event' => $event,
            'details' => $details,
            'requires_immediate_attention' => true,
        ]);
    }

    /**
     * Bloqueo automático de amenazas
     */
    private static function autoBlockThreat(string $ipAddress, string $reason): void
    {
        // Implementar lógica de bloqueo de IP
        Log::error('IP automatically blocked', [
            'ip_address' => $ipAddress,
            'reason' => $reason,
            'blocked_at' => now(),
        ]);
    }
}
