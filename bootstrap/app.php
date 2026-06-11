<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Middleware para API
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // ✅ Registrar middleware de role
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckUserRole::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'log.activity' => \App\Http\Middleware\LogUserActivity::class, // CORRIGIDO: Mover para alias
        ]);

        // ✅ Configurar para API retornar JSON em vez de redirecionar
        $middleware->redirectGuestsTo(fn (Request $request) =>
            $request->expectsJson() ? response()->json(['message' => 'Não autenticado'], 401) : null
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
