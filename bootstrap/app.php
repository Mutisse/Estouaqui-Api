<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\ApiMetricsMiddleware;
use App\Http\Middleware\SlowQueryMiddleware;
use App\Http\Middleware\SecurityMonitoringMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Registrar aliases para os middlewares
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);

        // ✅ Adicionar middlewares de monitoramento a TODAS as rotas API
        $middleware->api(prepend: [
            ApiMetricsMiddleware::class,        // Métricas de requisição
            SlowQueryMiddleware::class,         // Monitoramento de queries lentas
            SecurityMonitoringMiddleware::class, // Segurança
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
