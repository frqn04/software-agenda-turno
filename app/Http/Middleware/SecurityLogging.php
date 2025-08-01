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
            'path' => $request->path(),
            'status_code' => $response->getStatusCode(),
            'user_id' => auth()->id(),
            'session_id' => $request->session()?->getId(),
            'referer' => $request->header('referer'),
            'request_size' => strlen($request->getContent()),
        ];

        if ($response->getStatusCode() >= 400) {
            $data['response_content'] = substr($response->getContent(), 0, 500);
        }

        if ($request->isMethod('POST') && str_contains($request->path(), 'login')) {
            $data['login_attempt'] = [
                'email' => $request->input('email'),
                'success' => $response->getStatusCode() === 200
            ];
        }

        Log::channel('security')->info('Security Event', $data);
    }
}
