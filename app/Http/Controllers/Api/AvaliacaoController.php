<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Avaliacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AvaliacaoController extends Controller
{
    /**
     * Listar avaliações (admin)
     * GET /api/admin/avaliacoes
     */
    public function index(Request $request)
    {
        $query = Avaliacao::with(['cliente', 'prestador', 'pedido']);

        if ($request->has('prestador_id')) {
            $query->where('prestador_id', $request->prestador_id);
        }

        if ($request->has('nota')) {
            $query->where('nota', $request->nota);
        }

        $avaliacoes = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $avaliacoes
        ]);
    }

    /**
     * Mostrar uma avaliação
     * GET /api/admin/avaliacoes/{id}
     */
    public function show($id)
    {
        $avaliacao = Avaliacao::with(['cliente', 'prestador', 'pedido'])->find($id);

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
     * Deletar avaliação (admin)
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

        \App\Models\User::where('id', $prestadorId)->update([
            'media_avaliacao' => round($media ?? 0, 1),
            'total_avaliacoes' => Avaliacao::where('prestador_id', $prestadorId)->count()
        ]);
    }
}
