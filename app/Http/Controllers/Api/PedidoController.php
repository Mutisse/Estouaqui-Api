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
     * Listar pedidos (admin) - ✅ RETORNA ARRAY COM CASTS
     * GET /api/admin/pedidos
     */
    public function index(Request $request)
    {
        $cacheKey = 'admin_pedidos_' . md5($request->fullUrl());

        $pedidos = Cache::remember($cacheKey, 120, function () use ($request) {
            $query = Pedido::with([
                'cliente:id,nome,foto,telefone',
                'prestador:id,nome,foto,telefone',
                'servico:id,nome,preco,duracao',
                'categoria:id,nome,icone,cor'  // ✅ ADICIONADO
            ]);

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
     * Mostrar um pedido - ✅ RETORNA ARRAY COM CASTS
     * GET /api/admin/pedidos/{id}
     */
    public function show($id)
    {
        $cacheKey = "pedido_detalhes_{$id}";

        $pedido = Cache::remember($cacheKey, 300, function () use ($id) {
            return Pedido::with([
                'cliente:id,nome,foto,telefone',
                'prestador:id,nome,foto,telefone,media_avaliacao',
                'servico:id,nome,preco,duracao',
                'categoria:id,nome,icone,cor',  // ✅ ADICIONADO
                'avaliacao'
            ])->find($id);
        });

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'error' => 'Pedido não encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatPedido($pedido)
        ]);
    }

    /**
     * Cliente criar novo pedido (NOVO MÉTODO)
     * POST /api/cliente/pedidos
     */
    public function createPedido(Request $request)
    {
        $cliente = $request->user();

        $validator = Validator::make($request->all(), [
            'categoria_id' => 'required|exists:categorias,id',
            'descricao' => 'required|string|max:1000',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'endereco' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            $fotoPath = null;
            if ($request->hasFile('foto')) {
                $fotoPath = $request->file('foto')->store('pedidos', 'public');
            }

            $pedido = Pedido::create([
                'numero' => 'PED-' . strtoupper(uniqid()),
                'cliente_id' => $cliente->id,
                'categoria_id' => $request->categoria_id,
                'descricao' => $request->descricao,
                'foto' => $fotoPath,
                'endereco' => $request->endereco,
                'status' => 'aberto',  // ✅ NOVO STATUS
                'prestador_id' => null,  // ✅ NULL até aceitar proposta
                'servico_id' => null,    // ✅ NULL (não usa serviço pré-definido)
                'valor' => null,         // ✅ NULL até aceitar proposta
            ]);

            // Limpar cache
            $this->clearPedidoCache($pedido->id, $cliente->id, null);

            return response()->json([
                'success' => true,
                'message' => 'Pedido publicado com sucesso!',
                'data' => $this->formatPedido($pedido)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar pedido: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cliente listar seus pedidos
     * GET /api/cliente/pedidos
     */
    public function meusPedidos(Request $request)
    {
        $cliente = $request->user();

        $pedidos = Pedido::where('cliente_id', $cliente->id)
            ->with(['categoria', 'prestador' => function ($q) {
                $q->select('id', 'nome', 'foto', 'telefone', 'media_avaliacao');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $pedidos->map(function ($pedido) {
                return $this->formatPedido($pedido);
            })
        ]);
    }

    /**
     * Atualizar status do pedido - ✅ RETORNA ARRAY COM CASTS
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
            'status' => 'required|in:aberto,pendente,aceito,em_andamento,concluido,cancelado'
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

            $pedido->load([
                'cliente:id,nome,foto,telefone',
                'prestador:id,nome,foto,telefone,media_avaliacao',
                'servico:id,nome,preco,duracao',
                'categoria:id,nome,icone,cor',
                'avaliacao'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Status atualizado com sucesso',
                'data' => $this->formatPedido($pedido)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancelar pedido (admin) - ✅ RETORNA MENSAGEM
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

        try {
            $pedido->status = 'cancelado';
            $pedido->save();

            $this->clearPedidoCache($id, $pedido->cliente_id, $pedido->prestador_id);

            return response()->json([
                'success' => true,
                'message' => 'Pedido cancelado com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao cancelar pedido: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Formatar pedido com casts corretos (ATUALIZADO)
     */
    private function formatPedido($pedido)
    {
        return [
            'id' => (int) $pedido->id,
            'numero' => (string) $pedido->numero,
            'status' => (string) $pedido->status,
            'descricao' => $pedido->descricao ? (string) $pedido->descricao : null,  // ✅ NOVO
            'foto' => $pedido->foto ? asset('storage/' . $pedido->foto) : null,      // ✅ NOVO
            'data' => $pedido->data ? $pedido->data->toISOString() : null,
            'endereco' => (string) $pedido->endereco,
            'observacoes' => $pedido->observacoes ? (string) $pedido->observacoes : null,
            'valor' => $pedido->valor ? (float) $pedido->valor : null,  // ✅ PODE SER NULL
            'created_at' => $pedido->created_at ? $pedido->created_at->toISOString() : null,
            'updated_at' => $pedido->updated_at ? $pedido->updated_at->toISOString() : null,

            // ✅ NOVO - Categoria
            'categoria' => $pedido->categoria ? [
                'id' => (int) $pedido->categoria->id,
                'nome' => (string) $pedido->categoria->nome,
                'icone' => (string) $pedido->categoria->icone,
                'cor' => (string) $pedido->categoria->cor,
            ] : null,

            // Relacionamentos
            'cliente' => $pedido->cliente ? [
                'id' => (int) $pedido->cliente->id,
                'nome' => (string) $pedido->cliente->nome,
                'foto' => $pedido->cliente->foto ? asset('storage/' . $pedido->cliente->foto) : null,
                'telefone' => (string) $pedido->cliente->telefone,
            ] : null,

            'prestador' => $pedido->prestador ? [
                'id' => (int) $pedido->prestador->id,
                'nome' => (string) $pedido->prestador->nome,
                'foto' => $pedido->prestador->foto ? asset('storage/' . $pedido->prestador->foto) : null,
                'telefone' => (string) $pedido->prestador->telefone,
                'media_avaliacao' => (float) ($pedido->prestador->media_avaliacao ?? 0),
            ] : null,

            'servico' => $pedido->servico ? [
                'id' => (int) $pedido->servico->id,
                'nome' => (string) $pedido->servico->nome,
                'preco' => (float) $pedido->servico->preco,
                'duracao' => (int) $pedido->servico->duracao,
            ] : null,

            'avaliacao' => $pedido->avaliacao ? [
                'id' => (int) $pedido->avaliacao->id,
                'nota' => (int) $pedido->avaliacao->nota,
                'comentario' => (string) $pedido->avaliacao->comentario,
                'created_at' => $pedido->avaliacao->created_at ? $pedido->avaliacao->created_at->toISOString() : null,
            ] : null,
        ];
    }

    /**
     * Limpar cache do pedido (ATUALIZADO)
     */
    private function clearPedidoCache($pedidoId, $clienteId, $prestadorId)
    {
        // Limpar cache do pedido específico
        Cache::forget("pedido_detalhes_{$pedidoId}");

        // Limpar cache de admin
        for ($page = 1; $page <= 5; $page++) {
            Cache::forget("admin_pedidos_page_{$page}");
            $statuses = ['aberto', 'pendente', 'aceito', 'em_andamento', 'concluido', 'cancelado'];
            foreach ($statuses as $status) {
                Cache::forget("admin_pedidos_status_{$status}_page_{$page}");
            }
        }

        // Limpar cache do cliente
        if ($clienteId) {
            $statuses = ['aberto', 'pendente', 'aceito', 'concluido', 'cancelado', null];
            foreach ($statuses as $status) {
                for ($page = 1; $page <= 5; $page++) {
                    $statusKey = $status ?: 'all';
                    Cache::forget("cliente_pedidos_{$clienteId}_{$statusKey}_{$page}");
                }
            }
        }

        // Limpar cache do prestador
        if ($prestadorId) {
            $statuses = ['aberto', 'pendente', 'aceito', 'concluido', 'cancelado', null];
            foreach ($statuses as $status) {
                for ($page = 1; $page <= 5; $page++) {
                    $statusKey = $status ?: 'all';
                    Cache::forget("prestador_solicitacoes_{$prestadorId}_{$statusKey}_{$page}");
                }
            }
        }
    }
}
