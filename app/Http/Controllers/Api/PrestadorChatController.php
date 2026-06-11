<?php
// app/Http/Controllers/Api/PrestadorChatController.php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Chat;
use App\Models\Mensagem;
use App\Models\User;
use App\Services\NotificationService;

class PrestadorChatController extends BaseController
{
    /**
     * Listar conversas do prestador
     * GET /api/prestador/chat/conversas
     */
    public function conversas(Request $request)
    {
        $user = $request->user();

        $chats = Chat::where('prestador_id', $user->id)
            ->with(['cliente' => function ($q) {
                $q->select('id', 'nome', 'foto');
            }])
            ->orderBy('updated_at', 'desc')
            ->get();

        $result = $chats->map(function ($chat) use ($user) {
            $naoLidas = $chat->mensagens()
                ->where('receiver_id', $user->id)
                ->where('lida', false)
                ->count();

            $ultimaMensagem = $chat->mensagens()->latest()->first();

            return [
                'id' => $chat->id,
                'cliente_id' => $chat->cliente_id,
                'cliente_nome' => $chat->cliente?->nome ?? 'Cliente',
                'cliente_foto' => $chat->cliente?->foto ? asset('storage/' . $chat->cliente->foto) : null,
                'ultima_mensagem' => $ultimaMensagem?->mensagem ?? '',
                'ultima_mensagem_data' => $ultimaMensagem?->created_at,
                'nao_lidas' => $naoLidas,
                // ✅ Dados do prestador logado
                'prestador_id' => $user->id,
                'prestador_nome' => $user->nome,
                'prestador_foto' => $user->foto ? asset('storage/' . $user->foto) : null,
                'prestador_profissao' => $user->prestadorProfile?->profissao ?? 'Prestador de Serviços',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    /**
     * Buscar mensagens de um chat
     * GET /api/prestador/chat/mensagens/{chatId}
     */
    public function mensagens($chatId, Request $request)
    {
        $user = $request->user();

        $chat = Chat::where('prestador_id', $user->id)
            ->where('id', $chatId)
            ->first();

        if (!$chat) {
            return response()->json([
                'success' => false,
                'message' => 'Chat não encontrado'
            ], 404);
        }

        $mensagens = Mensagem::where('chat_id', $chatId)
            ->orderBy('created_at', 'asc')
            ->get();

        $mensagens->each(function ($mensagem) use ($user) {
            $mensagem->is_user = ($mensagem->sender_id === $user->id);
        });

        return response()->json([
            'success' => true,
            'data' => $mensagens
        ]);
    }

    /**
     * Buscar dados do prestador logado
     * GET /api/prestador/chat/dados
     */
    public function dadosPrestador(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'nome' => $user->nome,
                'foto' => $user->foto ? asset('storage/' . $user->foto) : null,
                'profissao' => $user->prestadorProfile?->profissao ?? 'Prestador de Serviços',
                'media_avaliacao' => $user->prestadorProfile?->media_avaliacao ?? 0,
                'total_avaliacoes' => $user->prestadorProfile?->total_avaliacoes ?? 0,
            ]
        ]);
    }

    /**
     * Enviar mensagem
     * POST /api/prestador/chat/enviar
     */
    public function enviarMensagem(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'chat_id' => 'required|exists:chats,id',
            'mensagem' => 'required|string|max:1000',
        ]);

        $chat = Chat::where('prestador_id', $user->id)
            ->where('id', $request->chat_id)
            ->first();

        if (!$chat) {
            return response()->json([
                'success' => false,
                'message' => 'Chat não encontrado'
            ], 404);
        }

        $mensagem = Mensagem::create([
            'chat_id' => $chat->id,
            'sender_id' => $user->id,
            'receiver_id' => $chat->cliente_id,
            'mensagem' => $request->mensagem,
            'lida' => false,
        ]);

        // Atualizar última mensagem no chat
        $chat->update([
            'ultima_mensagem' => $request->mensagem,
            'ultima_mensagem_data' => now(),
        ]);

        // Notificar cliente
        NotificationService::send('mensagem.nova', $chat->cliente_id, [
            'chat_id' => $chat->id,
            'remetente' => $user->nome,
            'mensagem' => $request->mensagem,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mensagem enviada',
            'data' => [
                'id' => $mensagem->id,
                'mensagem' => $mensagem->mensagem,
                'created_at' => $mensagem->created_at,
                'is_user' => true
            ]
        ]);
    }

    /**
     * Marcar mensagens como lidas
     * PUT /api/prestador/chat/marcar-lidas/{chatId}
     */
    public function marcarComoLidas($chatId, Request $request)
    {
        $user = $request->user();

        $chat = Chat::where('prestador_id', $user->id)
            ->where('id', $chatId)
            ->first();

        if (!$chat) {
            return response()->json([
                'success' => false,
                'message' => 'Chat não encontrado'
            ], 404);
        }

        $updated = Mensagem::where('chat_id', $chatId)
            ->where('receiver_id', $user->id)
            ->where('lida', false)
            ->update([
                'lida' => true,
                'lida_em' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Mensagens marcadas como lidas',
            'data' => ['marcadas' => $updated]
        ]);
    }
}
