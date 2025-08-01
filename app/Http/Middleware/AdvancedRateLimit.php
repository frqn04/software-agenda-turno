<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AdvancedRateLimit
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $key = 'api'): Response
    {
        $ip = $request->ip();
        $user = $request->user();
        
        // Rate limits específicos por tipo de usuario
        $limits = $this->getRateLimits($user);
        
        // Key única para el rate limit
        $rateLimitKey = $this->getRateLimitKey($request, $user, $key);
        
        // Verificar rate limit
        if (RateLimiter::tooManyAttempts($rateLimitKey, $limits['max_attempts'])) {
            $this->logRateLimitViolation($request, $user);
            
            return response()->json([
                'success' => false,
                'message' => 'Demasiadas solicitudes. Intente más tarde.',
                'retry_after' => RateLimiter::availableIn($rateLimitKey)
            ], 429);
        }

        // Incrementar contador
        RateLimiter::hit($rateLimitKey, $limits['decay_minutes'] * 60);
        
        $response = $next($request);
        
        // Agregar headers de rate limit
        $response->headers->add([
            'X-RateLimit-Limit' => $limits['max_attempts'],
            'X-RateLimit-Remaining' => RateLimiter::remaining($rateLimitKey, $limits['max_attempts']),
            'X-RateLimit-Reset' => now()->addMinutes($limits['decay_minutes'])->timestamp,
        ]);

        return $response;
    }

    private function getRateLimits($user): array
    {
        if (!$user) {
            // Usuarios no autenticados - más restrictivo
            return [
                'max_attempts' => 10,
                'decay_minutes' => 1,
            ];
        }

        // Rate limits por rol
        switch ($user->rol) {
            case 'admin':
                return [
                    'max_attempts' => 1000,
                    'decay_minutes' => 1,
                ];
            case 'doctor':
                return [
                    'max_attempts' => 200,
                    'decay_minutes' => 1,
                ];
            case 'secretaria':
                return [
                    'max_attempts' => 150,
                    'decay_minutes' => 1,
                ];
            default:
                return [
                    'max_attempts' => 60,
                    'decay_minutes' => 1,
                ];
        }
    }

    private function getRateLimitKey(Request $request, $user, string $key): string
    {
        $baseKey = $user ? "user:{$user->id}" : "ip:{$request->ip()}";
        return "{$key}:{$baseKey}";
    }

    private function logRateLimitViolation(Request $request, $user): void
    {
        $logData = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'timestamp' => now()->toDateTimeString(),
        ];

        if ($user) {
            $logData['user_id'] = $user->id;
            $logData['user_email'] = $user->email;
        }

        // Log de seguridad
        \Log::warning('Rate limit exceeded', $logData);

        // Almacenar en cache para detección de patrones
        $violationKey = "rate_limit_violations:{$request->ip()}";
        $violations = Cache::get($violationKey, 0);
        Cache::put($violationKey, $violations + 1, now()->addHour());

        // Si hay muchas violaciones, considerar bloqueo temporal
        if ($violations >= 10) {
            $this->temporaryBan($request->ip());
        }
    }

    private function temporaryBan(string $ip): void
    {
        $banKey = "temp_ban:{$ip}";
        Cache::put($banKey, true, now()->addHours(24));
        
        \Log::alert('Temporary IP ban applied', [
            'ip' => $ip,
            'duration' => '24 hours',
            'reason' => 'Excessive rate limit violations',
        ]);
    }
}
