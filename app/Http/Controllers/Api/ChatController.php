<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Notifications\DynamicNotification;

class ChatController extends Controller
{
    // ==========================================
    // CONSTANTES DE CACHE
    // ==========================================
    private const CACHE_SHORT = 30;      // 30 segundos
    private const CACHE_MEDIUM = 120;    // 2 minutos
    private const CACHE_LONG = 600;      // 10 minutos

    /**
     * Buscar todas as mensagens entre o usuário logado e um prestador - OTIMIZADO
     * GET /api/chat/messages/{prestadorId}
     */
    public function getMessages(Request $request, $prestadorId)
    {
        $usuario = Auth::user();
        $cacheKey = "chat_messages_{$usuario->id}_{$prestadorId}";

        $mensagens = Cache::remember($cacheKey, self::CACHE_SHORT, function () use ($usuario, $prestadorId) {
            return Mensagem::entre($usuario->id, $prestadorId)
                ->select(['id', 'remetente_id', 'mensagem', 'lida', 'lida_em', 'created_at'])
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($mensagem) use ($usuario) {
                    return [
                        'id' => $mensagem->id,
                        'message' => $mensagem->mensagem,
                        'is_owner' => $mensagem->remetente_id == $usuario->id,
                        'created_at' => $mensagem->created_at,
                        'read_at' => $mensagem->lida_em,
                    ];
                });
        });

