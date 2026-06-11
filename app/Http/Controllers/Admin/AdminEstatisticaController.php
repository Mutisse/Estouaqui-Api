<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminEstatisticaController extends Controller
{
    /**
     * GET /admin/estatisticas - VERSÃO OTIMIZADA E CORRIGIDA
     */
    public function index(Request $request)
    {
        try {
            $periodo = $request->input('periodo', 'mes');
            $dataInicio = $this->getDataInicio($periodo);
            $dataInicioMes = Carbon::now()->subMonths(11);

            // ========== 1. TOTAIS ==========
            $totalUsuarios = User::count();
            $totalPrestadores = User::where('tipo', 'prestador')->count();
            $totalClientes = User::where('tipo', 'cliente')->count();
            $totalPedidos = Pedido::count();
            $ganhosTotais = (float) Pedido::where('status', 'concluido')->sum('valor');

            // ========== 2. PEDIDOS POR STATUS ==========
            $pedidosPorStatus = Pedido::select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->get()
                ->keyBy('status')
                ->map(fn($item) => $item->total)
                ->toArray();

            $statusPadrao = ['pendente', 'em_andamento', 'concluido', 'cancelado'];
            foreach ($statusPadrao as $status) {
                if (!isset($pedidosPorStatus[$status])) {
                    $pedidosPorStatus[$status] = 0;
                }
            }

            // ========== 3. CRESCIMENTOS ==========
            $crescimentoUsuarios = $this->crescimento(User::class, $dataInicio);
            $crescimentoPrestadores = $this->crescimento(User::class, $dataInicio, 'tipo', 'prestador');
            $crescimentoPedidos = $this->crescimento(Pedido::class, $dataInicio);
            $crescimentoGanhos = $this->crescimentoFinanceiro($dataInicio);

            // ========== 4. AVALIAÇÕES ==========
            $avaliacoesTotal = 0;
            $mediaAvaliacaoGlobal = 0;
            if ($this->tabelaExiste('avaliacoes')) {
                $avaliacoesTotal = DB::table('avaliacoes')->count();
                $mediaAvaliacaoGlobal = (float) DB::table('avaliacoes')->avg('nota') ?? 0;
            }

            // ========== 5. CATEGORIAS ==========
            $totalCategorias = $this->tabelaExiste('categorias') ? DB::table('categorias')->count() : 0;

            // ========== 6. TICKETS ==========
            $ticketsAbertos = $this->tabelaExiste('suporte_tickets')
                ? DB::table('suporte_tickets')->where('status', 'aberto')->count()
                : 0;

            // ========== 7. GRÁFICOS (CORRIGIDO - sem MIN(created_at)) ==========
            $ganhosPorMes = Pedido::where('status', 'concluido')
                ->where('created_at', '>=', $dataInicioMes)
                ->select(
                    DB::raw("DATE_FORMAT(created_at, '%b/%Y') as mes"),
                    DB::raw("DATE_FORMAT(created_at, '%Y-%m') as mes_ordem"),
                    DB::raw('SUM(valor) as total')
                )
                ->groupBy('mes', 'mes_ordem')
                ->orderBy('mes_ordem', 'asc')
                ->get()
                ->map(fn($item) => ['mes' => $item->mes, 'total' => (float) $item->total]);

            $pedidosPorMes = Pedido::where('created_at', '>=', $dataInicioMes)
                ->select(
                    DB::raw("DATE_FORMAT(created_at, '%b/%Y') as mes"),
                    DB::raw("DATE_FORMAT(created_at, '%Y-%m') as mes_ordem"),
                    DB::raw('COUNT(*) as total')
                )
                ->groupBy('mes', 'mes_ordem')
                ->orderBy('mes_ordem', 'asc')
                ->get()
                ->map(fn($item) => ['mes' => $item->mes, 'total' => (int) $item->total]);

            // ========== 8. TOP CATEGORIAS ==========
            $topCategorias = [];
            if ($this->tabelaExiste('categorias')) {
                $topCategorias = DB::table('pedidos')
                    ->join('categorias', 'pedidos.categoria_id', '=', 'categorias.id')
                    ->select('categorias.nome as categoria', DB::raw('COUNT(*) as total'))
                    ->groupBy('categorias.id', 'categorias.nome')
                    ->orderBy('total', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(fn($item) => ['categoria' => $item->categoria, 'total' => (int) $item->total]);
            }

            // ========== 9. TOP PRESTADORES ==========
            $topPrestadores = Pedido::whereNotNull('prestador_id')
                ->join('users', 'pedidos.prestador_id', '=', 'users.id')
                ->select('users.id', 'users.nome', DB::raw('COUNT(*) as total_pedidos'))
                ->groupBy('users.id', 'users.nome')
                ->orderBy('total_pedidos', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    $avaliacao = 0;
                    if ($this->tabelaExiste('avaliacoes')) {
                        $avaliacao = (float) DB::table('avaliacoes')
                            ->where('prestador_id', $item->id)
                            ->avg('nota') ?? 0;
                    }
                    return [
                        'id' => $item->id,
                        'nome' => $item->nome,
                        'total_pedidos' => (int) $item->total_pedidos,
                        'avaliacao' => round($avaliacao, 1)
                    ];
                });

            // ========== 10. ÚLTIMAS ATIVIDADES ==========
            $ultimasAtividades = $this->getUltimasAtividades();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_usuarios' => $totalUsuarios,
                    'total_prestadores' => $totalPrestadores,
                    'total_clientes' => $totalClientes,
                    'total_pedidos' => $totalPedidos,
                    'ganhos_totais' => $ganhosTotais,
                    'crescimento_usuarios' => $crescimentoUsuarios,
                    'crescimento_prestadores' => $crescimentoPrestadores,
                    'crescimento_pedidos' => $crescimentoPedidos,
                    'crescimento_ganhos' => $crescimentoGanhos,
                    'avaliacoes_total' => $avaliacoesTotal,
                    'media_avaliacao_global' => round($mediaAvaliacaoGlobal, 1),
                    'total_categorias' => $totalCategorias,
                    'tickets_abertos' => $ticketsAbertos,
                    'pedidos_por_status' => $pedidosPorStatus,
                    'ganhos_por_mes' => $ganhosPorMes,
                    'pedidos_por_mes' => $pedidosPorMes,
                    'top_categorias' => $topCategorias,
                    'top_prestadores' => $topPrestadores,
                    'ultimas_atividades' => $ultimasAtividades,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /admin/estatisticas/dashboard
     */
    public function dashboard()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_clientes' => User::where('tipo', 'cliente')->count(),
                    'total_prestadores' => User::where('tipo', 'prestador')->count(),
                    'total_pedidos' => Pedido::count(),
                    'total_pedidos_concluidos' => Pedido::where('status', 'concluido')->count(),
                    'faturamento_total' => (float) Pedido::where('status', 'concluido')->sum('valor'),
                    'faturamento_ultimos_30_dias' => (float) Pedido::where('status', 'concluido')
                        ->where('created_at', '>=', Carbon::now()->subDays(30))
                        ->sum('valor'),
                    'pedidos_por_status' => $this->getPedidosPorStatus(),
                    'pedidos_ultimos_7_dias' => $this->getPedidosUltimosDias(7),
                    'media_avaliacao_global' => $this->tabelaExiste('avaliacoes')
                        ? round(DB::table('avaliacoes')->avg('nota') ?? 0, 1)
                        : 0,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /admin/estatisticas/{periodo}
     */
    public function porPeriodo($periodo)
    {
        try {
            $dataInicio = $this->getDataInicio($periodo);

            return response()->json([
                'success' => true,
                'data' => [
                    'total_pedidos' => Pedido::where('created_at', '>=', $dataInicio)->count(),
                    'pedidos_concluidos' => Pedido::where('status', 'concluido')
                        ->where('created_at', '>=', $dataInicio)
                        ->count(),
                    'ganhos_totais' => (float) Pedido::where('status', 'concluido')
                        ->where('created_at', '>=', $dataInicio)
                        ->sum('valor'),
                    'novos_usuarios' => User::where('created_at', '>=', $dataInicio)->count(),
                    'novos_prestadores' => User::where('tipo', 'prestador')
                        ->where('created_at', '>=', $dataInicio)
                        ->count(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ========== FUNÇÕES AUXILIARES ==========

    private function getDataInicio(string $periodo): Carbon
    {
        return match ($periodo) {
            'semana' => Carbon::now()->subWeek(),
            'trimestre' => Carbon::now()->subMonths(3),
            'ano' => Carbon::now()->subYear(),
            'todos' => Carbon::now()->subYears(10),
            default => Carbon::now()->subMonth(),
        };
    }

    private function crescimento(string $model, Carbon $dataInicio, ?string $campo = null, ?string $valor = null): float
    {
        $queryAtual = $model::where('created_at', '>=', $dataInicio);
        $queryAnterior = $model::whereBetween('created_at', [$dataInicio->copy()->subMonth(), $dataInicio]);

        if ($campo && $valor) {
            $queryAtual->where($campo, $valor);
            $queryAnterior->where($campo, $valor);
        }

        $atual = $queryAtual->count();
        $anterior = $queryAnterior->count();

        if ($anterior == 0) return 0;
        return round((($atual - $anterior) / $anterior) * 100, 1);
    }

    private function crescimentoFinanceiro(Carbon $dataInicio): float
    {
        $atual = Pedido::where('status', 'concluido')
            ->where('created_at', '>=', $dataInicio)
            ->sum('valor');

        $anterior = Pedido::where('status', 'concluido')
            ->whereBetween('created_at', [$dataInicio->copy()->subMonth(), $dataInicio])
            ->sum('valor');

        if ($anterior == 0) return 0;
        return round((($atual - $anterior) / $anterior) * 100, 1);
    }

    private function getPedidosPorStatus(): array
    {
        $status = Pedido::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->map(fn($item) => $item->total)
            ->toArray();

        $padrao = ['pendente', 'aceito', 'em_andamento', 'concluido', 'cancelado'];
        foreach ($padrao as $s) {
            if (!isset($status[$s])) $status[$s] = 0;
        }

        return $status;
    }

    private function getPedidosUltimosDias(int $dias): array
    {
        $resultado = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $data = Carbon::now()->subDays($i);
            $resultado[] = [
                'data' => $data->format('Y-m-d'),
                'total' => Pedido::whereDate('created_at', $data)->count()
            ];
        }
        return $resultado;
    }

    private function getUltimasAtividades(): array
    {
        $pedidos = Pedido::orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($p) => [
                'tipo' => 'pedido',
                'descricao' => "Pedido #{$p->id} - " . number_format($p->valor, 2) . " MZN",
                'created_at' => $p->created_at,
            ]);

        $usuarios = User::orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($u) => [
                'tipo' => 'usuario',
                'descricao' => "Novo usuário: {$u->nome} ({$u->tipo})",
                'created_at' => $u->created_at,
            ]);

        return $pedidos->concat($usuarios)
            ->sortByDesc('created_at')
            ->take(10)
            ->values()
            ->toArray();
    }

    private function tabelaExiste(string $tabela): bool
    {
        static $cache = [];

        if (isset($cache[$tabela])) {
            return $cache[$tabela];
        }

        try {
            $cache[$tabela] = DB::getSchemaBuilder()->hasTable($tabela);
            return $cache[$tabela];
        } catch (\Exception $e) {
            return false;
        }
    }
}
