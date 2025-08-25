<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Handle preflight requests
        if ($request->getMethod() === 'OPTIONS') {
            return response('', 200)
                ->header('Access-Control-Allow-Origin', $this->getAllowedOrigin($request))
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '3600');
        }

        $response = $next($request);

        // Add CORS headers to actual requests
        $response->headers->set('Access-Control-Allow-Origin', $this->getAllowedOrigin($request));
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');

        return $response;
    }

    /**
     * Get allowed origin based on environment
     */
    private function getAllowedOrigin(Request $request): string
    {
        $allowedOrigins = [
            'http://localhost:3000',
            'http://localhost:5173',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:5173',
            config('app.url'),
        ];

        $origin = $request->header('Origin');

        if (config('app.env') === 'production') {
            // En producción, solo permitir orígenes específicos
            return in_array($origin, $allowedOrigins) ? $origin : config('app.url');
        }

        // En desarrollo, más permisivo pero aún seguro
        if ($origin && (
            str_starts_with($origin, 'http://localhost') ||
            str_starts_with($origin, 'http://127.0.0.1') ||
            in_array($origin, $allowedOrigins)
        )) {
            return $origin;
        }

        return config('app.url');
    }
}
