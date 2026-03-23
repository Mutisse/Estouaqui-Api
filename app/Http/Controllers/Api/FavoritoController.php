<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FavoritoController extends Controller
{
    /**
     * Listar favoritos do cliente
     * GET /api/cliente/favoritos
     */
    public function index(Request $request)
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
    public function store(Request $request, $prestadorId)
    {
        $user = $request->user();

        // Verificar se prestador existe
        $prestador = \App\Models\User::where('tipo', 'prestador')->find($prestadorId);
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

        return response()->json([
            'success' => true,
            'message' => 'Prestador removido dos favoritos'
        ]);
    }

    /**
     * Verificar se prestador é favorito
     * GET /api/cliente/favoritos/{prestadorId}/check
     */
    public function check(Request $request, $prestadorId)
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
