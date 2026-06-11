<?php
// app/Http/Middleware/CheckUserRole.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckUserRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Não autenticado'
            ], 401);
        }

        $userType = $request->user()->tipo;

        if (!in_array($userType, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso negado. Permissões insuficientes.',
                'required_roles' => $roles,
                'your_role' => $userType
            ], 403);
        }

        return $next($request);
    }
}
