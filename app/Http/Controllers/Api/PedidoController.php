<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PedidoController extends Controller
{
    /**
     * Listar pedidos (admin)
     * GET /api/admin/pedidos
     */
    public function index(Request $request)
    {
        Log::info('🔵 PedidoController@index - INICIADO');

        $cacheKey = 'admin_pedidos_' . md5($request->fullUrl());

        $pedidos = Cache::remember($cacheKey, 120, function () use ($request) {
            $query = Pedido::with([
                'cliente:id,nome,foto,telefone',
                'prestador:id,nome,foto,telefone',
                'servico:id,nome,preco,duracao',
                'categoria:id,nome,icone,cor'
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

        Log::info('✅ PedidoController@index - FINALIZADO');

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
        Log::info('🔵 PedidoController@show - ID: ' . $id);

        $cacheKey = "pedido_detalhes_{$id}";

        $pedido = Cache::remember($cacheKey, 300, function () use ($id) {
            return Pedido::with([
                'cliente:id,nome,foto,telefone',
                'prestador:id,nome,foto,telefone,media_avaliacao',
                'servico:id,nome,preco,duracao',
                'categoria:id,nome,icone,cor',
                'avaliacao'
            ])->find($id);
        });

        if (!$pedido) {
            Log::warning('⚠️ Pedido não encontrado - ID: ' . $id);
            return response()->json([
                'success' => false,
                'error' => 'Pedido não encontrado'
            ], 404);
        }

        Log::info('✅ PedidoController@show - FINALIZADO');

        return response()->json([
            'success' => true,
            'data' => $this->formatPedido($pedido)
        ]);
    }

    /**
     * Cliente criar novo pedido
     * POST /api/cliente/pedidos
     */
    /**
     * Cliente criar novo pedido
     * POST /api/cliente/pedidos
     */
    /**
     * Cliente criar novo pedido
     * POST /api/cliente/pedidos
     */
    public function createPedido(Request $request)
    {
        Log::info('🔵🔵🔵 PedidoController@createPedido - INICIADO 🔵🔵🔵');

        try {
            $cliente = $request->user();

            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
            }

            // PEGAR TODOS OS DADOS DO FormData
            $categoria_id = $request->post('categoria_id');
            $descricao = $request->post('descricao');
            $endereco = $request->post('endereco');

            Log::info('📌 categoria_id: ' . $categoria_id);
            Log::info('📌 descricao: ' . $descricao);
            Log::info('📌 endereco: ' . $endereco);

            // VALIDAÇÃO
            if (!$categoria_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Categoria é obrigatória'
                ], 422);
            }

            if (!$descricao) {
                return response()->json([
                    'success' => false,
                    'message' => 'Descrição é obrigatória'
                ], 422);
            }

            if (!$endereco) {
                return response()->json([
                    'success' => false,
                    'message' => 'Endereço é obrigatório'
                ], 422);
            }

            // FOTO
            $fotoPath = null;
            if ($request->hasFile('foto')) {
                $fotoPath = $request->file('foto')->store('pedidos', 'public');
            }

            // CRIAR PEDIDO
            $pedido = Pedido::create([
                'cliente_id' => $cliente->id,
                'categoria_id' => $categoria_id,
                'descricao' => $descricao,
                'foto' => $fotoPath,
                'endereco' => $endereco,
                'data' => now(),
                'status' => 'pendente',
                'prestador_id' => null,
                'servico_id' => null,
                'valor' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pedido publicado com sucesso!',
                'data' => $pedido
            ], 201);
        } catch (\Exception $e) {
            Log::error('❌ ERRO: ' . $e->getMessage());
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
        Log::info('🔵 PedidoController@meusPedidos - INICIADO');

        $cliente = $request->user();
        Log::info('Cliente ID: ' . ($cliente ? $cliente->id : 'null'));

        $pedidos = Pedido::where('cliente_id', $cliente->id)
            ->with(['categoria', 'prestador' => function ($q) {
                $q->select('id', 'nome', 'foto', 'telefone', 'media_avaliacao');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        Log::info('✅ Pedidos encontrados: ' . $pedidos->count());

        return response()->json([
            'success' => true,
            'data' => $pedidos->map(function ($pedido) {
                return $this->formatPedido($pedido);
            })
        ]);
    }

    /**
     * Atualizar status do pedido
     * PUT /api/admin/pedidos/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        Log::info('🔵 PedidoController@updateStatus - ID: ' . $id);
        // LOG COMPLETO DO QUE O FRONTEND ENVIA
        Log::info('========== CONTEÚDO COMPLETO DO REQUEST ==========');
        Log::info('POST params: ' . json_encode($request->post()));
        Log::info('REQUEST all: ' . json_encode($request->all()));
        Log::info('RAW CONTENT: ' . $request->getContent());
        Log::info('==================================================');
        $pedido = Pedido::find($id);

        if (!$pedido) {
            Log::warning('⚠️ Pedido não encontrado - ID: ' . $id);
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

            $pedido->load([
                'cliente:id,nome,foto,telefone',
                'prestador:id,nome,foto,telefone,media_avaliacao',
                'servico:id,nome,preco,duracao',
                'categoria:id,nome,icone,cor',
                'avaliacao'
            ]);

            Log::info('✅ Status atualizado para: ' . $request->status);

            return response()->json([
                'success' => true,
                'message' => 'Status atualizado com sucesso',
                'data' => $this->formatPedido($pedido)
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancelar pedido (admin)
     * DELETE /api/admin/pedidos/{id}/cancel
     */
    public function cancel($id)
    {
        Log::info('🔵 PedidoController@cancel - ID: ' . $id);

        $pedido = Pedido::find($id);

        if (!$pedido) {
            Log::warning('⚠️ Pedido não encontrado - ID: ' . $id);
            return response()->json([
                'success' => false,
                'error' => 'Pedido não encontrado'
            ], 404);
        }

        try {
            $pedido->status = 'cancelado';
            $pedido->save();

            $this->clearPedidoCache($id, $pedido->cliente_id, $pedido->prestador_id);

            Log::info('✅ Pedido cancelado - ID: ' . $id);

            return response()->json([
                'success' => true,
                'message' => 'Pedido cancelado com sucesso'
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Erro ao cancelar pedido: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao cancelar pedido: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Formatar pedido com casts corretos
     */
    private function formatPedido($pedido)
    {
        return [
            'id' => (int) $pedido->id,
            'status' => (string) $pedido->status,
            'descricao' => $pedido->descricao ? (string) $pedido->descricao : null,
            'foto' => $pedido->foto ? asset('storage/' . $pedido->foto) : null,
            'data' => $pedido->data ? $pedido->data->toISOString() : null,
            'endereco' => (string) $pedido->endereco,
            'observacoes' => $pedido->observacoes ? (string) $pedido->observacoes : null,
            'valor' => $pedido->valor ? (float) $pedido->valor : null,
            'created_at' => $pedido->created_at ? $pedido->created_at->toISOString() : null,
            'updated_at' => $pedido->updated_at ? $pedido->updated_at->toISOString() : null,

            'categoria' => $pedido->categoria ? [
                'id' => (int) $pedido->categoria->id,
                'nome' => (string) $pedido->categoria->nome,
                'icone' => (string) $pedido->categoria->icone,
                'cor' => (string) $pedido->categoria->cor,
            ] : null,

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
     * Limpar cache do pedido
     */
    private function clearPedidoCache($pedidoId, $clienteId, $prestadorId)
    {
        Cache::forget("pedido_detalhes_{$pedidoId}");

        for ($page = 1; $page <= 5; $page++) {
            Cache::forget("admin_pedidos_page_{$page}");
            $statuses = ['pendente', 'aceito', 'em_andamento', 'concluido', 'cancelado'];
            foreach ($statuses as $status) {
                Cache::forget("admin_pedidos_status_{$status}_page_{$page}");
            }
        }

        if ($clienteId) {
            $statuses = ['pendente', 'aceito', 'concluido', 'cancelado', null];
            foreach ($statuses as $status) {
                for ($page = 1; $page <= 5; $page++) {
                    $statusKey = $status ?: 'all';
                    Cache::forget("cliente_pedidos_{$clienteId}_{$statusKey}_{$page}");
                }
            }
        }

        if ($prestadorId) {
            $statuses = ['pendente', 'aceito', 'concluido', 'cancelado', null];
            foreach ($statuses as $status) {
                for ($page = 1; $page <= 5; $page++) {
                    $statusKey = $status ?: 'all';
                    Cache::forget("prestador_solicitacoes_{$prestadorId}_{$statusKey}_{$page}");
                }
            }
        }
    }
}
