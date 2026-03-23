<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Pedido;
use App\Models\Avaliacao;
use App\Models\Favorito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class ClienteController extends Controller
{
    // ==========================================
    // 1. REGISTRO DO CLIENTE
    // ==========================================

    /**
     * Registro de novo cliente
     * POST /api/register/cliente
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'telefone' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:6',
            'endereco' => 'nullable|string',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            $userData = [
                'nome' => $request->nome,
                'email' => $request->email,
                'telefone' => $request->telefone,
                'password' => Hash::make($request->password),
                'endereco' => $request->endereco,
                'tipo' => 'cliente',
            ];

            if ($request->hasFile('foto')) {
                $path = $request->file('foto')->store('fotos/clientes', 'public');
                $userData['foto'] = $path;
            }

            $user = User::create($userData);
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Cliente registado com sucesso!',
                'data' => [
                    'id' => $user->id,
                    'nome' => $user->nome,
                    'email' => $user->email,
                    'telefone' => $user->telefone,
                    'foto' => $user->foto ? asset('storage/' . $user->foto) : null,
                ],
                'token' => $token
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao registar cliente: ' . $e->getMessage()
            ], 500);
        }
    }


    // ==========================================
    // 2. PEDIDOS DO CLIENTE
    // ==========================================

    /**
     * Listar pedidos do cliente
     * GET /api/cliente/pedidos
     */
    public function pedidos(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status'); // ativo, concluido, cancelado

        $query = Pedido::where('cliente_id', $user->id);

        if ($status) {
            $query->where('status', $status);
        }

        $pedidos = $query->with('prestador')->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $pedidos
        ]);
    }

    /**
     * Detalhes de um pedido específico
     * GET /api/cliente/pedidos/{id}
     */
    public function showPedido(Request $request, $id)
    {
        $user = $request->user();
        $pedido = Pedido::where('cliente_id', $user->id)
            ->with(['prestador', 'servico', 'avaliacao'])
            ->find($id);

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
     * Criar novo pedido
     * POST /api/cliente/pedidos
     */
    public function createPedido(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'prestador_id' => 'required|exists:users,id',
            'servico_id' => 'required|exists:servicos,id',
            'data' => 'required|date|after:now',
            'endereco' => 'required|string',
            'observacoes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            $pedido = Pedido::create([
                'cliente_id' => $user->id,
                'prestador_id' => $request->prestador_id,
                'servico_id' => $request->servico_id,
                'data' => $request->data,
                'endereco' => $request->endereco,
                'observacoes' => $request->observacoes,
                'status' => 'pendente',
                'numero' => 'PED-' . strtoupper(uniqid()),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pedido criado com sucesso',
                'data' => $pedido
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar pedido'
            ], 500);
        }
    }

    /**
     * Cancelar pedido
     * PUT /api/cliente/pedidos/{id}/cancelar
     */
    public function cancelarPedido(Request $request, $id)
    {
        $user = $request->user();
        $pedido = Pedido::where('cliente_id', $user->id)->find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'error' => 'Pedido não encontrado'
            ], 404);
        }

        if ($pedido->status !== 'pendente') {
            return response()->json([
                'success' => false,
                'error' => 'Apenas pedidos pendentes podem ser cancelados'
            ], 422);
        }

        $pedido->status = 'cancelado';
        $pedido->save();

        return response()->json([
            'success' => true,
            'message' => 'Pedido cancelado com sucesso',
            'data' => $pedido
        ]);
    }


    // ==========================================
    // 3. AVALIAÇÕES DO CLIENTE
    // ==========================================

    /**
     * Listar avaliações do cliente
     * GET /api/cliente/avaliacoes
     */
    public function avaliacoes(Request $request)
    {
        $user = $request->user();

        $avaliacoes = Avaliacao::where('cliente_id', $user->id)
            ->with('prestador')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $avaliacoes
        ]);
    }

    /**
     * Criar avaliação para um prestador
     * POST /api/cliente/avaliacoes
     */
    public function createAvaliacao(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'prestador_id' => 'required|exists:users,id',
            'pedido_id' => 'required|exists:pedidos,id',
            'nota' => 'required|integer|min:1|max:5',
            'comentario' => 'nullable|string|max:500',
            'categorias' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        // Verificar se o pedido pertence ao cliente
        $pedido = Pedido::where('id', $request->pedido_id)
            ->where('cliente_id', $user->id)
            ->first();

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'error' => 'Pedido não encontrado'
            ], 404);
        }

        // Verificar se já avaliou este pedido
        $existe = Avaliacao::where('pedido_id', $request->pedido_id)
            ->where('cliente_id', $user->id)
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'error' => 'Este pedido já foi avaliado'
            ], 422);
        }

        try {
            $avaliacao = Avaliacao::create([
                'cliente_id' => $user->id,
                'prestador_id' => $request->prestador_id,
                'pedido_id' => $request->pedido_id,
                'nota' => $request->nota,
                'comentario' => $request->comentario,
                'categorias' => $request->categorias,
            ]);

            // Atualizar média do prestador
            $this->atualizarMediaPrestador($request->prestador_id);

            return response()->json([
                'success' => true,
                'message' => 'Avaliação enviada com sucesso!',
                'data' => $avaliacao
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao enviar avaliação'
            ], 500);
        }
    }

    /**
     * Atualizar avaliação
     * PUT /api/cliente/avaliacoes/{id}
     */
    public function updateAvaliacao(Request $request, $id)
    {
        $user = $request->user();
        $avaliacao = Avaliacao::where('cliente_id', $user->id)->find($id);

        if (!$avaliacao) {
            return response()->json([
                'success' => false,
                'error' => 'Avaliação não encontrada'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nota' => 'sometimes|integer|min:1|max:5',
            'comentario' => 'nullable|string|max:500',
            'categorias' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            if ($request->has('nota')) $avaliacao->nota = $request->nota;
            if ($request->has('comentario')) $avaliacao->comentario = $request->comentario;
            if ($request->has('categorias')) $avaliacao->categorias = $request->categorias;

            $avaliacao->save();

            // Atualizar média do prestador
            $this->atualizarMediaPrestador($avaliacao->prestador_id);

            return response()->json([
                'success' => true,
                'message' => 'Avaliação atualizada com sucesso',
                'data' => $avaliacao
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar avaliação'
            ], 500);
        }
    }

    /**
     * Deletar avaliação
     * DELETE /api/cliente/avaliacoes/{id}
     */
    public function deleteAvaliacao(Request $request, $id)
    {
        $user = $request->user();
        $avaliacao = Avaliacao::where('cliente_id', $user->id)->find($id);

        if (!$avaliacao) {
            return response()->json([
                'success' => false,
                'error' => 'Avaliação não encontrada'
            ], 404);
        }

        $prestadorId = $avaliacao->prestador_id;
        $avaliacao->delete();

        // Atualizar média do prestador
        $this->atualizarMediaPrestador($prestadorId);

        return response()->json([
            'success' => true,
            'message' => 'Avaliação removida com sucesso'
        ]);
    }

    /**
     * Verificar se pedido já foi avaliado
     * GET /api/cliente/pedidos/{id}/avaliacao
     */
    public function checkAvaliacao(Request $request, $pedidoId)
    {
        $user = $request->user();

        $existe = Avaliacao::where('pedido_id', $pedidoId)
            ->where('cliente_id', $user->id)
            ->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'avaliado' => $existe
            ]
        ]);
    }

    /**
     * Atualizar média do prestador
     */
    private function atualizarMediaPrestador($prestadorId)
    {
        $media = Avaliacao::where('prestador_id', $prestadorId)
            ->avg('nota');

        User::where('id', $prestadorId)->update([
            'media_avaliacao' => round($media, 1),
            'total_avaliacoes' => Avaliacao::where('prestador_id', $prestadorId)->count()
        ]);
    }


    // ==========================================
    // 4. FAVORITOS
    // ==========================================

    /**
     * Listar favoritos do cliente
     * GET /api/cliente/favoritos
     */
    public function favoritos(Request $request)
    {
        $user = $request->user();

        $favoritos = Favorito::where('cliente_id', $user->id)
            ->with('prestador')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $favoritos
        ]);
    }

    /**
     * Adicionar prestador aos favoritos
     * POST /api/cliente/favoritos/{prestadorId}
     */
    public function addFavorito(Request $request, $prestadorId)
    {
        $user = $request->user();

        // Verificar se prestador existe
        $prestador = User::where('tipo', 'prestador')->find($prestadorId);
        if (!$prestador) {
            return response()->json([
                'success' => false,
                'error' => 'Prestador não encontrado'
            ], 404);
        }

        // Verificar se já é favorito
        $existe = Favorito::where('cliente_id', $user->id)
            ->where('prestador_id', $prestadorId)
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'error' => 'Prestador já está nos favoritos'
            ], 422);
        }

        try {
            $favorito = Favorito::create([
                'cliente_id' => $user->id,
                'prestador_id' => $prestadorId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Prestador adicionado aos favoritos',
                'data' => $favorito
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao adicionar favorito'
            ], 500);
        }
    }

    /**
     * Remover prestador dos favoritos
     * DELETE /api/cliente/favoritos/{prestadorId}
     */
    public function removeFavorito(Request $request, $prestadorId)
    {
        $user = $request->user();

        $favorito = Favorito::where('cliente_id', $user->id)
            ->where('prestador_id', $prestadorId)
            ->first();

        if (!$favorito) {
            return response()->json([
                'success' => false,
                'error' => 'Prestador não está nos favoritos'
            ], 404);
        }

        $favorito->delete();

        return response()->json([
            'success' => true,
            'message' => 'Prestador removido dos favoritos'
        ]);
    }

    /**
     * Verificar se prestador é favorito
     * GET /api/cliente/favoritos/{prestadorId}/check
     */
    public function checkFavorito(Request $request, $prestadorId)
    {
        $user = $request->user();

        $isFavorito = Favorito::where('cliente_id', $user->id)
            ->where('prestador_id', $prestadorId)
            ->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'is_favorito' => $isFavorito
            ]
        ]);
    }
}
