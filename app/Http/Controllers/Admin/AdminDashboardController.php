<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    /**
     * Carrega todos os dados do dashboard - UMA ÚNICA CONSULTA
     * GET /admin/dashboard
     */
    public function index()
    {
        try {
            $startOfMonth = Carbon::now()->startOfMonth();
            $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
            $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();
            $sevenDaysAgo = Carbon::now()->subDays(6)->startOfDay();

            // ========== 1. CONSULTA PRINCIPAL (tudo em uma vez) ==========
            $totais = DB::transaction(function () use ($startOfMonth, $startOfLastMonth, $endOfLastMonth, $sevenDaysAgo) {

                // Totais gerais
                $totalUsuarios = User::count();
                $totalPrestadores = User::where('tipo', 'prestador')->count();
                $totalPedidos = Pedido::count();

                // Faturamento do mês
                $faturamentoMes = (float) Pedido::where('status', 'concluido')
                    ->where('created_at', '>=', $startOfMonth)
                    ->sum('valor');

                // Crescimentos (mês atual vs mês anterior)
                $usuariosMesAtual = User::where('created_at', '>=', $startOfMonth)->count();
                $usuariosMesAnterior = User::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();

                $prestadoresMesAtual = User::where('tipo', 'prestador')->where('created_at', '>=', $startOfMonth)->count();
                $prestadoresMesAnterior = User::where('tipo', 'prestador')->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();

                $pedidosMesAtual = Pedido::where('created_at', '>=', $startOfMonth)->count();
                $pedidosMesAnterior = Pedido::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();

                $faturamentoMesAtual = (float) Pedido::where('status', 'concluido')->where('created_at', '>=', $startOfMonth)->sum('valor');
                $faturamentoMesAnterior = (float) Pedido::where('status', 'concluido')->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->sum('valor');

                // Atividade últimos 7 dias (uma query com groupBy)
                $atividade7Dias = Pedido::where('created_at', '>=', $sevenDaysAgo)
                    ->select(DB::raw('DATE(created_at) as dia'), DB::raw('COUNT(*) as total'))
                    ->groupBy('dia')
                    ->orderBy('dia', 'asc')
                    ->get()
                    ->keyBy('dia')
                    ->map(fn($item) => $item->total)
                    ->toArray();

                // Distribuição por tipo (uma query)
                $distribuicao = User::select('tipo', DB::raw('COUNT(*) as total'))
                    ->groupBy('tipo')
                    ->get()
                    ->keyBy('tipo')
                    ->map(fn($item) => $item->total)
                    ->toArray();

                // Últimos 5 utilizadores
                $ultimosUtilizadores = User::orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(['id', 'nome', 'email', 'tipo', 'created_at'])
                    ->map(fn($user) => [
                        'id' => $user->id,
                        'nome' => $user->nome,
                        'email' => $user->email,
                        'tipo' => $user->tipo,
                        'data_criacao' => $user->created_at->toISOString(),
                    ]);

                // Últimos 5 pedidos com relacionamentos
                $servicosRecentes = Pedido::with(['cliente:id,nome', 'prestador:id,nome', 'categoria:id,nome'])
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(['id', 'descricao', 'valor', 'status', 'categoria_id', 'cliente_id', 'prestador_id', 'created_at'])
                    ->map(fn($pedido) => [
                        'id' => $pedido->id,
                        'servico' => $pedido->descricao,
                        'nome' => $pedido->categoria->nome ?? 'Serviço',
                        'cliente' => $pedido->cliente->nome ?? '—',
                        'cliente_nome' => $pedido->cliente->nome ?? '—',
                        'prestador' => $pedido->prestador->nome ?? '—',
                        'prestador_nome' => $pedido->prestador->nome ?? '—',
                        'valor' => (float) $pedido->valor,
                        'status' => $this->getStatusLabel($pedido->status),
                        'statusKey' => match ($pedido->status) {
                            'concluido' => 'ok',
                            'em_andamento' => 'prog',
                            'cancelado' => 'cancel',
                            default => 'pend'
                        },
                        'icone' => $this->getIconePorCategoria($pedido->categoria_id),
                        'colorKey' => ['blue', 'green', 'gold', 'teal', 'red', 'purple', 'slate'][$pedido->categoria_id % 7] ?? 'slate',
                    ]);

                return [
                    'total_usuarios' => $totalUsuarios,
                    'total_prestadores' => $totalPrestadores,
                    'total_servicos' => $totalPedidos,
                    'faturamento_mes' => $faturamentoMes,
                    'crescimento_usuarios' => $this->calcCrescimento($usuariosMesAtual, $usuariosMesAnterior),
                    'crescimento_prestadores' => $this->calcCrescimento($prestadoresMesAtual, $prestadoresMesAnterior),
                    'crescimento_servicos' => $this->calcCrescimento($pedidosMesAtual, $pedidosMesAnterior),
                    'crescimento_faturamento' => $this->calcCrescimento($faturamentoMesAtual, $faturamentoMesAnterior),
                    'atividade_ultimos_7_dias' => $this->formatAtividade($atividade7Dias, $sevenDaysAgo),
                    'distribuicao_tipos' => $this->formatDistribuicao($distribuicao),
                    'ultimos_utilizadores' => $ultimosUtilizadores,
                    'servicos_recentes' => $servicosRecentes,
                ];
            });

            return response()->json(['success' => true, 'data' => $totais]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar dashboard: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Carrega apenas estatísticas - RÁPIDO (apenas counts)
     * GET /admin/dashboard/estatisticas
     */
    public function estatisticas()
    {
        try {
            $startOfMonth = Carbon::now()->startOfMonth();
            $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
            $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();

            // TODAS as queries em paralelo usando uma única transação
            $stats = DB::transaction(function () use ($startOfMonth, $startOfLastMonth, $endOfLastMonth) {

                $totalUsuarios = User::count();
                $totalPrestadores = User::where('tipo', 'prestador')->count();
                $totalPedidos = Pedido::count();
                $faturamentoMes = (float) Pedido::where('status', 'concluido')->where('created_at', '>=', $startOfMonth)->sum('valor');

                $usuariosMesAtual = User::where('created_at', '>=', $startOfMonth)->count();
                $usuariosMesAnterior = User::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();

                $prestadoresMesAtual = User::where('tipo', 'prestador')->where('created_at', '>=', $startOfMonth)->count();
                $prestadoresMesAnterior = User::where('tipo', 'prestador')->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();

                $pedidosMesAtual = Pedido::where('created_at', '>=', $startOfMonth)->count();
                $pedidosMesAnterior = Pedido::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();

                $faturamentoAtual = (float) Pedido::where('status', 'concluido')->where('created_at', '>=', $startOfMonth)->sum('valor');
                $faturamentoAnterior = (float) Pedido::where('status', 'concluido')->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->sum('valor');

                return [
                    'total_usuarios' => $totalUsuarios,
                    'total_prestadores' => $totalPrestadores,
                    'total_servicos' => $totalPedidos,
                    'faturamento_mes' => $faturamentoMes,
                    'crescimento_usuarios' => $this->calcCrescimento($usuariosMesAtual, $usuariosMesAnterior),
                    'crescimento_prestadores' => $this->calcCrescimento($prestadoresMesAtual, $prestadoresMesAnterior),
                    'crescimento_servicos' => $this->calcCrescimento($pedidosMesAtual, $pedidosMesAnterior),
                    'crescimento_faturamento' => $this->calcCrescimento($faturamentoAtual, $faturamentoAnterior),
                ];
            });

            return response()->json(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar estatísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Carrega atividade dos últimos 7 dias
     * GET /admin/dashboard/atividade
     */
    public function atividade()
    {
        try {
            $sevenDaysAgo = Carbon::now()->subDays(6)->startOfDay();

            $atividade = Pedido::where('created_at', '>=', $sevenDaysAgo)
                ->select(DB::raw('DATE(created_at) as dia'), DB::raw('COUNT(*) as total'))
                ->groupBy('dia')
                ->orderBy('dia', 'asc')
                ->get()
                ->keyBy('dia')
                ->map(fn($item) => $item->total)
                ->toArray();

            $data = $this->formatAtividade($atividade, $sevenDaysAgo);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar atividade: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Carrega últimos utilizadores
     * GET /admin/dashboard/ultimos-utilizadores
     */
    public function ultimosUtilizadores()
    {
        try {
            $data = User::orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'nome', 'email', 'tipo', 'created_at'])
                ->map(fn($user) => [
                    'id' => $user->id,
                    'nome' => $user->nome,
                    'email' => $user->email,
                    'tipo' => $user->tipo,
                    'data_criacao' => $user->created_at->toISOString(),
                ]);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar últimos utilizadores: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Carrega serviços recentes
     * GET /admin/dashboard/servicos-recentes
     */
    public function servicosRecentes()
    {
        try {
            $data = Pedido::with(['cliente:id,nome', 'prestador:id,nome', 'categoria:id,nome'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'descricao', 'valor', 'status', 'categoria_id', 'cliente_id', 'prestador_id', 'created_at'])
                ->map(fn($pedido) => [
                    'id' => $pedido->id,
                    'servico' => $pedido->descricao,
                    'nome' => $pedido->categoria->nome ?? 'Serviço',
                    'cliente' => $pedido->cliente->nome ?? '—',
                    'cliente_nome' => $pedido->cliente->nome ?? '—',
                    'prestador' => $pedido->prestador->nome ?? '—',
                    'prestador_nome' => $pedido->prestador->nome ?? '—',
                    'valor' => (float) $pedido->valor,
                    'status' => $this->getStatusLabel($pedido->status),
                    'statusKey' => match ($pedido->status) {
                        'concluido' => 'ok',
                        'em_andamento' => 'prog',
                        'cancelado' => 'cancel',
                        default => 'pend'
                    },
                    'icone' => $this->getIconePorCategoria($pedido->categoria_id),
                    'colorKey' => ['blue', 'green', 'gold', 'teal', 'red', 'purple', 'slate'][$pedido->categoria_id % 7] ?? 'slate',
                ]);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar serviços recentes: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== MÉTODOS PRIVADOS OTIMIZADOS ====================

    private function calcCrescimento(float|int $atual, float|int $anterior): int
    {
        if ($anterior == 0) return 0;
        return (int) round((($atual - $anterior) / $anterior) * 100);
    }

    private function formatAtividade(array $atividade, Carbon $startDate): array
    {
        $resultado = [];
        for ($i = 0; $i < 7; $i++) {
            $data = $startDate->copy()->addDays($i);
            $diaKey = $data->format('Y-m-d');
            $resultado[] = [
                'dia' => $diaKey,
                'quantidade' => $atividade[$diaKey] ?? 0,
            ];
        }
        return $resultado;
    }

    private function formatDistribuicao(array $distribuicao): array
    {
        $total = array_sum($distribuicao);
        $cores = [
            'cliente' => '#3B82F6',
            'prestador' => '#8B5CF6',
            'admin' => '#EF4444',
            'root' => '#1F2937',
        ];

        $tipos = ['cliente', 'prestador', 'admin', 'root'];
        $resultado = [];

        foreach ($tipos as $tipo) {
            $quantidade = $distribuicao[$tipo] ?? 0;
            $resultado[] = [
                'tipo' => $tipo,
                'quantidade' => $quantidade,
                'percentual' => $total > 0 ? round(($quantidade / $total) * 100) : 0,
                'cor' => $cores[$tipo] ?? '#6B7280',
            ];
        }

        return $resultado;
    }

    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            'pendente' => 'Pendente',
            'aceito' => 'Aceito',
            'em_andamento' => 'Em Andamento',
            'concluido' => 'Concluído',
            'cancelado' => 'Cancelado',
            default => $status
        };
    }

    private function getIconePorCategoria(?int $categoriaId): string
    {
        $icones = [
            1 => 'handyman',
            2 => 'build',
            3 => 'bolt',
            4 => 'water_drop',
            5 => 'brush',
            6 => 'cleaning_services',
            7 => 'moving',
            8 => 'security',
            9 => 'school',
            10 => 'fitness_center',
        ];
        return $icones[$categoriaId] ?? 'receipt';
    }

    /**
     * Versão simplificada para o frontend
     */
    /**
     * Versão simplificada para o frontend
     */
    public function stats()
    {
        try {
            $startOfMonth = Carbon::now()->startOfMonth();

            $stats = [
                'total_usuarios' => User::count(),
                'total_prestadores' => User::where('tipo', 'prestador')->count(),
                'total_clientes' => User::where('tipo', 'cliente')->count(),
                'total_pedidos' => Pedido::count(),
                'pedidos_pendentes' => Pedido::where('status', 'pendente')->count(),
                'pedidos_concluidos' => Pedido::where('status', 'concluido')->count(),
                'ganhos_totais' => (float) Pedido::where('status', 'concluido')->sum('valor'),
                'ganhos_mes' => (float) Pedido::where('status', 'concluido')
                    ->where('created_at', '>=', $startOfMonth)
                    ->sum('valor'),
                'prestadores_pendentes' => User::where('tipo', 'prestador')
                    ->where('verificado', 0)  // ← CORRIGIDO
                    ->count(),
                'tickets_abertos' => 0,
                'notificacoes_nao_lidas' => 0,
                'alertas_ativos' => 0,
                'avaliacoes_pendentes' => 0,
            ];

            return response()->json(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage()
            ], 500);
        }
    }
}
