<?php
// app/Services/NotificationService.php

namespace App\Services;

use App\Models\Notificacao;
use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\Log;  // ← ADICIONAR ESTA LINHA

class NotificationService
{
    /**
     * Enviar notificação usando template
     */
    public static function send(string $evento, int $userId, array $data = []): ?Notificacao
    {
        // Buscar template
        $template = NotificationTemplate::where('evento', $evento)
            ->where('ativo', true)
            ->first();

        if (!$template) {
            Log::warning("Template de notificação não encontrado: {$evento}");
            return null;
        }

        // Renderizar título e mensagem
        $titulo = $template->renderTitulo($data);
        $mensagem = $template->renderMensagem($data);

        // Criar notificação
        return Notificacao::create([
            'user_id' => $userId,
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'tipo' => $template->tipo,
            'data' => $data,
            'lida' => false,
        ]);
    }

    /**
     * Enviar notificação para múltiplos usuários
     */
    public static function sendToMany(string $evento, array $userIds, array $data = []): array
    {
        $notificacoes = [];
        foreach ($userIds as $userId) {
            $notificacoes[] = self::send($evento, $userId, $data);
        }
        return $notificacoes;
    }
}
