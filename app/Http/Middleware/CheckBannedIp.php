<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckBannedIp
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        
        // Verificar si la IP está en la lista de IPs bloqueadas permanentemente
        $permanentBans = config('security.banned_ips', []);
        if (in_array($ip, $permanentBans)) {
            Log::warning('IP bloqueada intentó acceder al sistema de clínica', [
                'ip' => $ip,
                'user_agent' => $request->userAgent(),
                'endpoint' => $request->path(),
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Acceso restringido al sistema de la clínica',
                'error_code' => 'IP_BANNED'
            ], 403);
        }

        // Verificar ban temporal
        $banKey = "temp_ban:{$ip}";
        if (Cache::has($banKey)) {
            $remainingTime = Cache::get($banKey . '_expires', now()->addHours(24));
            
            return response()->json([
                'success' => false,
                'message' => 'IP temporalmente bloqueada por actividad sospechosa',
                'retry_after' => $remainingTime->diffInSeconds(now())
            ], 429);
        }

        // Verificar patrones sospechosos
        $this->checkSuspiciousActivity($request);

        return $next($request);
    }

    /**
     * Check for suspicious activity patterns
     */
    private function checkSuspiciousActivity(Request $request): void
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent();

        // Detectar bots maliciosos por User-Agent
        $suspiciousAgents = [
            'sqlmap',
            'nikto',
            'nessus',
            'nmap',
            'masscan',
            'python-requests',
            'curl/7',
            'wget',
        ];

        foreach ($suspiciousAgents as $agent) {
            if (str_contains(strtolower($userAgent), $agent)) {
                $this->applyTemporaryBan($ip, 'Suspicious user agent: ' . $agent);
                break;
            }
        }

        // Detectar patrones de URL sospechosos
        $suspiciousPatterns = [
            '/\.php',
            '/admin',
            '/wp-',
            '/phpmyadmin',
            '/.env',
            '/config',
            '/backup',
            '/sql',
            'eval(',
            'base64_decode',
            'script>',
        ];

        $path = $request->path();
        foreach ($suspiciousPatterns as $pattern) {
            if (str_contains(strtolower($path), $pattern)) {
                $this->applyTemporaryBan($ip, 'Suspicious URL pattern: ' . $pattern);
                break;
            }
        }
    }

    /**
     * Apply temporary ban to IP
     */
    private function applyTemporaryBan(string $ip, string $reason): void
    {
        $banKey = "temp_ban:{$ip}";
        $expiresAt = now()->addHours(24);
        
        Cache::put($banKey, true, $expiresAt);
        Cache::put($banKey . '_expires', $expiresAt, $expiresAt);
        
        Log::alert('Automatic IP ban applied', [
            'ip' => $ip,
            'reason' => $reason,
            'expires_at' => $expiresAt->toDateTimeString(),
        ]);
    }
}
