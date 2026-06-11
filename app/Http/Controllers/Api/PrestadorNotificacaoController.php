<?php
// app/Http/Controllers/Api/PrestadorNotificacaoController.php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Notificacao;

class PrestadorNotificacaoController extends BaseController
{
    /**
     * Listar notificações do prestador
     * GET /api/prestador/notificacoes
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $perPage = $request->get('per_page', 20);
        $notificacoes = Notificacao::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $notificacoes->items(),
            'unread_count' => Notificacao::where('user_id', $user->id)
                ->where('lida', false)
                ->count(),
            'pagination' => [
                'current_page' => $notificacoes->currentPage(),
                'last_page' => $notificacoes->lastPage(),
                'per_page' => $notificacoes->perPage(),
                'total' => $notificacoes->total(),
            ]
        ]);
    }

    /**
     * Notificações não lidas
     * GET /api/prestador/notificacoes/nao-lidas
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
            'count' => $notificacoes->count()
        ]);
    }

    /**
     * Marcar notificação como lida
     * PUT /api/prestador/notificacoes/{id}/ler
     */
    public function marcarComoLida($id, Request $request)
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

        $notificacao->lida = true;
        $notificacao->save();

        return response()->json([
            'success' => true,
            'message' => 'Notificação marcada como lida'
        ]);
    }

    /**
     * Marcar todas notificações como lidas
     * PUT /api/prestador/notificacoes/marcar-todas-lidas
     */
    public function marcarTodasComoLidas(Request $request)
    {
        $user = $request->user();

        Notificacao::where('user_id', $user->id)
            ->where('lida', false)
            ->update(['lida' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Todas notificações marcadas como lidas'
        ]);
    }
}
