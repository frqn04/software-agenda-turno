<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
        
        // Excluir rutas API del middleware CSRF
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
        
        // Middlewares de seguridad globales
        $middleware->web(append: [
            \App\Http\Middleware\SecureHeaders::class,
        ]);
        
        $middleware->api(append: [
            \App\Http\Middleware\SecureHeaders::class,
            \App\Http\Middleware\SecurityLogging::class,
        ]);
        
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'throttle.ban' => \App\Http\Middleware\ThrottleWithBanMiddleware::class,
            'secure.headers' => \App\Http\Middleware\SecureHeaders::class,
            'security.logging' => \App\Http\Middleware\SecurityLogging::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
