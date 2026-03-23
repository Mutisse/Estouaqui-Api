<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PedidoController extends Controller
{
    /**
     * Listar pedidos (admin)
     * GET /api/admin/pedidos
     */
    public function index(Request $request)
    {
        $query = Pedido::with(['cliente', 'prestador', 'servico']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        if ($request->has('prestador_id')) {
            $query->where('prestador_id', $request->prestador_id);
        }

        $pedidos = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $pedidos
        ]);
    }

    /**
     * Mostrar um pedido
     * GET /api/admin/pedidos/{id}
     */
    public function show($id)
    {
        $pedido = Pedido::with(['cliente', 'prestador', 'servico', 'avaliacao'])->find($id);

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
     * Atualizar status do pedido
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
     * Cancelar pedido (admin)
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

        return response()->json([
            'success' => true,
            'message' => 'Pedido cancelado com sucesso'
        ]);
    }
}
