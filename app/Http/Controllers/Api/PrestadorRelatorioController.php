<?php
// app/Http/Controllers/Api/PrestadorRelatorioController.php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Pedido;
use App\Models\Servico;
use Carbon\Carbon;

class PrestadorRelatorioController extends BaseController
{
    /**
     * GET /prestador/relatorio-financeiro
     * Estatísticas financeiras do prestador
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $periodo = $request->get('periodo', 'mes');

        // Datas base
        $hoje = Carbon::now();
        $inicioSemana = Carbon::now()->startOfWeek();
        $inicioMes = Carbon::now()->startOfMonth();
        $inicioAno = Carbon::now()->startOfYear();

        // Ganhos por período
        $ganhosHoje = Pedido::where('prestador_id', $user->id)
            ->where('status', 'concluido')
            ->whereDate('created_at', $hoje)
            ->sum('valor');

        $ganhosSemana = Pedido::where('prestador_id', $user->id)
            ->where('status', 'concluido')
            ->whereBetween('created_at', [$inicioSemana, $hoje])
            ->sum('valor');

        $ganhosMes = Pedido::where('prestador_id', $user->id)
            ->where('status', 'concluido')
            ->whereYear('created_at', $hoje->year)
            ->whereMonth('created_at', $hoje->month)
            ->sum('valor');

        $ganhosAno = Pedido::where('prestador_id', $user->id)
            ->where('status', 'concluido')
            ->whereYear('created_at', $hoje->year)
            ->sum('valor');

        // Variações
        $ganhosMesAnterior = Pedido::where('prestador_id', $user->id)
            ->where('status', 'concluido')
            ->whereYear('created_at', $hoje->copy()->subMonth()->year)
            ->whereMonth('created_at', $hoje->copy()->subMonth()->month)
            ->sum('valor');

        $variacaoMes = $ganhosMesAnterior > 0
            ? round((($ganhosMes - $ganhosMesAnterior) / $ganhosMesAnterior) * 100, 1)
            : ($ganhosMes > 0 ? 100 : 0);

        // Total de serviços
        $totalServicos = Pedido::where('prestador_id', $user->id)
            ->where('status', 'concluido')
            ->count();

        // Total de clientes únicos
        $totalClientes = Pedido::where('prestador_id', $user->id)
            ->where('status', 'concluido')
            ->distinct('cliente_id')
            ->count('cliente_id');

        // Avaliação média
        $avaliacaoMedia = $user->prestadorProfile ? $user->prestadorProfile->media_avaliacao : 0;

        // Ganhos por mês (últimos 6 meses)
        $ganhosPorMes = [];
        for ($i = 5; $i >= 0; $i--) {
            $data = Carbon::now()->subMonths($i);
            $total = Pedido::where('prestador_id', $user->id)
                ->where('status', 'concluido')
                ->whereYear('created_at', $data->year)
                ->whereMonth('created_at', $data->month)
                ->sum('valor');

            $ganhosPorMes[] = [
                'mes' => $data->format('M'),
                'ano' => $data->year,
                'total' => (float) $total
            ];
        }

        // Serviços por categoria
        $servicosPorCategoria = Pedido::where('prestador_id', $user->id)
            ->where('status', 'concluido')
            ->with('categoria')
            ->get()
            ->groupBy('categoria.nome')
            ->map(function ($items, $categoria) {
                return [
                    'categoria' => $categoria ?: 'Sem categoria',
                    'total' => $items->count(),
                    'cor' => $this->getCorCategoria($categoria)
                ];
            })
            ->values()
            ->toArray();

        // Status dos serviços
        $statusServicos = [
            [
                'status' => 'Concluídos',
                'total' => Pedido::where('prestador_id', $user->id)->where('status', 'concluido')->count(),
                'cor' => '#10B981'
            ],
            [
                'status' => 'Pendentes',
                'total' => Pedido::where('prestador_id', $user->id)->where('status', 'pendente')->count(),
                'cor' => '#F59E0B'
            ],
            [
                'status' => 'Em andamento',
                'total' => Pedido::where('prestador_id', $user->id)->where('status', 'em_andamento')->count(),
                'cor' => '#3B82F6'
            ],
            [
                'status' => 'Cancelados',
                'total' => Pedido::where('prestador_id', $user->id)->where('status', 'cancelado')->count(),
                'cor' => '#EF4444'
            ],
        ];

        // Top serviços - agrupar por descrição
        $topServicos = Pedido::where('prestador_id', $user->id)
            ->where('status', 'concluido')
            ->get()
            ->groupBy('descricao')
            ->map(function ($items, $descricao) {
                return [
                    'nome' => $descricao ?: 'Serviço',
                    'quantidade' => $items->count(),
                    'receita' => $items->sum('valor')
                ];
            })
            ->sortByDesc('receita')
            ->take(5)
            ->values()
            ->toArray();

        // Se não houver descrição, agrupa por categoria
        if (empty($topServicos)) {
            $topServicos = Pedido::where('prestador_id', $user->id)
                ->where('status', 'concluido')
                ->with('categoria')
                ->get()
                ->groupBy('categoria.nome')
                ->map(function ($items, $categoria) {
                    return [
                        'nome' => $categoria ?: 'Serviço',
                        'quantidade' => $items->count(),
                        'receita' => $items->sum('valor')
                    ];
                })
                ->sortByDesc('receita')
                ->take(5)
                ->values()
                ->toArray();
        }

        // Projeção
        $projecaoMesAtual = $ganhosMes;
        $diasNoMes = Carbon::now()->daysInMonth;
        $diasPassados = Carbon::now()->day;
        $mediaDiaria = $diasPassados > 0 ? $ganhosMes / $diasPassados : 0;
        $diasRestantes = $diasNoMes - $diasPassados;
        $projecaoMesSeguinte = $ganhosMes + ($mediaDiaria * $diasRestantes);

        return response()->json([
            'success' => true,
            'data' => [
                'total_ganhos' => (float) $ganhosAno,
                'total_servicos' => $totalServicos,
                'total_clientes' => $totalClientes,
                'avaliacao_media' => (float) $avaliacaoMedia,
                'ganhos_hoje' => (float) $ganhosHoje,
                'ganhos_semana' => (float) $ganhosSemana,
                'ganhos_mes' => (float) $ganhosMes,
                'ganhos_ano' => (float) $ganhosAno,
                'variacao_mes' => $variacaoMes,
                'variacao_ano' => 0,
                'ganhos_por_mes' => $ganhosPorMes,
                'servicos_por_categoria' => $servicosPorCategoria,
                'status_servicos' => $statusServicos,
                'top_servicos' => $topServicos,
                'projecao_mes_atual' => (float) $projecaoMesAtual,
                'projecao_mes_seguinte' => (float) $projecaoMesSeguinte,
            ]
        ]);
    }

    /**
     * Obtém a cor para uma categoria específica
     */
    private function getCorCategoria($categoria): string
    {
        $cores = [
            'Limpeza' => '#10B981',
            'Eletricista' => '#F59E0B',
            'Canalizador' => '#3B82F6',
            'Pintor' => '#8B5CF6',
            'Jardinagem' => '#10B981',
            'Construção' => '#EF4444',
            'Eletrônica' => '#8B5CF6',
            'Mecânica' => '#F59E0B',
            'Outros' => '#6B7280'
        ];

        return $cores[$categoria] ?? '#6B7280';
    }
}
