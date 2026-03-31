<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    /**
     * Buscar todas as mensagens entre o usuário logado e um prestador - COM CACHE
     * GET /api/chat/messages/{prestadorId}
     */
    public function getMessages(Request $request, $prestadorId)
    {
        $usuario = Auth::user();
        $cacheKey = "chat_messages_{$usuario->id}_{$prestadorId}";

        $mensagens = Cache::remember($cacheKey, 60, function() use ($usuario, $prestadorId) {
            return Mensagem::entre($usuario->id, $prestadorId)
                ->orderBy('created_at', 'asc')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $mensagens->map(function ($mensagem) use ($usuario) {
                return [
                    'id' => $mensagem->id,
                    'message' => $mensagem->mensagem,
                    'is_owner' => $mensagem->remetente_id == $usuario->id,
                    'created_at' => $mensagem->created_at,
                    'read_at' => $mensagem->lida_em,
                ];
            }),
        ]);
    }

    /**
     * Enviar uma nova mensagem - LIMPAR CACHE
     * POST /api/chat/messages
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'prestador_id' => 'required|exists:users,id',
            'message' => 'required|string|max:5000',
        ]);

        $usuario = Auth::user();
        $prestadorId = $request->prestador_id;

        // Verificar se o destinatário é um prestador
        $prestador = User::where('id', $prestadorId)
            ->where('tipo', 'prestador')
            ->firstOrFail();

        $mensagem = Mensagem::create([
            'remetente_id' => $usuario->id,
            'destinatario_id' => $prestadorId,
            'mensagem' => $request->message,
            'lida' => false,
        ]);

        // Limpar cache
        $this->clearChatCache($usuario->id, $prestadorId);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $mensagem->id,
                'message' => $mensagem->mensagem,
                'is_owner' => true,
                'created_at' => $mensagem->created_at,
                'read_at' => null,
            ],
            'message' => 'Mensagem enviada com sucesso',
        ], 201);
    }

    /**
     * Buscar últimas mensagens (para polling) - SEM CACHE (para polling)
     * GET /api/chat/messages/{prestadorId}/latest
     */
    public function getLatestMessages(Request $request, $prestadorId)
    {
        $usuario = Auth::user();
        $ultimoId = $request->input('last_id', 0);

        $mensagens = Mensagem::entre($usuario->id, $prestadorId)
            ->where('id', '>', $ultimoId)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $mensagens->map(function ($mensagem) use ($usuario) {
                return [
                    'id' => $mensagem->id,
                    'message' => $mensagem->mensagem,
                    'is_owner' => $mensagem->remetente_id == $usuario->id,
                    'created_at' => $mensagem->created_at,
                    'read_at' => $mensagem->lida_em,
                ];
            }),
        ]);
    }

    /**
     * Marcar mensagens como lidas - LIMPAR CACHE
     * PUT /api/chat/messages/{prestadorId}/read
     */
    public function markAsRead(Request $request, $prestadorId)
    {
        $usuario = Auth::user();

        $quantidade = Mensagem::entre($usuario->id, $prestadorId)
            ->where('destinatario_id', $usuario->id)
            ->where('lida', false)
            ->update([
                'lida' => true,
                'lida_em' => now(),
            ]);

        // Limpar cache
        $this->clearChatCache($usuario->id, $prestadorId);

        return response()->json([
            'success' => true,
            'data' => ['count' => $quantidade],
            'message' => 'Mensagens marcadas como lidas',
        ]);
    }

    /**
     * Buscar conversas recentes do usuário - COM CACHE
     * GET /api/chat/conversations
     */
    public function getConversations(Request $request)
    {
        $usuario = Auth::user();
        $cacheKey = "chat_conversations_{$usuario->id}";

        $contatos = Cache::remember($cacheKey, 60, function() use ($usuario) {
            $conversas = DB::table('mensagens')
                ->select(
                    DB::raw('CASE
                        WHEN remetente_id = ' . $usuario->id . ' THEN destinatario_id
                        ELSE remetente_id
                    END as contato_id'),
                    DB::raw('MAX(created_at) as ultima_mensagem'),
                    DB::raw('COUNT(CASE WHEN destinatario_id = ' . $usuario->id . ' AND lida = 0 THEN 1 END) as nao_lidas')
                )
                ->where('remetente_id', $usuario->id)
                ->orWhere('destinatario_id', $usuario->id)
                ->groupBy('contato_id')
                ->orderBy('ultima_mensagem', 'desc')
                ->get();

            $result = [];
            foreach ($conversas as $conversa) {
                $contato = User::find($conversa->contato_id);
                if ($contato) {
                    $ultimaMensagem = Mensagem::entre($usuario->id, $conversa->contato_id)
                        ->orderBy('created_at', 'desc')
                        ->first();

                    $result[] = [
                        'id' => $contato->id,
                        'nome' => $contato->nome,
                        'foto' => $contato->foto,
                        'tipo' => $contato->tipo,
                        'disponivel' => $contato->tipo === 'prestador',
                        'ultima_mensagem' => $ultimaMensagem ? [
                            'texto' => $ultimaMensagem->mensagem,
                            'data' => $ultimaMensagem->created_at,
                            'is_owner' => $ultimaMensagem->remetente_id == $usuario->id,
                        ] : null,
                        'nao_lidas' => $conversa->nao_lidas,
                    ];
                }
            }
            return $result;
        });

        return response()->json([
            'success' => true,
            'data' => $contatos,
        ]);
    }

    /**
     * Buscar contagem de mensagens não lidas - COM CACHE
     * GET /api/chat/unread-count
     */
    public function getUnreadCount(Request $request)
    {
        $usuario = Auth::user();
        $cacheKey = "chat_unread_count_{$usuario->id}";

        $count = Cache::remember($cacheKey, 30, function() use ($usuario) {
            return Mensagem::where('destinatario_id', $usuario->id)
                ->where('lida', false)
                ->count();
        });

        return response()->json([
            'success' => true,
            'data' => ['total' => $count],
        ]);
    }

    /**
     * Limpar cache do chat
     */
    private function clearChatCache($userId, $prestadorId)
    {
        Cache::forget("chat_messages_{$userId}_{$prestadorId}");
        Cache::forget("chat_conversations_{$userId}");
        Cache::forget("chat_unread_count_{$userId}");

        // Limpar também do prestador
        Cache::forget("chat_messages_{$prestadorId}_{$userId}");
        Cache::forget("chat_conversations_{$prestadorId}");
        Cache::forget("chat_unread_count_{$prestadorId}");
    }
}
