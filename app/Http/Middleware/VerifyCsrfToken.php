<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Support\Facades\Log;
use App\Models\LogAuditoria;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para verificación CSRF
 * Protege formularios web mientras excluye APIs REST
 */
class VerifyCsrfToken extends Middleware
{
    /**
     * URIs que deben ser excluidas de la verificación CSRF
     * Incluye todas las rutas API y webhooks específicos del sistema médico
     *
     * @var array<int, string>
     */
    protected $except = [
        // Todas las rutas API
        'api/*',
        
        // Webhooks y callbacks externos
        'webhooks/*',
        'callbacks/*',
        
        // Endpoints específicos del sistema médico
        'external/appointment-confirmation/*',  // Confirmaciones externas
        'external/payment-callback/*',          // Callbacks de pagos
        'external/insurance-verification/*',    // Verificación de seguros
        
        // Endpoints de integración con sistemas hospitalarios
        'integration/his/*',                    // Hospital Information System
        'integration/emr/*',                    // Electronic Medical Records
        'integration/laboratory/*',             // Resultados de laboratorio
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Log de verificación CSRF para auditoría (solo para rutas web críticas)
            if ($this->shouldLogCsrfAttempt($request)) {
                $this->logCsrfAttempt($request);
            }
            
            return parent::handle($request, $next);
            
        } catch (\Illuminate\Session\TokenMismatchException $e) {
            // Log crítico de posible ataque CSRF
            $this->logCsrfFailure($request, $e);
            
            return $this->handleCsrfFailure($request);
        }
    }

    /**
     * Determina si debe registrar el intento de verificación CSRF
     */
    private function shouldLogCsrfAttempt(Request $request): bool
    {
        // Solo log en rutas sensibles del sistema médico
        $sensitiveRoutes = [
            'pacientes/store',
            'pacientes/update',
            'turnos/store',
            'turnos/update',
            'doctors/store',
            'doctors/update',
            'admin/*',
        ];

        foreach ($sensitiveRoutes as $route) {
            if ($request->is($route)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Registra intento de verificación CSRF
     */
    private function logCsrfAttempt(Request $request): void
    {
        LogAuditoria::create([
            'usuario_id' => auth()->id(),
            'accion' => 'csrf_verification_attempt',
            'tabla' => 'security',
            'registro_id' => null,
            'valores_anteriores' => null,
            'valores_nuevos' => json_encode([
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'has_token' => $request->hasHeader('X-CSRF-TOKEN') || $request->has('_token'),
                'ip' => $request->ip(),
            ]),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Registra falla de verificación CSRF
     */
    private function logCsrfFailure(Request $request, \Exception $exception): void
    {
        // Log crítico de seguridad
        Log::critical('CSRF token mismatch - Potential CSRF attack', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('referer'),
            'has_session' => $request->hasSession(),
            'exception' => $exception->getMessage(),
            'timestamp' => now()->toISOString(),
        ]);

        // Auditoría de seguridad
        LogAuditoria::create([
            'usuario_id' => auth()->id(),
            'accion' => 'csrf_attack_detected',
            'tabla' => 'security',
            'registro_id' => null,
            'valores_anteriores' => null,
            'valores_nuevos' => json_encode([
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'potential_attack' => true,
                'security_threat_level' => 'high',
            ]),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Maneja respuesta personalizada para falla CSRF
     */
    private function handleCsrfFailure(Request $request): Response
    {
        // Si es una solicitud AJAX/API
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Token CSRF inválido',
                'message' => 'Su sesión ha expirado o el token de seguridad es inválido. Por favor, recargue la página e intente nuevamente.',
                'code' => 'CSRF_TOKEN_MISMATCH',
                'action_required' => 'reload_page',
                'timestamp' => now()->toISOString(),
            ], 419); // 419 es el código estándar para CSRF token mismatch
        }

        // Para solicitudes web
        return redirect()
            ->back()
            ->withInput($request->except(['_token', 'password', 'password_confirmation']))
            ->withErrors([
                'csrf' => 'Su sesión ha expirado por seguridad. Por favor, intente nuevamente.'
            ]);
    }

    /**
     * Determine if the session and input CSRF tokens match.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function tokensMatch($request): bool
    {
        $token = $this->getTokenFromRequest($request);

        return is_string($request->session()->token()) &&
               is_string($token) &&
               hash_equals($request->session()->token(), $token);
    }
}
