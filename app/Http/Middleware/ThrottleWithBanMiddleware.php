<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ThrottleWithBanMiddleware
{
    public function handle(Request $request, Closure $next, string $maxAttempts = '60', string $decayMinutes = '1'): Response
    {
        $key = $this->resolveRequestSignature($request);
        $banKey = 'ban:' . $key;
        
        if (RateLimiter::tooManyAttempts($banKey, 1)) {
            $availableAt = RateLimiter::availableAt($banKey);
            return response()->json([
                'success' => false,
                'message' => 'IP temporalmente bloqueada por actividad sospechosa.',
                'retry_after' => $availableAt - time()
            ], 429);
        }

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            RateLimiter::hit($banKey, 3600);
            
            $this->logSuspiciousActivity($request, $key);
            
            return response()->json([
                'success' => false,
                'message' => 'Demasiados intentos. IP bloqueada temporalmente.',
                'retry_after' => RateLimiter::availableIn($key)
            ], 429);
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        $response = $next($request);

        return $this->addRateLimitHeaders($response, $maxAttempts, $key);
    }

    protected function resolveRequestSignature(Request $request): string
    {
        return sha1(
            $request->method() .
            '|' . $request->server('SERVER_NAME') .
            '|' . $request->path() .
            '|' . $request->ip()
        );
    }

    protected function addRateLimitHeaders(Response $response, string $maxAttempts, string $key): Response
    {
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => RateLimiter::remaining($key, $maxAttempts),
        ]);

        return $response;
    }

    protected function logSuspiciousActivity(Request $request, string $key): void
    {
        Log::warning('Rate limit exceeded - potential attack', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
            'method' => $request->method(),
            'timestamp' => now(),
            'key' => $key
        ]);
    }
}
