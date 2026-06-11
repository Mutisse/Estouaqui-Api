<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\User;
use App\Models\Avaliacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class AdminRelatorioController extends Controller
{
    /**
     * GET /admin/relatorios/pedidos
     * Relatório de pedidos
     */
    public function pedidos(Request $request)
    {
        try {
            $dataInicio = $request->input('data_inicio', Carbon::now()->startOfMonth()->toDateString());
            $dataFim = $request->input('data_fim', Carbon::now()->toDateString());
            $categoriaId = $request->input('categoria_id');
            $prestadorId = $request->input('prestador_id');

            $query = Pedido::whereBetween('pedidos.created_at', [$dataInicio, $dataFim . ' 23:59:59']);

            if ($categoriaId) {
                $query->where('pedidos.categoria_id', $categoriaId);
            }
            if ($prestadorId) {
                $query->where('pedidos.prestador_id', $prestadorId);
            }

            $total = $query->count();
            $valorTotal = $query->sum('pedidos.valor');
            $valorMedio = $total > 0 ? $valorTotal / $total : 0;

            $porStatus = [
                'pendente' => (clone $query)->where('pedidos.status', 'pendente')->count(),
                'aceito' => (clone $query)->where('pedidos.status', 'aceito')->count(),
                'em_andamento' => (clone $query)->where('pedidos.status', 'em_andamento')->count(),
                'concluido' => (clone $query)->where('pedidos.status', 'concluido')->count(),
                'cancelado' => (clone $query)->where('pedidos.status', 'cancelado')->count(),
            ];

            $pedidosPorDia = (clone $query)
                ->select(DB::raw('DATE(pedidos.created_at) as data'), DB::raw('count(*) as total'))
                ->groupBy('data')
                ->orderBy('data', 'asc')
                ->get();

            $pedidosPorCategoria = (clone $query)
                ->join('categorias', 'pedidos.categoria_id', '=', 'categorias.id')
                ->select('categorias.nome', DB::raw('count(*) as total'), DB::raw('SUM(pedidos.valor) as valor'))
                ->groupBy('categorias.id', 'categorias.nome')
                ->get();

            $pedidosPorPrestador = (clone $query)
                ->join('users', 'pedidos.prestador_id', '=', 'users.id')
                ->select('users.nome', DB::raw('count(*) as total'), DB::raw('SUM(pedidos.valor) as valor'))
                ->whereNotNull('pedidos.prestador_id')
                ->where('users.tipo', 'prestador')
                ->groupBy('users.id', 'users.nome')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get();

            $topClientes = (clone $query)
                ->join('users', 'pedidos.cliente_id', '=', 'users.id')
                ->select('users.nome', DB::raw('count(*) as pedidos'), DB::raw('SUM(pedidos.valor) as valor_total'))
                ->where('users.tipo', 'cliente')
                ->groupBy('users.id', 'users.nome')
                ->orderBy('valor_total', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'por_status' => $porStatus,
                    'valor_total' => (float)$valorTotal,
                    'valor_medio' => round($valorMedio, 2),
                    'pedidos_por_dia' => $pedidosPorDia,
                    'pedidos_por_categoria' => $pedidosPorCategoria,
                    'pedidos_por_prestador' => $pedidosPorPrestador,
                    'top_clientes' => $topClientes,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar relatório: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /admin/relatorios/financeiro
     * Relatório financeiro
     */
    public function financeiro(Request $request)
    {
        try {
            $dataInicio = $request->input('data_inicio', Carbon::now()->startOfMonth()->toDateString());
            $dataFim = $request->input('data_fim', Carbon::now()->toDateString());

            $query = Pedido::whereBetween('pedidos.created_at', [$dataInicio, $dataFim . ' 23:59:59'])
                ->where('pedidos.status', 'concluido');

            $faturamentoTotal = Pedido::where('status', 'concluido')->sum('valor');
            $faturamentoPeriodo = (clone $query)->sum('pedidos.valor');
            $comissoesTotal = $faturamentoPeriodo * 0.10;

            // ✅ CORRIGIDO: Tabela pagamentos não existe
            $pagamentosPendentes = 0;

            $receitaPorDia = (clone $query)
                ->select(DB::raw('DATE(pedidos.created_at) as data'), DB::raw('SUM(pedidos.valor) as valor'))
                ->groupBy('data')
                ->orderBy('data', 'asc')
                ->get();

            $receitaPorMes = (clone $query)
                ->select(DB::raw("DATE_FORMAT(pedidos.created_at, '%Y-%m') as mes"), DB::raw('SUM(pedidos.valor) as valor'))
                ->where('pedidos.created_at', '>=', Carbon::now()->subMonths(12))
                ->groupBy('mes')
                ->orderBy('mes', 'asc')
                ->get();

            $topCategorias = (clone $query)
                ->join('categorias', 'pedidos.categoria_id', '=', 'categorias.id')
                ->select('categorias.nome', DB::raw('SUM(pedidos.valor) as valor'))
                ->groupBy('categorias.id', 'categorias.nome')
                ->orderBy('valor', 'DESC')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'faturamento_total' => (float)$faturamentoTotal,
                    'faturamento_periodo' => (float)$faturamentoPeriodo,
                    'comissoes_total' => (float)$comissoesTotal,
                    'pagamentos_pendentes' => (float)$pagamentosPendentes,
                    'receita_por_dia' => $receitaPorDia,
                    'receita_por_mes' => $receitaPorMes,
                    'top_categorias' => $topCategorias,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar relatório financeiro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /admin/relatorios/prestadores
     * Relatório de prestadores
     */
    public function prestadores(Request $request)
    {
        try {
            // Dados da tabela users com tipo = 'prestador'
            $totalPrestadores = User::where('tipo', 'prestador')->count();

            // Usando coluna 'disponivel' (existe na tabela users)
            $disponiveis = User::where('tipo', 'prestador')->where('disponivel', true)->count();
            $indisponiveis = $totalPrestadores - $disponiveis;

            // Usando coluna 'verificado' (existe na tabela users)
            $verificados = User::where('tipo', 'prestador')->where('verificado', true)->count();
            $naoVerificados = $totalPrestadores - $verificados;

            // Média de avaliação
            $mediaAvaliacao = Avaliacao::whereNotNull('prestador_id')->avg('nota') ?? 0;

            // Top prestadores
            $topPrestadores = Pedido::where('status', 'concluido')
                ->join('users', 'pedidos.prestador_id', '=', 'users.id')
                ->select(
                    'users.id',
                    'users.nome',
                    'users.profissao',
                    DB::raw('COUNT(*) as total_pedidos'),
                    DB::raw('SUM(pedidos.valor) as faturamento')
                )
                ->whereNotNull('pedidos.prestador_id')
                ->where('users.tipo', 'prestador')
                ->groupBy('users.id', 'users.nome', 'users.profissao')
                ->orderBy('faturamento', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    $media = Avaliacao::where('prestador_id', $item->id)->avg('nota') ?? 0;
                    $item->media_avaliacao = round($media, 1);
                    return $item;
                });

            // ✅ CORRIGIDO: Tabela prestador_categoria não existe
            // Buscar categorias dos prestadores através da tabela pedidos
            $prestadoresPorCategoria = DB::table('pedidos')
                ->join('users', 'pedidos.prestador_id', '=', 'users.id')
                ->join('categorias', 'pedidos.categoria_id', '=', 'categorias.id')
                ->select('categorias.nome', DB::raw('COUNT(DISTINCT pedidos.prestador_id) as total'))
                ->where('users.tipo', 'prestador')
                ->whereNotNull('pedidos.prestador_id')
                ->groupBy('categorias.id', 'categorias.nome')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_prestadores' => $totalPrestadores,
                    'disponiveis' => $disponiveis,
                    'indisponiveis' => $indisponiveis,
                    'verificados' => $verificados,
                    'nao_verificados' => $naoVerificados,
                    'media_avaliacao_global' => round($mediaAvaliacao, 1),
                    'top_prestadores' => $topPrestadores,
                    'prestadores_por_categoria' => $prestadoresPorCategoria,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar relatório de prestadores: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /admin/relatorios/clientes
     * Relatório de clientes
     */
    public function clientes(Request $request)
    {
        try {
            $dataInicio = $request->input('data_inicio', Carbon::now()->startOfMonth()->toDateString());
            $dataFim = $request->input('data_fim', Carbon::now()->toDateString());

            $totalClientes = User::where('tipo', 'cliente')->count();
            $novosMes = User::where('tipo', 'cliente')
                ->whereBetween('created_at', [$dataInicio, $dataFim . ' 23:59:59'])
                ->count();
            $ativosMes = Pedido::whereBetween('pedidos.created_at', [$dataInicio, $dataFim . ' 23:59:59'])
                ->distinct('cliente_id')
                ->count('cliente_id');

            $topClientes = Pedido::where('status', 'concluido')
                ->join('users', 'pedidos.cliente_id', '=', 'users.id')
                ->select(
                    'users.id',
                    'users.nome',
                    DB::raw('COUNT(*) as total_pedidos'),
                    DB::raw('SUM(pedidos.valor) as total_gasto')
                )
                ->where('users.tipo', 'cliente')
                ->groupBy('users.id', 'users.nome')
                ->orderBy('total_gasto', 'desc')
                ->limit(10)
                ->get();

            $clientesPorMes = User::where('tipo', 'cliente')
                ->where('created_at', '>=', Carbon::now()->subMonths(12))
                ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as mes"), DB::raw('COUNT(*) as total'))
                ->groupBy('mes')
                ->orderBy('mes', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_clientes' => $totalClientes,
                    'novos_mes' => $novosMes,
                    'ativos_mes' => $ativosMes,
                    'top_clientes' => $topClientes,
                    'clientes_por_mes' => $clientesPorMes,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar relatório de clientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /admin/relatorios/{tipo}/exportar
     */
    public function exportar(Request $request, $tipo)
    {
        try {
            $format = $request->input('format', 'excel');
            $dataInicio = $request->input('data_inicio', Carbon::now()->startOfMonth()->toDateString());
            $dataFim = $request->input('data_fim', Carbon::now()->toDateString());

            if ($format === 'excel') {
                $dados = $this->getDadosExportacao($tipo, $dataInicio, $dataFim);
                return response()->json([
                    'success' => true,
                    'data' => $dados,
                    'message' => 'Dados prontos para exportação'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $this->getDadosExportacao($tipo, $dataInicio, $dataFim),
                'message' => 'Para exportar PDF, instale: composer require barryvdh/laravel-dompdf'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao exportar: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== MÉTODOS PRIVADOS ====================

    private function getDadosExportacao($tipo, $dataInicio, $dataFim)
    {
        switch ($tipo) {
            case 'completo':
                return [
                    'pedidos' => $this->getDadosPedidos($dataInicio, $dataFim),
                    'financeiro' => $this->getDadosFinanceiro($dataInicio, $dataFim),
                    'prestadores' => $this->getDadosPrestadores(),
                    'clientes' => $this->getDadosClientes($dataInicio, $dataFim),
                ];
            case 'pedidos':
                return $this->getDadosPedidos($dataInicio, $dataFim);
            case 'financeiro':
                return $this->getDadosFinanceiro($dataInicio, $dataFim);
            case 'prestadores':
                return $this->getDadosPrestadores();
            case 'clientes':
                return $this->getDadosClientes($dataInicio, $dataFim);
            default:
                return [];
        }
    }

    private function getDadosPedidos($dataInicio, $dataFim)
    {
        $query = Pedido::whereBetween('pedidos.created_at', [$dataInicio, $dataFim . ' 23:59:59']);

        return [
            'total' => $query->count(),
            'valor_total' => (float)$query->sum('pedidos.valor'),
            'por_status' => [
                'pendente' => (clone $query)->where('pedidos.status', 'pendente')->count(),
                'concluido' => (clone $query)->where('pedidos.status', 'concluido')->count(),
                'cancelado' => (clone $query)->where('pedidos.status', 'cancelado')->count(),
            ]
        ];
    }

    private function getDadosFinanceiro($dataInicio, $dataFim)
    {
        $query = Pedido::whereBetween('pedidos.created_at', [$dataInicio, $dataFim . ' 23:59:59'])
            ->where('pedidos.status', 'concluido');

        return [
            'faturamento_periodo' => (float)$query->sum('pedidos.valor'),
            'faturamento_total' => (float)Pedido::where('status', 'concluido')->sum('valor'),
        ];
    }

    private function getDadosPrestadores()
    {
        return [
            'total_prestadores' => User::where('tipo', 'prestador')->count(),
            'disponiveis' => User::where('tipo', 'prestador')->where('disponivel', true)->count(),
        ];
    }

    private function getDadosClientes($dataInicio, $dataFim)
    {
        return [
            'total_clientes' => User::where('tipo', 'cliente')->count(),
            'novos_mes' => User::where('tipo', 'cliente')
                ->whereBetween('created_at', [$dataInicio, $dataFim . ' 23:59:59'])
                ->count(),
        ];
    }
}
