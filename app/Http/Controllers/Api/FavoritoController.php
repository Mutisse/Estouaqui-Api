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
     * Listar favoritos do cliente - COM CACHE E TOARRAY
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
                ->get()
                ->map(function ($favorito) {
                    return [
                        'id' => (int) $favorito->id,
                        'created_at' => $favorito->created_at ? $favorito->created_at->toISOString() : null,
                        'prestador' => $favorito->prestador ? [
                            'id' => (int) $favorito->prestador->id,
                            'nome' => (string) $favorito->prestador->nome,
                            'foto' => $favorito->prestador->foto ? asset('storage/' . $favorito->prestador->foto) : null,
                            'telefone' => (string) $favorito->prestador->telefone,
                            'media_avaliacao' => (float) ($favorito->prestador->media_avaliacao ?? 0),
                            'profissao' => (string) ($favorito->prestador->profissao ?? 'Profissional'),
                            'ativo' => (bool) $favorito->prestador->ativo,
                        ] : null
                    ];
                })
                ->toArray(); // ✅ CONVERTER PARA ARRAY
        });

        return response()->json([
            'success' => true,
            'data' => $favoritos
        ]);
    }

    /**
     * Adicionar prestador aos favoritos - LIMPAR CACHE E RETORNAR ARRAY
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

            // ✅ CARREGAR RELACIONAMENTO
            $favorito->load('prestador:id,nome,foto,telefone,media_avaliacao,profissao,ativo');

            // Limpar cache
            $this->clearFavoritoCache($user->id);

            // ✅ CONVERTER PARA ARRAY COM CASTS
            return response()->json([
                'success' => true,
                'message' => 'Prestador adicionado aos favoritos',
                'data' => [
                    'id' => (int) $favorito->id,
                    'created_at' => $favorito->created_at ? $favorito->created_at->toISOString() : null,
                    'prestador' => $favorito->prestador ? [
                        'id' => (int) $favorito->prestador->id,
                        'nome' => (string) $favorito->prestador->nome,
                        'foto' => $favorito->prestador->foto ? asset('storage/' . $favorito->prestador->foto) : null,
                        'telefone' => (string) $favorito->prestador->telefone,
                        'media_avaliacao' => (float) ($favorito->prestador->media_avaliacao ?? 0),
                        'profissao' => (string) ($favorito->prestador->profissao ?? 'Profissional'),
                        'ativo' => (bool) $favorito->prestador->ativo,
                    ] : null
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao adicionar favorito: ' . $e->getMessage()
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
            'data' => ['is_favorito' => (bool) $isFavorito]
        ]);
    }

    /**
     * Limpar cache de favoritos
     */
    private function clearFavoritoCache($userId)
    {
        Cache::forget("cliente_favoritos_{$userId}");
        Cache::forget("dashboard_{$userId}");

        // Limpar caches de verificação de favoritos (padrão)
        // Usando padrão para limpar caches relacionados
        $keys = Cache::get("cliente_favorito_keys_{$userId}");
        if ($keys && is_array($keys)) {
            foreach ($keys as $key) {
                Cache::forget($key);
            }
        }
    }
}
