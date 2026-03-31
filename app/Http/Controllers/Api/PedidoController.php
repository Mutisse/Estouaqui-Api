<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class PedidoController extends Controller
{
    /**
     * Listar pedidos (admin) - COM CACHE
     * GET /api/admin/pedidos
     */
    public function index(Request $request)
    {
        $cacheKey = 'admin_pedidos_' . md5($request->fullUrl());

        $pedidos = Cache::remember($cacheKey, 120, function() use ($request) {
            $query = Pedido::with(['cliente:id,nome,foto,telefone', 'prestador:id,nome,foto,telefone', 'servico:id,nome,preco,duracao']);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('cliente_id')) {
                $query->where('cliente_id', $request->cliente_id);
            }

            if ($request->has('prestador_id')) {
                $query->where('prestador_id', $request->prestador_id);
            }

            return $query->orderBy('created_at', 'desc')->paginate(20);
        });

        return response()->json([
            'success' => true,
            'data' => $pedidos
        ]);
    }

    /**
     * Mostrar um pedido - COM CACHE
     * GET /api/admin/pedidos/{id}
     */
    public function show($id)
    {
        $cacheKey = "pedido_detalhes_{$id}";

        $pedido = Cache::remember($cacheKey, 300, function() use ($id) {
            return Pedido::with(['cliente:id,nome,foto,telefone', 'prestador:id,nome,foto,telefone,media_avaliacao', 'servico:id,nome,preco,duracao', 'avaliacao'])->find($id);
        });

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'error' => 'Pedido não encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $pedido
        ]);
    }

    /**
     * Atualizar status do pedido - LIMPAR CACHE
     * PUT /api/admin/pedidos/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        $pedido = Pedido::find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'error' => 'Pedido não encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pendente,aceito,em_andamento,concluido,cancelado'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            $pedido->status = $request->status;
            $pedido->save();

            $this->clearPedidoCache($id, $pedido->cliente_id, $pedido->prestador_id);

            return response()->json([
                'success' => true,
                'message' => 'Status atualizado com sucesso',
                'data' => $pedido
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar status'
            ], 500);
        }
    }

    /**
     * Cancelar pedido (admin) - LIMPAR CACHE
     * DELETE /api/admin/pedidos/{id}/cancel
     */
    public function cancel($id)
    {
        $pedido = Pedido::find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'error' => 'Pedido não encontrado'
            ], 404);
        }

        $pedido->status = 'cancelado';
        $pedido->save();

        $this->clearPedidoCache($id, $pedido->cliente_id, $pedido->prestador_id);

        return response()->json([
            'success' => true,
            'message' => 'Pedido cancelado com sucesso'
        ]);
    }

    /**
     * Limpar cache do pedido
     */
    private function clearPedidoCache($pedidoId, $clienteId, $prestadorId)
    {
        Cache::forget("pedido_detalhes_{$pedidoId}");

        // Limpar cache do cliente
        $statuses = ['pendente', 'confirmado', 'concluido', 'cancelado', null];
        foreach ($statuses as $status) {
            for ($page = 1; $page <= 3; $page++) {
                $statusKey = $status ?: 'all';
                Cache::forget("cliente_pedidos_{$clienteId}_{$statusKey}_{$page}");
            }
        }

        // Limpar cache do prestador
        $statuses = ['pendente', 'aceito', 'concluido', 'cancelado', null];
        foreach ($statuses as $status) {
            for ($page = 1; $page <= 3; $page++) {
                $statusKey = $status ?: 'all';
                Cache::forget("prestador_solicitacoes_{$prestadorId}_{$statusKey}_{$page}");
            }
        }

        // Limpar cache de admin
        for ($page = 1; $page <= 5; $page++) {
            Cache::forget("admin_pedidos_page_{$page}");
        }
    }
}
