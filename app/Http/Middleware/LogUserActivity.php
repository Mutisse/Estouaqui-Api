<?php

namespace App\Http\Middleware;

use App\Models\LogSistema;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // ✅ ADICIONAR ESTA LINHA
use Illuminate\Support\Facades\Schema;

class LogUserActivity
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate($request, $response)
    {
        try {
            // Verificar se a tabela existe
            if (!Schema::hasTable('logs_sistema')) {
                return;
            }

            // Obter usuário autenticado
            $user = null;
            if (Auth::check()) {
                $user = Auth::user();
            }

            // Só logar se tiver usuário autenticado
            if (!$user || !$user->id) {
                return;
            }

            // Verificar se é rota admin
            if (!str_contains($request->path(), 'admin')) {
                return;
            }

            // Inserir log diretamente com DB para evitar problemas de modelo
            DB::table('logs_sistema')->insert([
                'user_id' => $user->id,
                'user_nome' => $user->nome ?? 'Unknown',
                'user_email' => $user->email ?? 'Unknown',
                'acao' => 'acesso',
                'nivel' => 'info',
                'descricao' => "Acessou: " . $request->method() . " " . $request->path(),
                'ip' => $request->ip() ?? '0.0.0.0',
                'user_agent' => $request->userAgent() ?? 'Unknown',
                'modulo' => explode('/', $request->path())[2] ?? 'sistema',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        } catch (\Exception $e) {
            // Não deixar o log quebrar a aplicação
            Log::error('Erro no LogUserActivity: ' . $e->getMessage());
        }
    }
}
