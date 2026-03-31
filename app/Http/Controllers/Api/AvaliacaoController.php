<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Avaliacao;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AvaliacaoController extends Controller
{
    /**
     * Listar avaliações (admin) - COM CACHE
     * GET /api/admin/avaliacoes
     */
    public function index(Request $request)
    {
        $cacheKey = 'admin_avaliacoes_' . md5($request->fullUrl());

        $avaliacoes = Cache::remember($cacheKey, 300, function() use ($request) {
            $query = Avaliacao::with(['cliente:id,nome,foto', 'prestador:id,nome,foto', 'pedido:id,numero,status']);

            if ($request->has('prestador_id')) {
                $query->where('prestador_id', $request->prestador_id);
            }

            if ($request->has('nota')) {
                $query->where('nota', $request->nota);
            }

            return $query->orderBy('created_at', 'desc')->paginate(20);
        });

        return response()->json([
            'success' => true,
            'data' => $avaliacoes
        ]);
    }

    /**
     * Mostrar uma avaliação - COM CACHE
     * GET /api/admin/avaliacoes/{id}
     */
    public function show($id)
    {
        $cacheKey = "avaliacao_{$id}";

        $avaliacao = Cache::remember($cacheKey, 3600, function() use ($id) {
            return Avaliacao::with(['cliente:id,nome,foto', 'prestador:id,nome,foto', 'pedido:id,numero,status'])->find($id);
        });

        if (!$avaliacao) {
            return response()->json([
                'success' => false,
                'error' => 'Avaliação não encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $avaliacao
        ]);
    }

    /**
     * Deletar avaliação (admin) - LIMPAR CACHE
     * DELETE /api/admin/avaliacoes/{id}
     */
    public function destroy($id)
    {
        $avaliacao = Avaliacao::find($id);

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

        // Limpar cache
        $this->clearAvaliacaoCache($prestadorId);

        return response()->json([
            'success' => true,
            'message' => 'Avaliação removida com sucesso'
        ]);
    }

    /**
     * Atualizar média do prestador
     */
    private function atualizarMediaPrestador($prestadorId)
    {
        $media = Avaliacao::where('prestador_id', $prestadorId)->avg('nota');
        $total = Avaliacao::where('prestador_id', $prestadorId)->count();

        User::where('id', $prestadorId)->update([
            'media_avaliacao' => round($media ?? 0, 1),
            'total_avaliacoes' => $total
        ]);
    }

    /**
     * Limpar cache de avaliações
     */
    private function clearAvaliacaoCache($prestadorId)
    {
        Cache::forget("prestador_stats_{$prestadorId}");
        Cache::forget("prestador_avaliacoes_recentes_{$prestadorId}_5");

        for ($page = 1; $page <= 5; $page++) {
            Cache::forget("admin_avaliacoes_page_{$page}");
        }
    }
}