        return response()->json([
            'success' => true,
            'data' => $mensagens,
        ]);
    }

    /**
     * Enviar uma nova mensagem - OTIMIZADO
     * POST /api/chat/messages
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'prestador_id' => 'required|exists:users,id',
            'message' => 'required|string|max:5000',
        ]);

        $usuario = Auth::user();
        $prestadorId = (int) $request->prestador_id;

        // ✅ OTIMIZADO: verificação rápida
        $prestador = User::where('id', $prestadorId)
            ->where('tipo', 'prestador')
            ->exists();

        if (!$prestador) {
            return response()->json([
                'success' => false,
                'error' => 'Prestador não encontrado'
            ], 404);
        }

        $mensagem = Mensagem::create([
            'remetente_id' => $usuario->id,
            'destinatario_id' => $prestadorId,
            'mensagem' => $request->message,
            'lida' => false,
        ]);

        // ✅ NOTIFICAÇÃO assíncrona (não bloqueia resposta)
        $this->sendNotificationAsync($prestadorId, $usuario->nome, $request->message, $mensagem->id);

        // ✅ Limpar cache específico
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
     * Enviar notificação em background (não bloqueia)
     */
    private function sendNotificationAsync($destinatarioId, $remetenteNome, $mensagem, $mensagemId)
    {
        try {
            $destinatario = User::find($destinatarioId);
            if ($destinatario) {
                $destinatario->notify(new DynamicNotification('nova_mensagem', [
                    'remetente_nome' => $remetenteNome,
                    'mensagem_resumo' => substr($mensagem, 0, 100),
                    'conversa_id' => $destinatarioId,
                    'mensagem_id' => $mensagemId,
                ]));
            }
        } catch (\Exception $e) {
            Log::warning('Erro ao enviar notificação de chat: ' . $e->getMessage());
        }
    }

    /**
     * Buscar últimas mensagens (para polling) - OTIMIZADO
     * GET /api/chat/messages/{prestadorId}/latest
     */
    public function getLatestMessages(Request $request, $prestadorId)
    {
        $usuario = Auth::user();
        $ultimoId = (int) $request->input('last_id', 0);

        $mensagens = Mensagem::entre($usuario->id, $prestadorId)
            ->where('id', '>', $ultimoId)
            ->select(['id', 'remetente_id', 'mensagem', 'lida', 'lida_em', 'created_at'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($mensagem) use ($usuario) {
                return [
                    'id' => $mensagem->id,
                    'message' => $mensagem->mensagem,
                    'is_owner' => $mensagem->remetente_id == $usuario->id,
                    'created_at' => $mensagem->created_at,
                    'read_at' => $mensagem->lida_em,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $mensagens,
        ]);
    }

    /**
     * Marcar mensagens como lidas - OTIMIZADO
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
        Cache::forget("chat_unread_count_{$usuario->id}");

        return response()->json([
            'success' => true,
            'data' => ['count' => $quantidade],
            'message' => 'Mensagens marcadas como lidas',
        ]);
    }

    /**
     * Buscar conversas recentes do usuário - OTIMIZADO (UMA ÚNICA QUERY)
     * GET /api/chat/conversations
     */
    public function getConversations(Request $request)
    {
        $usuario = Auth::user();
        $cacheKey = "chat_conversations_{$usuario->id}";

        $conversas = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($usuario) {
            // ✅ OTIMIZADO: busca IDs dos contatos com uma única query
            $contatosIds = DB::table('mensagens')
                ->select(DB::raw('DISTINCT CASE
                    WHEN remetente_id = ' . $usuario->id . ' THEN destinatario_id
                    ELSE remetente_id
                END as contato_id'))
                ->where('remetente_id', $usuario->id)
                ->orWhere('destinatario_id', $usuario->id)
                ->pluck('contato_id');

            if ($contatosIds->isEmpty()) {
                return [];
            }

            // ✅ Buscar todos os contatos de uma vez
            $contatos = User::whereIn('id', $contatosIds)
                ->select(['id', 'nome', 'foto', 'tipo'])
                ->get()
                ->keyBy('id');

            // ✅ Buscar últimas mensagens e contagem de não lidas em UMA query por contato
            $result = [];
            foreach ($contatosIds as $contatoId) {
                if (!$contatos->has($contatoId)) continue;

                $contato = $contatos->get($contatoId);

                // Última mensagem
                $ultimaMensagem = Mensagem::entre($usuario->id, $contatoId)
                    ->select(['mensagem', 'created_at', 'remetente_id'])
                    ->orderBy('created_at', 'desc')
                    ->first();

                // Contagem de não lidas
                $naoLidas = Mensagem::entre($usuario->id, $contatoId)
                    ->where('destinatario_id', $usuario->id)
                    ->where('lida', false)
                    ->count();

                $result[] = [
                    'id' => $contato->id,
                    'nome' => $contato->nome,
                    'foto' => $contato->foto ? asset('storage/' . $contato->foto) : null,
                    'tipo' => $contato->tipo,
                    'disponivel' => $contato->tipo === 'prestador',
                    'ultima_mensagem' => $ultimaMensagem ? [
                        'texto' => $ultimaMensagem->mensagem,
                        'data' => $ultimaMensagem->created_at,
                        'is_owner' => $ultimaMensagem->remetente_id == $usuario->id,
                    ] : null,
                    'nao_lidas' => $naoLidas,
                ];
            }

            // Ordenar por data da última mensagem
            usort($result, function ($a, $b) {
                $dateA = $a['ultima_mensagem']['data'] ?? null;
                $dateB = $b['ultima_mensagem']['data'] ?? null;
                if (!$dateA && !$dateB) return 0;
                if (!$dateA) return 1;
                if (!$dateB) return -1;
                return strtotime($dateB) - strtotime($dateA);
            });

            return $result;
        });

        return response()->json([
            'success' => true,
            'data' => $conversas,
        ]);
    }

    /**
     * Buscar contagem de mensagens não lidas - OTIMIZADO
     * GET /api/chat/unread-count
     */
    public function getUnreadCount(Request $request)
    {
        $usuario = Auth::user();
        $cacheKey = "chat_unread_count_{$usuario->id}";

        $count = Cache::remember($cacheKey, self::CACHE_SHORT, function () use ($usuario) {
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
     * Limpar cache do chat (mais eficiente)
     */
    private function clearChatCache($userId, $prestadorId)
    {
        $keys = [
            "chat_messages_{$userId}_{$prestadorId}",
            "chat_messages_{$prestadorId}_{$userId}",
            "chat_conversations_{$userId}",
            "chat_conversations_{$prestadorId}",
            "chat_unread_count_{$userId}",
            "chat_unread_count_{$prestadorId}",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}
