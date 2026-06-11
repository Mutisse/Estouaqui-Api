<?php
// app/Http/Controllers/Api/AvaliacaoController.php

namespace App\Http\Controllers\Api;

use App\Models\Avaliacao;
use App\Models\Pedido;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class AvaliacaoController extends BaseController
{
    /**
     * Buscar avaliação de um pedido específico
     * GET /api/pedidos/{pedidoId}/avaliacao
     */
    public function getAvaliacaoByPedido($pedidoId)
    {
        $user = request()->user();

        $avaliacao = Avaliacao::where('pedido_id', $pedidoId)
            ->where(function ($query) use ($user) {
                $query->where('cliente_id', $user->id)
                    ->orWhere('prestador_id', $user->id);
            })
            ->first();

        if (!$avaliacao) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Nenhuma avaliação encontrada para este pedido'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $avaliacao
        ]);
    }

    /**
     * Listar avaliações de um prestador
     * GET /api/prestadores/{prestadorId}/avaliacoes
     */
    public function getAvaliacoesByPrestador($prestadorId)
    {
        $avaliacoes = Avaliacao::where('prestador_id', $prestadorId)
            ->where('status', 'aprovada')
            ->with('cliente')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $avaliacoes->items(),
            'total' => $avaliacoes->total(),
            'current_page' => $avaliacoes->currentPage(),
            'last_page' => $avaliacoes->lastPage(),
        ]);
    }

    /**
     * Listar avaliações feitas por um cliente
     * GET /api/clientes/{clienteId}/avaliacoes
     */
    public function getAvaliacoesByCliente($clienteId)
    {
        $avaliacoes = Avaliacao::where('cliente_id', $clienteId)
            ->where('status', 'aprovada')
            ->with('prestador')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $avaliacoes->items(),
            'total' => $avaliacoes->total(),
        ]);
    }

    /**
     * Criar uma nova avaliação para um pedido
     * POST /api/pedidos/{pedidoId}/avaliar
     */
    public function store(Request $request, $pedidoId)
    {
        $user = $request->user();

        $request->validate([
            'nota' => 'required|integer|min:1|max:5',
            'comentario' => 'nullable|string|max:1000',
        ]);

        // Verificar se o pedido existe
        $pedido = Pedido::find($pedidoId);
        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não encontrado'
            ], 404);
        }

        // Verificar se o usuário é o cliente do pedido
        if ($pedido->cliente_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas o cliente pode avaliar este pedido'
            ], 403);
        }

        // Verificar se o pedido está concluído
        if ($pedido->status !== 'concluido') {
            return response()->json([
                'success' => false,
                'message' => 'Apenas pedidos concluídos podem ser avaliados'
            ], 422);
        }

        // Verificar se já existe avaliação para este pedido
        $existing = Avaliacao::where('pedido_id', $pedidoId)->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Este pedido já foi avaliado'
            ], 422);
        }

        // Criar avaliação
        $avaliacao = Avaliacao::create([
            'cliente_id' => $user->id,
            'prestador_id' => $pedido->prestador_id,
            'pedido_id' => $pedidoId,
            'nota' => $request->nota,
            'comentario' => $request->comentario,
            'status' => 'aprovada',
        ]);

        // Atualizar média do prestador
        $prestador = $pedido->prestador;
        if ($prestador) {
            $media = Avaliacao::where('prestador_id', $prestador->id)
                ->where('status', 'aprovada')
                ->avg('nota');

            $total = Avaliacao::where('prestador_id', $prestador->id)
                ->where('status', 'aprovada')
                ->count();

            $prestador->media_avaliacao = round($media, 2);
            $prestador->total_avaliacoes = $total;
            $prestador->save();
        }

        // 🔔 NOTIFICAÇÃO: Nova avaliação
        NotificationService::send('avaliacao.recebida', $pedido->prestador_id, [
            'cliente_nome' => $user->nome,
            'nota' => $request->nota,
            'pedido_id' => $pedidoId
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Avaliação enviada com sucesso!',
            'data' => $avaliacao
        ], 201);
    }

    /**
     * Atualizar uma avaliação existente
     * PUT /api/avaliacoes/{id}
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        $avaliacao = Avaliacao::find($id);

        if (!$avaliacao) {
            return response()->json([
                'success' => false,
                'message' => 'Avaliação não encontrada'
            ], 404);
        }

        // Verificar se o usuário é o dono da avaliação
        if ($avaliacao->cliente_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Você não tem permissão para editar esta avaliação'
            ], 403);
        }

        $request->validate([
            'nota' => 'sometimes|integer|min:1|max:5',
            'comentario' => 'nullable|string|max:1000',
        ]);

        if ($request->has('nota')) {
            $avaliacao->nota = $request->nota;
        }

        if ($request->has('comentario')) {
            $avaliacao->comentario = $request->comentario;
        }

        $avaliacao->save();

        // Atualizar média do prestador
        $prestador = $avaliacao->prestador;
        if ($prestador) {
            $media = Avaliacao::where('prestador_id', $prestador->id)
                ->where('status', 'aprovada')
                ->avg('nota');

            $prestador->media_avaliacao = round($media, 2);
            $prestador->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Avaliação atualizada com sucesso!',
            'data' => $avaliacao
        ]);
    }

    /**
     * Deletar uma avaliação
     * DELETE /api/avaliacoes/{id}
     */
    public function destroy($id)
    {
        $user = request()->user();

        $avaliacao = Avaliacao::find($id);

        if (!$avaliacao) {
            return response()->json([
                'success' => false,
                'message' => 'Avaliação não encontrada'
            ], 404);
        }

        // Verificar se o usuário é o dono
        if ($avaliacao->cliente_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Você não tem permissão para remover esta avaliação'
            ], 403);
        }

        $prestadorId = $avaliacao->prestador_id;
        $avaliacao->delete();

        // Atualizar média do prestador
        $prestador = \App\Models\User::find($prestadorId);
        if ($prestador) {
            $media = Avaliacao::where('prestador_id', $prestadorId)
                ->where('status', 'aprovada')
                ->avg('nota') ?? 0;

            $total = Avaliacao::where('prestador_id', $prestadorId)
                ->where('status', 'aprovada')
                ->count();

            $prestador->media_avaliacao = round($media, 2);
            $prestador->total_avaliacoes = $total;
            $prestador->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Avaliação removida com sucesso!'
        ]);
    }
}
