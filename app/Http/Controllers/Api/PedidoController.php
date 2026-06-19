<?php
// app/Http/Controllers/Api/PedidoController.php

namespace App\Http\Controllers\Api;

use App\Models\Pedido;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PedidoController extends BaseController
{
    /**
     * Listar todos os pedidos do cliente
     * GET /api/cliente/pedidos
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $pedidos = Pedido::where('cliente_id', $user->id)
            ->with(['categoria', 'prestador'])
            ->orderBy('created_at', 'desc')
            ->get();

        // ✅ CORRIGIR URL DA FOTO
        $pedidos->each(function ($pedido) {
            if ($pedido->foto) {
                $pedido->foto = asset('storage/' . $pedido->foto);
            }
        });

        return response()->json([
            'success' => true,
            'data' => $pedidos
        ]);
    }

    /**
     * Mostrar detalhes de um pedido específico
     * GET /api/cliente/pedidos/{id}
     */
    public function show($id)
    {
        $pedido = Pedido::with(['cliente', 'prestador', 'categoria'])
            ->find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não encontrado'
            ], 404);
        }

        // ✅ CORRIGIR URL DA FOTO DO PEDIDO
        if ($pedido->foto) {
            $pedido->foto = asset('storage/' . $pedido->foto);
        }

        // ✅ CORRIGIR URL DA FOTO DO CLIENTE
        if ($pedido->cliente && $pedido->cliente->foto) {
            $pedido->cliente->foto = asset('storage/' . $pedido->cliente->foto);
        }

        return response()->json([
            'success' => true,
            'data' => $pedido
        ]);
    }

    /**
     * Criar um novo pedido
     * POST /api/cliente/pedidos
     * ✅ ATUALIZADO: Suporte a latitude e longitude
     */
    public function store(Request $request)
    {
        // ✅ VALIDAÇÃO COM LATITUDE E LONGITUDE
        $request->validate([
            'categoria_id' => 'required|exists:categorias,id',
            'descricao' => 'required|string|min:10',
            'endereco' => 'required|string',
            'foto' => 'nullable|image|max:5120',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $user = $request->user();

        $dados = [
            'cliente_id' => $user->id,
            'categoria_id' => $request->categoria_id,
            'descricao' => $request->descricao,
            'endereco' => $request->endereco,
            'status' => 'pendente',
        ];

        // ✅ SALVAR LATITUDE E LONGITUDE
        if ($request->has('latitude') && $request->latitude !== null) {
            $dados['latitude'] = $request->latitude;
        }
        if ($request->has('longitude') && $request->longitude !== null) {
            $dados['longitude'] = $request->longitude;
        }

        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('pedidos', 'public');
            $dados['foto'] = $path;
        }

        $pedido = Pedido::create($dados);

        // ✅ LOG PARA VERIFICAR SE AS COORDENADAS FORAM SALVAS
        Log::info('Pedido criado', [
            'pedido_id' => $pedido->id,
            'latitude' => $pedido->latitude,
            'longitude' => $pedido->longitude,
        ]);

        NotificationService::send('pedido.criado', $user->id, [
            'numero' => $pedido->numero,
            'pedido_id' => $pedido->id,
            'categoria' => $pedido->categoria->nome ?? 'serviço'
        ]);

        $prestadoresDisponiveis = User::prestadores()
            ->disponiveis()
            ->whereHas('categorias', function ($query) use ($pedido) {
                $query->where('categoria_id', $pedido->categoria_id);
            })
            ->get();

        if ($prestadoresDisponiveis->count() > 0) {
            NotificationService::sendToMany(
                'pedido.novo_para_prestador',
                $prestadoresDisponiveis->pluck('id')->toArray(),
                [
                    'numero' => $pedido->numero,
                    'categoria' => $pedido->categoria->nome ?? 'serviço',
                    'descricao' => substr($pedido->descricao, 0, 50),
                    // ✅ ENVIAR LOCALIZAÇÃO NAS NOTIFICAÇÕES
                    'latitude' => $pedido->latitude,
                    'longitude' => $pedido->longitude,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Pedido criado com sucesso!',
            'data' => $pedido
        ], 201);
    }

    /**
     * Atualizar status do pedido
     * PATCH /api/cliente/pedidos/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pendente,aceito,em_andamento,concluido,cancelado',
            'prestador_id' => 'required_if:status,aceito|exists:users,id',
            'motivo' => 'required_if:status,cancelado|string|min:3'
        ]);

        $pedido = Pedido::with(['cliente', 'prestador', 'categoria'])->find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não encontrado'
            ], 404);
        }

        if ($request->status === 'aceito' && $request->has('prestador_id')) {
            $pedido->prestador_id = $request->prestador_id;
        }

        $pedido->status = $request->status;

        if ($request->status === 'concluido') {
            $pedido->concluido_em = now();
        }

        $pedido->save();

        $eventoMap = [
            'aceito' => 'pedido.aceito',
            'em_andamento' => 'pedido.em_andamento',
            'concluido' => 'pedido.concluido',
            'cancelado' => 'pedido.cancelado',
        ];

        if (isset($eventoMap[$request->status])) {
            $dadosNotificacao = [
                'numero' => $pedido->numero,
                'pedido_id' => $pedido->id,
            ];

            if ($pedido->prestador) {
                $dadosNotificacao['prestador_nome'] = $pedido->prestador->nome;
            }

            if ($request->status === 'cancelado') {
                $dadosNotificacao['motivo'] = $request->input('motivo', 'não especificado');
            }

            NotificationService::send($eventoMap[$request->status], $pedido->cliente_id, $dadosNotificacao);
        }

        if ($request->status === 'aceito' && $pedido->prestador_id) {
            NotificationService::send('pedido.aceito_prestador', $pedido->prestador_id, [
                'numero' => $pedido->numero,
                'cliente_nome' => $pedido->cliente->nome,
                'endereco' => $pedido->endereco
            ]);
        }

        if ($request->status === 'em_andamento' && $pedido->prestador_id) {
            NotificationService::send('pedido.em_andamento_prestador', $pedido->prestador_id, [
                'numero' => $pedido->numero,
                'cliente_nome' => $pedido->cliente->nome
            ]);
        }

        if ($request->status === 'concluido' && $pedido->prestador_id) {
            NotificationService::send('pedido.concluido_prestador', $pedido->prestador_id, [
                'numero' => $pedido->numero,
                'cliente_nome' => $pedido->cliente->nome
            ]);
        }

        if ($request->status === 'cancelado' && $pedido->prestador_id) {
            NotificationService::send('pedido.cancelado_prestador', $pedido->prestador_id, [
                'numero' => $pedido->numero,
                'motivo' => $request->input('motivo', 'cliente cancelou')
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Status atualizado com sucesso!',
            'data' => $pedido
        ]);
    }

    /**
     * Deletar um pedido (apenas pendentes)
     * DELETE /api/cliente/pedidos/{id}
     */
    public function destroy(Request $request, $id)
    {
        $pedido = Pedido::where('cliente_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não encontrado'
            ], 404);
        }

        if ($pedido->status !== 'pendente') {
            return response()->json([
                'success' => false,
                'message' => 'Apenas pedidos pendentes podem ser removidos'
            ], 422);
        }

        // ✅ REMOVER FOTO AO DELETAR
        if ($pedido->foto) {
            Storage::disk('public')->delete($pedido->foto);
        }

        $pedido->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pedido removido com sucesso'
        ]);
    }

    /**
     * Listar pedidos de um prestador (para prestadores)
     * GET /api/prestador/pedidos
     */
    public function pedidosPrestador(Request $request)
    {
        $user = $request->user();

        if (!$user->isPrestador()) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso apenas para prestadores'
            ], 403);
        }

        $pedidos = Pedido::where('prestador_id', $user->id)
            ->with(['cliente', 'categoria'])
            ->orderBy('created_at', 'desc')
            ->get();

        // ✅ CORRIGIR URL DA FOTO
        $pedidos->each(function ($pedido) {
            if ($pedido->foto) {
                $pedido->foto = asset('storage/' . $pedido->foto);
            }
        });

        return response()->json([
            'success' => true,
            'data' => $pedidos
        ]);
    }

    /**
     * Listar pedidos disponíveis para prestadores (status pendente)
     * GET /api/prestador/pedidos/disponiveis
     */
    public function pedidosDisponiveis(Request $request)
    {
        $user = $request->user();

        if (!$user->isPrestador()) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso apenas para prestadores'
            ], 403);
        }

        $categoriasPrestador = $user->categorias()->pluck('categorias.id');

        $pedidos = Pedido::where('status', 'pendente')
            ->whereIn('categoria_id', $categoriasPrestador)
            ->with(['cliente', 'categoria'])
            ->orderBy('created_at', 'desc')
            ->get();

        // ✅ CORRIGIR URL DA FOTO E INCLUIR LOCALIZAÇÃO
        $pedidos->each(function ($pedido) {
            if ($pedido->foto) {
                $pedido->foto = asset('storage/' . $pedido->foto);
            }
        });

        return response()->json([
            'success' => true,
            'data' => $pedidos,
            'total' => $pedidos->count()
        ]);
    }

    /**
     * Aceitar um pedido (prestador aceita)
     * POST /api/prestador/pedidos/{id}/aceitar
     */
    public function aceitarPedido(Request $request, $id)
    {
        $user = $request->user();

        if (!$user->isPrestador()) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas prestadores podem aceitar pedidos'
            ], 403);
        }

        $pedido = Pedido::with(['cliente', 'categoria'])->find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não encontrado'
            ], 404);
        }

        if ($pedido->status !== 'pendente') {
            return response()->json([
                'success' => false,
                'message' => 'Este pedido não está mais disponível'
            ], 422);
        }

        $temCategoria = $user->categorias()->where('categoria_id', $pedido->categoria_id)->exists();

        if (!$temCategoria) {
            return response()->json([
                'success' => false,
                'message' => 'Você não está habilitado para esta categoria'
            ], 403);
        }

        $pedido->prestador_id = $user->id;
        $pedido->status = 'aceito';
        $pedido->save();

        NotificationService::send('pedido.aceito', $pedido->cliente_id, [
            'numero' => $pedido->numero,
            'pedido_id' => $pedido->id,
            'prestador_nome' => $user->nome
        ]);

        NotificationService::send('pedido.aceito_prestador', $user->id, [
            'numero' => $pedido->numero,
            'cliente_nome' => $pedido->cliente->nome,
            'endereco' => $pedido->endereco
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pedido aceito com sucesso!',
            'data' => $pedido
        ]);
    }

    /**
     * Rejeitar um pedido (prestador recusa)
     * POST /api/prestador/pedidos/{id}/rejeitar
     */
    public function rejeitarPedido(Request $request, $id)
    {
        $user = $request->user();

        if (!$user->isPrestador()) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas prestadores podem rejeitar pedidos'
            ], 403);
        }

        $pedido = Pedido::find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pedido rejeitado'
        ]);
    }

    /**
     * Buscar o cliente que fez o pedido
     * GET /api/pedidos/{id}/cliente
     */
    public function getClienteDoPedido($id)
    {
        $pedido = Pedido::find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não encontrado'
            ], 404);
        }

        $cliente = $pedido->cliente;

        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente não encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $cliente->id,
                'nome' => $cliente->nome,
                'email' => $cliente->email,
                'telefone' => $cliente->telefone,
                'foto' => $cliente->foto ? asset('storage/' . $cliente->foto) : null,
            ]
        ]);
    }

    /**
     * Buscar histórico de status do pedido
     * GET /api/pedidos/{id}/historico
     */
    public function historico($id)
    {
        $pedido = Pedido::find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não encontrado'
            ], 404);
        }

        $historico = [
            [
                'id' => 1,
                'pedido_id' => $pedido->id,
                'status' => $pedido->status,
                'status_label' => $this->getStatusLabel($pedido->status),
                'observacao' => 'Status atual',
                'created_at' => $pedido->updated_at ?? $pedido->created_at,
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $historico
        ]);
    }

    /**
     * Retorna o label do status
     */
    private function getStatusLabel($status)
    {
        $labels = [
            'pendente' => 'Pendente',
            'aceito' => 'Aceito',
            'em_andamento' => 'Em Andamento',
            'concluido' => 'Concluído',
            'cancelado' => 'Cancelado',
        ];
        return $labels[$status] ?? $status;
    }
}
