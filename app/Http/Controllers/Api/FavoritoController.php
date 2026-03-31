<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorito;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FavoritoController extends Controller
{
    /**
     * Listar favoritos do cliente - COM CACHE
     * GET /api/cliente/favoritos
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $cacheKey = "cliente_favoritos_{$user->id}";

        $favoritos = Cache::remember($cacheKey, 300, function() use ($user) {
            return Favorito::where('cliente_id', $user->id)
                ->with('prestador:id,nome,foto,telefone,media_avaliacao,profissao,ativo')
                ->orderBy('created_at', 'desc')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $favoritos
        ]);
    }

    /**
     * Adicionar prestador aos favoritos - LIMPAR CACHE
     * POST /api/cliente/favoritos/{prestadorId}
     */
    public function store(Request $request, $prestadorId)
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

            // Limpar cache
            $this->clearFavoritoCache($user->id);

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
     * Remover prestador dos favoritos - LIMPAR CACHE
     * DELETE /api/cliente/favoritos/{prestadorId}
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
                'error' => 'Prestador não está nos favoritos'
            ], 404);
        }

        $favorito->delete();

        // Limpar cache
        $this->clearFavoritoCache($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Prestador removido dos favoritos'
        ]);
    }

    /**
     * Verificar se prestador é favorito - COM CACHE
     * GET /api/cliente/favoritos/{prestadorId}/check
     */
    public function check(Request $request, $prestadorId)
    {
        $user = $request->user();
        $cacheKey = "cliente_favorito_check_{$user->id}_{$prestadorId}";

        $isFavorito = Cache::remember($cacheKey, 600, function() use ($user, $prestadorId) {
            return Favorito::where('cliente_id', $user->id)
                ->where('prestador_id', $prestadorId)
                ->exists();
        });

        return response()->json([
            'success' => true,
            'data' => ['is_favorito' => $isFavorito]
        ]);
    }

    /**
     * Limpar cache de favoritos
     */
    private function clearFavoritoCache($userId)
    {
        Cache::forget("cliente_favoritos_{$userId}");

        // Limpar cache de dashboard
        Cache::forget("dashboard_{$userId}");
    }
}
