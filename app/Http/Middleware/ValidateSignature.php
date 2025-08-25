<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Foundation\Http\Middleware\ValidateSignature as Middleware;
use Illuminate\Support\Facades\Log;
use App\Models\LogAuditoria;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para validar firmas de URLs firmadas
 * Especialmente importante para enlaces de citas médicas y notificaciones
 */
class ValidateSignature extends Middleware
{
    /**
     * Parámetros de query string que deben ser ignorados en la validación de firma
     * Incluye parámetros de tracking y específicos del sistema médico
     *
     * @var array<int, string>
     */
    protected $except = [
        // Parámetros de tracking estándar
        'fbclid',
        'utm_campaign',
        'utm_content',
        'utm_medium',
        'utm_source',
        'utm_term',
        'gclid',
        
        // Parámetros específicos del sistema médico
        'notification_read',  // Estado de lectura de notificaciones
        'viewed_at',         // Timestamp de visualización
        'client_timezone',   // Zona horaria del cliente
        'device_id',         // ID del dispositivo (para estadísticas)
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $relative
     * @return \Illuminate\Http\Response
     */
    public function handle(Request $request, Closure $next, $relative = null): Response
    {
        try {
            // Registrar intento de validación para auditoría
            $this->logValidationAttempt($request);
            
            // Ejecutar validación padre
            $response = parent::handle($request, $next, $relative);
            
            // Log exitoso
            Log::info('Signature validation successful', [
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return $response;
            
        } catch (\Illuminate\Routing\Exceptions\InvalidSignatureException $e) {
            // Log falla de validación para seguridad
            $this->logValidationFailure($request, $e);
            
            // Para sistema médico, personalizar respuesta
            return $this->handleInvalidSignature($request);
        }
    }

    /**
     * Registra intento de validación de firma
     */
    private function logValidationAttempt(Request $request): void
    {
        LogAuditoria::create([
            'usuario_id' => auth()->id(),
            'accion' => 'signature_validation_attempt',
            'tabla' => 'system',
            'registro_id' => null,
            'valores_anteriores' => null,
            'valores_nuevos' => json_encode([
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Registra falla de validación para seguridad
     */
    private function logValidationFailure(Request $request, \Exception $exception): void
    {
        // Log de seguridad crítico
        Log::critical('Invalid signature detected - Potential security threat', [
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('referer'),
            'exception' => $exception->getMessage(),
            'timestamp' => now()->toISOString(),
        ]);

        // Auditoría de seguridad
        LogAuditoria::create([
            'usuario_id' => auth()->id(),
            'accion' => 'signature_validation_failed',
            'tabla' => 'security',
            'registro_id' => null,
            'valores_anteriores' => null,
            'valores_nuevos' => json_encode([
                'url' => $request->fullUrl(),
                'error' => $exception->getMessage(),
                'potential_attack' => true,
            ]),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Maneja respuesta personalizada para firma inválida
     */
    private function handleInvalidSignature(Request $request): Response
    {
        // Si es una solicitud AJAX/API
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Enlace inválido o expirado',
                'message' => 'El enlace que está intentando acceder es inválido o ha expirado. Por favor, solicite un nuevo enlace.',
                'code' => 'INVALID_SIGNATURE',
                'timestamp' => now()->toISOString(),
            ], 403);
        }

        // Para solicitudes web, redirigir con mensaje
        return redirect('/')->with('error', 
            'El enlace que está intentando acceder es inválido o ha expirado. ' .
            'Si necesita acceder a información de citas médicas, por favor inicie sesión normalmente.'
        );
    }
}
