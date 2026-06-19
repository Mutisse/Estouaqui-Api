<?php

namespace App\Http\Controllers\Api;

use App\Models\Proposta;
use App\Models\Pedido;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientePropostaController extends BaseController
{
    /**
     * Listar todas as propostas recebidas pelo cliente
     * GET /api/cliente/propostas
     */
    public function index(Request $request)
    {
        $user = $request->user();

        try {
            $propostas = Proposta::whereHas('pedido', function ($query) use ($user) {
                $query->where('cliente_id', $user->id);
            })
            ->with(['pedido', 'prestador', 'servico'])
            ->orderBy('created_at', 'desc')
            ->get();

            return response()->json([
                'success' => true,
                'data' => $propostas,
                'total' => $propostas->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar propostas do cliente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar propostas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar detalhes de uma proposta específica
     * GET /api/cliente/propostas/{id}
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        try {
            $proposta = Proposta::with(['pedido', 'prestador', 'servico'])
                ->whereHas('pedido', function ($query) use ($user) {
                    $query->where('cliente_id', $user->id);
                })
                ->find($id);

            if (!$proposta) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proposta não encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $proposta
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar proposta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar proposta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aceitar uma proposta
     * POST /api/cliente/propostas/{id}/aceitar
     */
    public function aceitar(Request $request, $id)
    {
        $user = $request->user();

        try {
            $proposta = Proposta::with(['pedido', 'prestador', 'servico'])
                ->whereHas('pedido', function ($query) use ($user) {
                    $query->where('cliente_id', $user->id);
                })
                ->find($id);

            if (!$proposta) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proposta não encontrada'
                ], 404);
            }

            // Verificar se a proposta pode ser aceita
            if (!in_array($proposta->status, ['pendente', 'enviada'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta proposta não pode mais ser aceita. Status atual: ' . $proposta->status
                ], 422);
            }

            DB::transaction(function () use ($proposta, $user) {
                // Atualizar status da proposta
                $proposta->status = 'aceita';
                $proposta->save();

                // Atualizar o pedido com o prestador escolhido
                $pedido = $proposta->pedido;
                if ($pedido) {
                    $pedido->prestador_id = $proposta->prestador_id;
                    $pedido->status = 'aceito';
                    $pedido->save();

                    // Atualizar outras propostas do mesmo pedido para 'recusada'
                    Proposta::where('pedido_id', $pedido->id)
                        ->where('id', '!=', $proposta->id)
                        ->whereIn('status', ['pendente', 'enviada'])
                        ->update(['status' => 'recusada']);
                }

                // Notificar o prestador que a proposta foi aceita
                NotificationService::send('proposta.aceita', $proposta->prestador_id, [
                    'proposta_id' => $proposta->id,
                    'pedido_id' => $pedido->id ?? null,
                    'cliente_nome' => $user->nome,
                    'proposta_valor' => $proposta->valor,
                ]);

                // Notificar o cliente que a proposta foi aceita
                NotificationService::send('proposta.aceita_cliente', $user->id, [
                    'proposta_id' => $proposta->id,
                    'pedido_id' => $pedido->id ?? null,
                    'prestador_nome' => $proposta->prestador->nome ?? 'Prestador',
                    'proposta_valor' => $proposta->valor,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Proposta aceita com sucesso!',
                'data' => $proposta->load(['pedido', 'prestador', 'servico'])
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao aceitar proposta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao aceitar proposta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recusar uma proposta
     * POST /api/cliente/propostas/{id}/recusar
     */
    public function recusar(Request $request, $id)
    {
        $user = $request->user();

        try {
            $proposta = Proposta::with(['prestador'])
                ->whereHas('pedido', function ($query) use ($user) {
                    $query->where('cliente_id', $user->id);
                })
                ->find($id);

            if (!$proposta) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proposta não encontrada'
                ], 404);
            }

            if (!in_array($proposta->status, ['pendente', 'enviada'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta proposta não pode mais ser recusada. Status atual: ' . $proposta->status
                ], 422);
            }

            $proposta->status = 'recusada';
            $proposta->save();

            // Notificar o prestador que a proposta foi recusada
            NotificationService::send('proposta.recusada', $proposta->prestador_id, [
                'proposta_id' => $proposta->id,
                'cliente_nome' => $user->nome,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Proposta recusada com sucesso',
                'data' => $proposta->load(['pedido', 'prestador', 'servico'])
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao recusar proposta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao recusar proposta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estatísticas das propostas do cliente
     * GET /api/cliente/propostas/estatisticas
     */
    public function estatisticas(Request $request)
    {
        $user = $request->user();

        try {
            $propostas = Proposta::whereHas('pedido', function ($query) use ($user) {
                $query->where('cliente_id', $user->id);
            })->get();

            $estatisticas = [
                'total' => $propostas->count(),
                'pendentes' => $propostas->whereIn('status', ['pendente', 'enviada'])->count(),
                'enviadas' => $propostas->where('status', 'enviada')->count(),
                'aceitas' => $propostas->where('status', 'aceita')->count(),
                'recusadas' => $propostas->where('status', 'recusada')->count(),
                'expiradas' => $propostas->where('status', 'expirada')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $estatisticas
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao carregar estatísticas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar estatísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Contagem de propostas pendentes
     * GET /api/cliente/propostas/pendentes/count
     */
    public function pendentesCount(Request $request)
    {
        $user = $request->user();

        try {
            $count = Proposta::whereHas('pedido', function ($query) use ($user) {
                $query->where('cliente_id', $user->id);
            })
            ->whereIn('status', ['pendente', 'enviada'])
            ->count();

            return response()->json([
                'success' => true,
                'count' => $count
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao contar propostas pendentes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao contar propostas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar se o cliente já aceitou alguma proposta para um pedido
     * GET /api/cliente/propostas/check/{pedidoId}
     */
    public function checkPropostaAceita(Request $request, $pedidoId)
    {
        $user = $request->user();

        try {
            $proposta = Proposta::whereHas('pedido', function ($query) use ($user, $pedidoId) {
                $query->where('cliente_id', $user->id)
                      ->where('id', $pedidoId);
            })
            ->where('status', 'aceita')
            ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'aceita' => !is_null($proposta),
                    'proposta_id' => $proposta->id ?? null,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao verificar proposta aceita: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao verificar proposta: ' . $e->getMessage()
            ], 500);
        }
    }
}
