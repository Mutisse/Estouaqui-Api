<?php
// app/Http/Controllers/Api/ChatController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    /**
     * Buscar todas as mensagens entre o usuário logado e um prestador
     */
    public function getMessages(Request $request, $prestadorId)
    {
        $usuario = Auth::user();

        // Verificar se o prestador existe
        $prestador = User::where('id', $prestadorId)
            ->where('tipo', 'prestador')
            ->firstOrFail();

        // Buscar mensagens entre os dois usuários
        $mensagens = Mensagem::entre($usuario->id, $prestadorId)
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
     * Enviar uma nova mensagem
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

        // Criar a mensagem
        $mensagem = Mensagem::create([
            'remetente_id' => $usuario->id,
            'destinatario_id' => $prestadorId,
            'mensagem' => $request->message,
            'lida' => false,
        ]);

        // Carregar o relacionamento para a resposta
        $mensagem->load('remetente', 'destinatario');

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
     * Buscar últimas mensagens (para polling)
     */
    public function getLatestMessages(Request $request, $prestadorId)
    {
        $usuario = Auth::user();
        $ultimoId = $request->input('last_id', 0);

        // Verificar se o prestador existe
        User::where('id', $prestadorId)
            ->where('tipo', 'prestador')
            ->firstOrFail();

        // Buscar mensagens mais recentes
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
     * Marcar mensagens como lidas
     */
    public function markAsRead(Request $request, $prestadorId)
    {
        $usuario = Auth::user();

        // Verificar se o prestador existe
        User::where('id', $prestadorId)
            ->where('tipo', 'prestador')
            ->firstOrFail();

        // Marcar mensagens não lidas como lidas
        $quantidade = Mensagem::entre($usuario->id, $prestadorId)
            ->where('destinatario_id', $usuario->id)
            ->where('lida', false)
            ->update([
                'lida' => true,
                'lida_em' => now(),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'count' => $quantidade,
            ],
            'message' => 'Mensagens marcadas como lidas',
        ]);
    }

    /**
     * Buscar conversas recentes do usuário
     */
    public function getConversations(Request $request)
    {
        $usuario = Auth::user();

        // Buscar todos os usuários com quem o usuário atual conversou
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

        // Carregar os dados dos contatos
        $contatos = [];
        foreach ($conversas as $conversa) {
            $contato = User::find($conversa->contato_id);
            if ($contato) {
                // Buscar a última mensagem
                $ultimaMensagem = Mensagem::entre($usuario->id, $conversa->contato_id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                $contatos[] = [
                    'id' => $contato->id,
                    'nome' => $contato->nome,
                    'foto' => $contato->foto,
                    'tipo' => $contato->tipo,
                    'disponivel' => $contato->tipo === 'prestador' ? true : false,
                    'ultima_mensagem' => $ultimaMensagem ? [
                        'texto' => $ultimaMensagem->mensagem,
                        'data' => $ultimaMensagem->created_at,
                        'is_owner' => $ultimaMensagem->remetente_id == $usuario->id,
                    ] : null,
                    'nao_lidas' => $conversa->nao_lidas,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $contatos,
        ]);
    }

    /**
     * Buscar contagem de mensagens não lidas
     */
    public function getUnreadCount(Request $request)
    {
        $usuario = Auth::user();

        $count = Mensagem::where('destinatario_id', $usuario->id)
            ->where('lida', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $count,
            ],
        ]);
    }
}
