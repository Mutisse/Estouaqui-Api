<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Não autenticado'
            ], 401);
        }

        $user = Auth::user();
        $userType = $user->tipo;

        if ($userType !== 'admin' && $userType !== 'root') {
            return response()->json([
                'success' => false,
                'message' => 'Acesso negado. Área restrita para administradores.'
            ], 403);
        }

        return $next($request);
    }
}
