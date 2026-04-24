<?php
// app/Http/Middleware/ApiMetricsMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApiMetricsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $response = $next($request);
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);

        if ($request->is('api/*')) {
            try {
                $userId = null;
                if (Auth::check()) {
                    $user = Auth::user();
                    $userId = $user ? $user->id : null;
                }

                DB::table('api_requests')->insert([
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'status_code' => $response->getStatusCode(),
                    'response_time_ms' => $responseTime,
                    'ip' => $request->ip(),
                    'user_agent' => substr($request->userAgent() ?? '', 0, 255),
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Exception $e) {
                // Não faz nada para não quebrar a aplicação
            }
        }

        return $response;
    }
}
