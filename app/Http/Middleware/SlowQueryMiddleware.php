<?php
// app/Http/Middleware/SlowQueryMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\MonitoringService;
use Symfony\Component\HttpFoundation\Response;

class SlowQueryMiddleware
{
    protected MonitoringService $monitoringService;

    public function __construct(MonitoringService $monitoringService)
    {
        $this->monitoringService = $monitoringService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        DB::enableQueryLog();
        $response = $next($request);

        $queries = DB::getQueryLog();
        $slowThreshold = 500;

        $userId = null;
        if (Auth::check()) {
            $user = Auth::user();
            $userId = $user ? $user->id : null;
        }

        foreach ($queries as $query) {
            $time = $query['time'] ?? 0;
            if ($time > $slowThreshold) {
                $this->monitoringService->logSlowQuery(
                    $query['query'] ?? '',
                    $query['bindings'] ?? [],
                    $time,
                    config('database.default'),
                    $request->path(),
                    $request->ip(),
                    $userId
                );
            }
        }

        return $response;
    }
}
