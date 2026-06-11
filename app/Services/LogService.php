<?php
// app/Services/LogService.php

namespace App\Services;

use App\Models\LogSistema;
use App\Models\LogTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class LogService
{
    public static function registrar(string $evento, array $data = []): ?LogSistema
    {
        try {
            $template = LogTemplate::where('evento', $evento)
                ->where('ativo', true)
                ->first();

            if (!$template) {
                Log::warning("Template de log não encontrado: {$evento}");
                return null;
            }

            $mensagem = $template->renderMensagem($data);

            // Obter usuário atual com verificação
            $user = null;
            if (!app()->runningInConsole()) {
                try {
                    $user = Auth::user();
                } catch (\Exception $e) {
                    $user = null;
                }
            }

            // Dados do usuário
            $userId = $user->id ?? null;
            $userNome = $user->nome ?? 'Sistema';
            $userEmail = $user->email ?? 'sistema@estouaqui.co.mz';

            if (isset($data['user_nome'])) {
                $userNome = $data['user_nome'];
            }

            return LogSistema::create([
                'user_id' => $userId,
                'user_nome' => $userNome,
                'user_email' => $userEmail,
                'acao' => $evento,
                'nivel' => $template->nivel,
                'descricao' => $mensagem,
                'ip' => request()->ip() ?? '0.0.0.0',
                'user_agent' => request()->userAgent() ?? 'Unknown',
                'modulo' => $template->modulo,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao registrar log: ' . $e->getMessage());
            return null;
        }
    }

    public static function error(string $evento, array $data = []): ?LogSistema
    {
        return self::registrar($evento, $data);
    }

    public static function warning(string $evento, array $data = []): ?LogSistema
    {
        return self::registrar($evento, $data);
    }
}
