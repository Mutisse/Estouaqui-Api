<?php
// app/Http/Controllers/Api/NotificacaoController.php

namespace App\Http\Controllers\Api;

use App\Models\Notificacao;
use Illuminate\Http\Request;

class NotificacaoController extends BaseController
{
    /**
     * Listar todas as notificações do usuário
     * GET /api/cliente/notificacoes
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $notificacoes = Notificacao::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notificacoes
        ]);
    }

    /**
     * Listar apenas notificações não lidas
     * GET /api/cliente/notificacoes/nao-lidas
     */
    public function naoLidas(Request $request)
    {
        $user = $request->user();

        $notificacoes = Notificacao::where('user_id', $user->id)
            ->where('lida', false)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notificacoes,
            'unread_count' => $notificacoes->count()
        ]);
    }

    /**
     * Marcar uma notificação como lida
     * PATCH /api/cliente/notificacoes/{id}/ler
     */
    public function marcarComoLida(Request $request, $id)
    {
        $user = $request->user();

        $notificacao = Notificacao::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$notificacao) {
            return response()->json([
                'success' => false,
                'message' => 'Notificação não encontrada'
            ], 404);
        }

        $notificacao->marcarComoLida();

        return response()->json([
            'success' => true,
            'message' => 'Notificação marcada como lida',
            'data' => $notificacao
        ]);
    }

    /**
     * Marcar todas as notificações como lidas
     * POST /api/cliente/notificacoes/marcar-todas-lidas
     */
    public function marcarTodasComoLidas(Request $request)
    {
        $user = $request->user();

        $updated = Notificacao::where('user_id', $user->id)
            ->where('lida', false)
            ->update([
                'lida' => true,
                'lida_em' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => "{$updated} notificação(ões) marcada(s) como lida(s)",
            'updated_count' => $updated
        ]);
    }

    /**
     * Deletar uma notificação
     * DELETE /api/cliente/notificacoes/{id}
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $notificacao = Notificacao::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$notificacao) {
            return response()->json([
                'success' => false,
                'message' => 'Notificação não encontrada'
            ], 404);
        }

        $notificacao->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notificação removida'
        ]);
    }

    /**
     * Criar uma notificação (método auxiliar para outros controllers)
     */
    public static function criar($userId, $titulo, $mensagem, $tipo = 'sistema', $data = null)
    {
        return Notificacao::criar($userId, $titulo, $mensagem, $tipo, $data);
    }
}
