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
     * Listar avaliações (admin) - COM CACHE E TOARRAY
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

        // ✅ GARANTIR QUE O PAGINATE RETORNA DADOS FORMATADOS
        return response()->json([
            'success' => true,
            'data' => $avaliacoes
        ]);
    }

    /**
     * Mostrar uma avaliação - COM CACHE E TOARRAY
     * GET /api/admin/avaliacoes/{id}
     */
    public function show($id)
    {
        $cacheKey = "avaliacao_{$id}";

        $avaliacao = Cache::remember($cacheKey, 3600, function() use ($id) {
            return Avaliacao::with([
                'cliente:id,nome,foto',
                'prestador:id,nome,foto,media_avaliacao,total_avaliacoes',
                'pedido:id,numero,status'
            ])->find($id);
        });

        if (!$avaliacao) {
            return response()->json([
                'success' => false,
                'error' => 'Avaliação não encontrada'
            ], 404);
        }

        // ✅ CONVERTER PARA ARRAY COM CASTS
        return response()->json([
            'success' => true,
            'data' => $this->formatAvaliacao($avaliacao)
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
     * Formatar avaliação com casts corretos
     */
    private function formatAvaliacao($avaliacao)
    {
        return [
            'id' => (int) $avaliacao->id,
            'nota' => (int) $avaliacao->nota,
            'comentario' => $avaliacao->comentario ? (string) $avaliacao->comentario : null,
            'categorias' => $avaliacao->categorias ? (array) $avaliacao->categorias : [],
            'created_at' => $avaliacao->created_at ? $avaliacao->created_at->toISOString() : null,
            'updated_at' => $avaliacao->updated_at ? $avaliacao->updated_at->toISOString() : null,

            // Cliente
            'cliente' => $avaliacao->cliente ? [
                'id' => (int) $avaliacao->cliente->id,
                'nome' => (string) $avaliacao->cliente->nome,
                'foto' => $avaliacao->cliente->foto ? asset('storage/' . $avaliacao->cliente->foto) : null,
            ] : null,

            // Prestador
            'prestador' => $avaliacao->prestador ? [
                'id' => (int) $avaliacao->prestador->id,
                'nome' => (string) $avaliacao->prestador->nome,
                'foto' => $avaliacao->prestador->foto ? asset('storage/' . $avaliacao->prestador->foto) : null,
                'media_avaliacao' => (float) ($avaliacao->prestador->media_avaliacao ?? 0),
                'total_avaliacoes' => (int) ($avaliacao->prestador->total_avaliacoes ?? 0),
            ] : null,

            // Pedido
            'pedido' => $avaliacao->pedido ? [
                'id' => (int) $avaliacao->pedido->id,
                'numero' => (string) $avaliacao->pedido->numero,
                'status' => (string) $avaliacao->pedido->status,
            ] : null,
        ];
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
            'total_avaliacoes' => (int) $total
        ]);
    }

    /**
     * Limpar cache de avaliações
     */
    private function clearAvaliacaoCache($prestadorId)
    {
        // Limpar cache do prestador
        Cache::forget("prestador_stats_{$prestadorId}");
        Cache::forget("prestador_avaliacoes_recentes_{$prestadorId}_5");
        Cache::forget("prestador_detalhes_{$prestadorId}");

        // Limpar cache de admin
        for ($page = 1; $page <= 5; $page++) {
            Cache::forget("admin_avaliacoes_page_{$page}");
        }

        // Limpar cache de páginas com filtros comuns
        $statuses = [1, 2, 3, 4, 5];
        foreach ($statuses as $nota) {
            for ($page = 1; $page <= 3; $page++) {
                Cache::forget("admin_avaliacoes_nota_{$nota}_page_{$page}");
            }
        }

        // Limpar cache de avaliações específicas por prestador
        for ($page = 1; $page <= 3; $page++) {
            Cache::forget("prestador_avaliacoes_{$prestadorId}_{$page}");
        }
    }
}
