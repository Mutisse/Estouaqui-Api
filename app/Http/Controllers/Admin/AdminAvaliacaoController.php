<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Avaliacao;
use App\Models\User;
use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAvaliacaoController extends Controller
{
    /**
     * Listar todas as avaliações com paginação e filtros
     * GET /admin/avaliacoes
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search');
            $nota = $request->input('nota');
            $status = $request->input('status');
            $dataInicio = $request->input('data_inicio');
            $dataFim = $request->input('data_fim');

            $query = Avaliacao::with(['cliente', 'prestador', 'pedido']);

            // Aplicar filtros
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('cliente', function ($q2) use ($search) {
                        $q2->where('nome', 'like', "%{$search}%")
                           ->orWhere('email', 'like', "%{$search}%");
                    })->orWhereHas('prestador', function ($q2) use ($search) {
                        $q2->where('nome', 'like', "%{$search}%")
                           ->orWhere('email', 'like', "%{$search}%")
                           ->orWhere('profissao', 'like', "%{$search}%");
                    });
                });
            }

            if ($nota && $nota !== '') {
                $query->where('nota', $nota);
            }

            if ($status && $status !== '') {
                $query->where('status', $status);
            }

            if ($dataInicio) {
                $query->whereDate('created_at', '>=', $dataInicio);
            }

            if ($dataFim) {
                $query->whereDate('created_at', '<=', $dataFim);
            }

            $avaliacoes = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $avaliacoes->items(),
                'current_page' => $avaliacoes->currentPage(),
                'last_page' => $avaliacoes->lastPage(),
                'per_page' => $avaliacoes->perPage(),
                'total' => $avaliacoes->total(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar avaliações: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar uma avaliação específica
     * GET /admin/avaliacoes/{id}
     */
    public function show($id)
    {
        try {
            $avaliacao = Avaliacao::with(['cliente', 'prestador', 'pedido'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $avaliacao
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Avaliação não encontrada'
            ], 404);
        }
    }

    /**
     * Aprovar uma avaliação
     * PUT /admin/avaliacoes/{id}/aprovar
     */
    public function aprovar($id)
    {
        try {
            $avaliacao = Avaliacao::findOrFail($id);

            if ($avaliacao->status !== 'pendente') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta avaliação não está pendente'
                ], 422);
            }

            $avaliacao->status = 'aprovada';
            $avaliacao->save();

            // Atualizar média do prestador
            $this->atualizarMediaPrestador($avaliacao->prestador_id);

            return response()->json([
                'success' => true,
                'data' => $avaliacao,
                'message' => 'Avaliação aprovada com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao aprovar avaliação: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rejeitar uma avaliação
     * PUT /admin/avaliacoes/{id}/rejeitar
     */
    public function rejeitar(Request $request, $id)
    {
        try {
            $avaliacao = Avaliacao::findOrFail($id);

            if ($avaliacao->status !== 'pendente') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta avaliação não está pendente'
                ], 422);
            }

            $avaliacao->status = 'rejeitada';
            $avaliacao->save();

            return response()->json([
                'success' => true,
                'data' => $avaliacao,
                'message' => 'Avaliação rejeitada com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao rejeitar avaliação: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar uma avaliação
     * DELETE /admin/avaliacoes/{id}
     */
    public function destroy($id)
    {
        try {
            $avaliacao = Avaliacao::findOrFail($id);
            $prestadorId = $avaliacao->prestador_id;

            $avaliacao->delete();

            // Atualizar média do prestador se a avaliação era aprovada
            if ($avaliacao->status === 'aprovada') {
                $this->atualizarMediaPrestador($prestadorId);
            }

            return response()->json([
                'success' => true,
                'message' => 'Avaliação excluída com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir avaliação: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estatísticas de avaliações
     * GET /admin/avaliacoes/estatisticas
     */
    public function estatisticas()
    {
        try {
            $total = Avaliacao::count();

            $porNota = [
                'nota_1' => Avaliacao::where('nota', 1)->where('status', 'aprovada')->count(),
                'nota_2' => Avaliacao::where('nota', 2)->where('status', 'aprovada')->count(),
                'nota_3' => Avaliacao::where('nota', 3)->where('status', 'aprovada')->count(),
                'nota_4' => Avaliacao::where('nota', 4)->where('status', 'aprovada')->count(),
                'nota_5' => Avaliacao::where('nota', 5)->where('status', 'aprovada')->count(),
            ];

            $somaNotas = ($porNota['nota_1'] * 1) + ($porNota['nota_2'] * 2) +
                         ($porNota['nota_3'] * 3) + ($porNota['nota_4'] * 4) +
                         ($porNota['nota_5'] * 5);

            $totalAprovadas = array_sum($porNota);
            $mediaGlobal = $totalAprovadas > 0 ? round($somaNotas / $totalAprovadas, 2) : 0;

            // Top prestadores
            $topPrestadores = User::where('tipo', 'prestador')
                ->withCount(['avaliacoes as total_avaliacoes' => function ($query) {
                    $query->where('status', 'aprovada');
                }])
                ->withAvg(['avaliacoes as media' => function ($query) {
                    $query->where('status', 'aprovada');
                }], 'nota')
                ->having('total_avaliacoes', '>', 0)
                ->orderBy('media', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($prestador) {
                    return [
                        'id' => $prestador->id,
                        'nome' => $prestador->nome,
                        'profissao' => $prestador->profissao,
                        'media' => round($prestador->media ?? 0, 2),
                        'total_avaliacoes' => $prestador->total_avaliacoes ?? 0,
                    ];
                });

            // Avaliações por mês (últimos 12 meses)
            $avaliacoesPorMes = Avaliacao::where('status', 'aprovada')
                ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as mes"), DB::raw('count(*) as total'))
                ->where('created_at', '>=', now()->subMonths(11))
                ->groupBy('mes')
                ->orderBy('mes', 'asc')
                ->get()
                ->map(function ($item) {
                    $date = \Carbon\Carbon::createFromFormat('Y-m', $item->mes);
                    return [
                        'mes' => $date->format('M Y'),
                        'total' => $item->total,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'media_global' => $mediaGlobal,
                    'por_nota' => $porNota,
                    'top_prestadores' => $topPrestadores,
                    'avaliacoes_por_mes' => $avaliacoesPorMes,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar estatísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar média do prestador
     */
    private function atualizarMediaPrestador($prestadorId)
    {
        $media = Avaliacao::where('prestador_id', $prestadorId)
            ->where('status', 'aprovada')
            ->avg('nota');

        $total = Avaliacao::where('prestador_id', $prestadorId)
            ->where('status', 'aprovada')
            ->count();

        User::where('id', $prestadorId)->update([
            'media_avaliacao' => round($media ?? 0, 2),
            'total_avaliacoes' => $total,
        ]);
    }
}
