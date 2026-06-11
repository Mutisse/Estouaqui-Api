<?php
// app/Http/Controllers/Api/ChatController.php

namespace App\Http\Controllers\Api;

use App\Models\Chat;
use App\Models\Mensagem;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends BaseController
{
    /**
     * Listar todos os chats do usuário autenticado
     * GET /api/chat/chats
     */
    public function chats(Request $request)
    {
        $user = $request->user();

        $chats = Chat::where('cliente_id', $user->id)
            ->orWhere('prestador_id', $user->id)
            ->with(['prestador', 'cliente'])
            ->orderBy('ultima_mensagem_data', 'desc')
            ->get();

        // Formatar dados para o frontend
        $chats->each(function ($chat) use ($user) {
            $isCliente = $chat->cliente_id === $user->id;
            $interlocutor = $isCliente ? $chat->prestador : $chat->cliente;

            if ($interlocutor) {
                $interlocutor->foto = $interlocutor->foto ? asset('storage/' . $interlocutor->foto) : null;
            }

            $chat->prestador_id = $chat->prestador_id;
            $chat->cliente_id = $chat->cliente_id;
            $chat->mensagens_nao_lidas = Mensagem::where('chat_id', $chat->id)
                ->where('receiver_id', $user->id)
                ->where('lida', false)
                ->count();
        });

        return response()->json([
            'success' => true,
            'data' => $chats
        ]);
    }

    /**
     * Buscar mensagens de um chat específico
     * GET /api/chat/mensagens/{prestadorId}
     */
    public function mensagens(Request $request, $prestadorId)
    {
        $user = $request->user();

        // Buscar chat existente
        $chat = Chat::where(function ($query) use ($user, $prestadorId) {
            $query->where('cliente_id', $user->id)
                ->where('prestador_id', $prestadorId);
        })->orWhere(function ($query) use ($user, $prestadorId) {
            $query->where('cliente_id', $prestadorId)
                ->where('prestador_id', $user->id);
        })->first();

        // Se não existir, criar novo chat
        if (!$chat) {
            $chat = Chat::create([
                'cliente_id' => $user->id,
                'prestador_id' => $prestadorId,
            ]);
        }

        $limit = $request->get('limit', 50);
        $mensagens = Mensagem::where('chat_id', $chat->id)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        // Formatar mensagens
        $mensagens->each(function ($msg) use ($user) {
            $msg->is_owner = $msg->sender_id === $user->id;
        });

        return response()->json([
            'success' => true,
            'data' => $mensagens,
            'chat_id' => $chat->id
        ]);
    }

    /**
     * Buscar novas mensagens (polling)
     * GET /api/chat/mensagens/{prestadorId}/novas
     */
    public function novasMensagens(Request $request, $prestadorId)
    {
        $user = $request->user();
        $ultimoId = $request->get('ultimo_id', 0);

        $chat = Chat::where(function ($query) use ($user, $prestadorId) {
            $query->where('cliente_id', $user->id)
                ->where('prestador_id', $prestadorId);
        })->orWhere(function ($query) use ($user, $prestadorId) {
            $query->where('cliente_id', $prestadorId)
                ->where('prestador_id', $user->id);
        })->first();

        if (!$chat) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        $mensagens = Mensagem::where('chat_id', $chat->id)
            ->where('id', '>', $ultimoId)
            ->orderBy('created_at', 'asc')
            ->get();

        $mensagens->each(function ($msg) use ($user) {
            $msg->is_owner = $msg->sender_id === $user->id;
        });

        return response()->json([
            'success' => true,
            'data' => $mensagens
        ]);
    }

    /**
     * Enviar mensagem
     * POST /api/chat/enviar/{prestadorId}
     */
    public function enviar(Request $request, $prestadorId)
    {
        $request->validate([
            'message' => 'required|string|max:1000'
        ]);

        $user = $request->user();
        $prestador = User::find($prestadorId);

        if (!$prestador || !$prestador->isPrestador()) {
            return response()->json([
                'success' => false,
                'message' => 'Prestador não encontrado'
            ], 404);
        }

        // Buscar ou criar chat
        $chat = Chat::firstOrCreate(
            [
                'cliente_id' => $user->id,
                'prestador_id' => $prestadorId,
            ],
            [
                'cliente_id' => $user->id,
                'prestador_id' => $prestadorId,
            ]
        );

        // Criar mensagem
        $mensagem = Mensagem::create([
            'chat_id' => $chat->id,
            'sender_id' => $user->id,
            'receiver_id' => $prestadorId,
            'mensagem' => $request->message,
            'lida' => false,
        ]);

        // Atualizar última mensagem do chat
        $chat->update([
            'ultima_mensagem' => substr($request->message, 0, 100),
            'ultima_mensagem_data' => now(),
        ]);

        // 🔔 NOTIFICAÇÃO 1: Nova mensagem para o prestador
        NotificationService::send('mensagem.nova_prestador', $prestadorId, [
            'chat_id' => $chat->id,
            'cliente_nome' => $user->nome,
            'mensagem' => substr($request->message, 0, 50),
        ]);

        $mensagem->is_owner = true;

        return response()->json([
            'success' => true,
            'message' => 'Mensagem enviada com sucesso',
            'data' => $mensagem
        ], 201);
    }

    /**
     * Marcar mensagens como lidas
     * POST /api/chat/marcar-lidas/{prestadorId}
     */
    public function marcarLidas(Request $request, $prestadorId)
    {
        $user = $request->user();

        $chat = Chat::where(function ($query) use ($user, $prestadorId) {
            $query->where('cliente_id', $user->id)
                ->where('prestador_id', $prestadorId);
        })->orWhere(function ($query) use ($user, $prestadorId) {
            $query->where('cliente_id', $prestadorId)
                ->where('prestador_id', $user->id);
        })->first();

        if (!$chat) {
            return response()->json([
                'success' => false,
                'message' => 'Chat não encontrado'
            ], 404);
        }

        $updated = Mensagem::where('chat_id', $chat->id)
            ->where('receiver_id', $user->id)
            ->where('lida', false)
            ->update([
                'lida' => true,
                'lida_em' => now(),
            ]);

        // Se o usuário que leu é o prestador, notificar o cliente
        if ($user->isPrestador() && $updated > 0) {
            // 🔔 NOTIFICAÇÃO 2: Prestador leu as mensagens
            NotificationService::send('mensagem.lidas', $chat->cliente_id, [
                'chat_id' => $chat->id,
                'prestador_nome' => $user->nome,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "{$updated} mensagens marcadas como lidas",
            'updated_count' => $updated
        ]);
    }

    /**
     * Contar mensagens não lidas
     * GET /api/chat/nao-lidas
     */
    public function naoLidas(Request $request)
    {
        $user = $request->user();

        $total = Mensagem::where('receiver_id', $user->id)
            ->where('lida', false)
            ->count();

        return response()->json([
            'success' => true,
            'total' => $total
        ]);
    }
}
