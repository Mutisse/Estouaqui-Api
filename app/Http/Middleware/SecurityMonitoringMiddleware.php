<?php
// app/Http/Middleware/SecurityMonitoringMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Services\MonitoringService;
use Symfony\Component\HttpFoundation\Response;

class SecurityMonitoringMiddleware
{
    protected MonitoringService $monitoringService;

    public function __construct(MonitoringService $monitoringService)
    {
        $this->monitoringService = $monitoringService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $userId = null;
        $isAdmin = false;

        if (Auth::check()) {
            $user = Auth::user();
            if ($user) {
                $userId = $user->id;
                $isAdmin = $user->tipo === 'admin';
            }
        }

        // Login falhou
        if ($request->is('api/login') && $response->getStatusCode() === 401) {
            $this->monitoringService->logSecurityEvent(
                'login_failed',
                'warning',
                $request->ip(),
                null,
                "Tentativa de login falhou",
                ['email' => $request->input('email', 'unknown')]
            );
        }

        // Acesso admin não autorizado
        if ($request->is('api/admin/*') && !$isAdmin && $userId) {
            $this->monitoringService->logSecurityEvent(
                'unauthorized_access',
                'critical',
                $request->ip(),
                $userId,
                "Tentativa de acesso não autorizado: " . $request->path(),
                ['path' => $request->path()]
            );
        }

        // Rate limit
        $ipKey = str_replace(['.', ':'], '_', $request->ip());
        $cacheKey = "req_ip_{$ipKey}";
        $recentRequests = Cache::get($cacheKey, 0);
        Cache::put($cacheKey, $recentRequests + 1, now()->addMinutes(1));

        if ($recentRequests > 100) {
            $this->monitoringService->logSecurityEvent(
                'high_request_rate',
                'warning',
                $request->ip(),
                $userId,
                "Alta taxa de requisições: {$recentRequests}/min",
                ['count' => $recentRequests]
            );
        }

        return $response;
    }
}
