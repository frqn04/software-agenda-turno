<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SecurityLogging
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($this->shouldLog($request, $response)) {
            $this->logSecurityEvent($request, $response);
        }

        return $response;
    }

    protected function shouldLog(Request $request, $response): bool
    {
        return $response->getStatusCode() >= 400 ||
               $request->isMethod('POST') ||
               $request->isMethod('PUT') ||
               $request->isMethod('DELETE') ||
               str_contains($request->path(), 'login') ||
               str_contains($request->path(), 'register');
    }

    protected function logSecurityEvent(Request $request, $response): void
    {
        $data = [
            'timestamp' => now()->toISOString(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'method' => $request->method(),
            'endpoint' => $request->path(),
            'status_code' => $response->getStatusCode(),
            'user_id' => auth()->id(),
            'user_name' => auth()->user()?->name,
            'user_role' => auth()->user()?->rol,
            'session_id' => $request->session()?->getId(),
            'referer' => $request->header('referer'),
            'request_size' => strlen($request->getContent()),
            'sistema' => 'clínica_dental_interna'
        ];

        if ($response->getStatusCode() >= 400) {
            $data['response_preview'] = substr($response->getContent(), 0, 300);
            $data['event_type'] = 'security_error';
        } else {
            $data['event_type'] = 'security_success';
        }

        // Log específico para intentos de login
        if ($request->isMethod('POST') && str_contains($request->path(), 'login')) {
            $data['login_attempt'] = [
                'email' => $request->input('email'),
                'success' => $response->getStatusCode() === 200
            ];
        }

        // Log según criticidad
        if ($response->getStatusCode() >= 500) {
            Log::error('Error crítico en sistema de clínica', $data);
        } elseif ($response->getStatusCode() >= 400) {
            Log::warning('Evento de seguridad en clínica', $data);
        } else {
            Log::info('Actividad de seguridad en clínica', $data);
        }
    }
}
