<?php
// app/Http/Controllers/Api/FavoritoController.php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Favorito;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class FavoritoController extends BaseController
{
    /**
     * Listar todos os favoritos do usuário autenticado
     * GET /api/favoritos
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $favoritos = Favorito::where('cliente_id', $user->id)
            ->with('prestador')
            ->orderBy('created_at', 'desc')
            ->get();

        // Formatar dados dos prestadores
        $favoritos->each(function ($favorito) {
            if ($favorito->prestador) {
                $favorito->prestador->foto = $favorito->prestador->foto
                    ? asset('storage/' . $favorito->prestador->foto)
                    : null;
                $favorito->prestador->media_avaliacao = (float) $favorito->prestador->media_avaliacao;
            }
        });

        return response()->json([
            'success' => true,
            'data' => $favoritos,
            'count' => $favoritos->count()
        ]);
    }

    /**
     * Adicionar prestador aos favoritos
     * POST /api/favoritos
     */
    public function store(Request $request)
    {
        $request->validate([
            'prestador_id' => 'required|exists:users,id'
        ]);

        $user = $request->user();

        // Verificar se o usuário é cliente
        if (!$user->isCliente()) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas clientes podem adicionar favoritos'
            ], 403);
        }

        // Verificar se o prestador existe e é realmente um prestador
        $prestador = User::prestadores()->find($request->prestador_id);

        if (!$prestador) {
            return response()->json([
                'success' => false,
                'message' => 'Prestador não encontrado'
            ], 404);
        }

        // Verificar se já é favorito
        $exists = Favorito::where('cliente_id', $user->id)
            ->where('prestador_id', $request->prestador_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Este prestador já está nos seus favoritos'
            ], 409);
        }

        // Criar favorito
        $favorito = Favorito::create([
            'cliente_id' => $user->id,
            'prestador_id' => $request->prestador_id
        ]);

        // Carregar relacionamento
        $favorito->load('prestador');

        // 🔔 NOTIFICAÇÃO: Prestador foi favoritado
        NotificationService::send('favorito.adicionado', $prestador->id, [
            'cliente_nome' => $user->nome,
            'cliente_id' => $user->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Prestador adicionado aos favoritos',
            'data' => $favorito
        ], 201);
    }

    /**
     * Remover prestador dos favoritos
     * DELETE /api/favoritos/{prestadorId}
     */
    public function destroy(Request $request, $prestadorId)
    {
        $user = $request->user();

        $favorito = Favorito::where('cliente_id', $user->id)
            ->where('prestador_id', $prestadorId)
            ->first();

        if (!$favorito) {
            return response()->json([
                'success' => false,
                'message' => 'Prestador não encontrado nos favoritos'
            ], 404);
        }

        $prestadorNome = $favorito->prestador->nome ?? 'Prestador';
        $favorito->delete();

        // 🔔 NOTIFICAÇÃO: Prestador foi removido dos favoritos (opcional)
        // NotificationService::send('favorito.removido', $prestadorId, [
        //     'cliente_nome' => $user->nome
        // ]);

        return response()->json([
            'success' => true,
            'message' => "{$prestadorNome} removido dos favoritos"
        ]);
    }

    /**
     * Verificar se um prestador está nos favoritos
     * GET /api/favoritos/check/{prestadorId}
     */
    public function check(Request $request, $prestadorId)
    {
        $user = $request->user();

        $isFavorito = Favorito::where('cliente_id', $user->id)
            ->where('prestador_id', $prestadorId)
            ->exists();

        return response()->json([
            'success' => true,
            'is_favorito' => $isFavorito
        ]);
    }

    /**
     * Remover todos os favoritos de uma só vez
     * DELETE /api/favoritos/limpar-todos
     */
    public function limparTodos(Request $request)
    {
        $user = $request->user();

        $deleted = Favorito::where('cliente_id', $user->id)->delete();

        return response()->json([
            'success' => true,
            'message' => "{$deleted} favorito(s) removido(s)",
            'deleted_count' => $deleted
        ]);
    }

    /**
     * Buscar prestadores favoritos com filtros
     * GET /api/favoritos/prestadores
     */
    public function prestadoresFavoritos(Request $request)
    {
        $user = $request->user();

        $query = $user->prestadoresFavoritos()
            ->with('categorias');

        // Filtrar por categoria
        if ($request->has('categoria_id')) {
            $query->whereHas('categorias', function ($q) use ($request) {
                $q->where('categoria_id', $request->categoria_id);
            });
        }

        // Filtrar por disponibilidade
        if ($request->has('disponivel')) {
            $query->where('disponivel', $request->boolean('disponivel'));
        }

        // Ordenação
        $orderBy = $request->get('order_by', 'created_at');
        $orderDir = $request->get('order_dir', 'desc');
        $query->orderBy($orderBy, $orderDir);

        $prestadores = $query->paginate($request->get('per_page', 15));

        // Formatar dados
        $prestadores->getCollection()->each(function ($prestador) {
            $prestador->foto = $prestador->foto ? asset('storage/' . $prestador->foto) : null;
            $prestador->media_avaliacao = (float) $prestador->media_avaliacao;
        });

        return response()->json([
            'success' => true,
            'data' => $prestadores,
            'total' => $prestadores->total()
        ]);
    }

    /**
     * Contar quantos favoritos o usuário tem
     * GET /api/favoritos/count
     */
    public function count(Request $request)
    {
        $user = $request->user();

        $count = Favorito::where('cliente_id', $user->id)->count();

        return response()->json([
            'success' => true,
            'count' => $count
        ]);
    }
}
