<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        Log::info('RoleMiddleware: iniciando');

        $user = $request->user();

        Log::info('RoleMiddleware: user = ' . ($user ? $user->id : 'null'));

        if (!$user) {
            Log::error('RoleMiddleware: Usuário não autenticado');
            return response()->json([
                'success' => false,
                'error' => 'Usuário não autenticado'
            ], 401);
        }

        Log::info('RoleMiddleware: user tipo = ' . $user->tipo);
        Log::info('RoleMiddleware: roles necessários = ' . implode(', ', $roles));

        if (empty($roles)) {
            Log::info('RoleMiddleware: sem roles, permitindo');
            return $next($request);
        }

        $hasPermission = false;

        foreach ($roles as $role) {
            $role = strtolower(trim($role));
            $method = 'is' . ucfirst($role);

            Log::info("RoleMiddleware: verificando método {$method}");

            if (method_exists($user, $method) && $user->$method()) {
                $hasPermission = true;
                Log::info("RoleMiddleware: usuário tem permissão para {$role}");
                break;
            }
        }

        if (!$hasPermission) {
            Log::error("RoleMiddleware: usuário NÃO tem permissão. Tipo: {$user->tipo}");
            return response()->json([
                'success' => false,
                'error' => 'Acesso negado. Perfil não autorizado.',
                'user_type' => $user->tipo,
                'required_roles' => $roles
            ], 403);
        }

        Log::info('RoleMiddleware: permitindo acesso');

        return $next($request);
    }
}
