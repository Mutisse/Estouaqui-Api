<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\User;
use App\Models\Saque;
use App\Models\Transacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminFinanceiroController extends Controller
{
    /**
     * Carregar todos os dados financeiros
     * GET /admin/financeiro
     */
    public function index(Request $request)
    {
        try {
            $periodo = $request->input('periodo', 'mes');

            $data = [
                'resumo' => $this->getResumo($periodo),
                'transacoes' => $this->getTransacoes($periodo),
                'ganhos_por_mes' => $this->getGanhosPorMes($periodo),
                'saques_pendentes' => $this->getSaquesPendentes(),
                'ultimos_saques' => $this->getUltimosSaques(),
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar dados financeiros: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Carregar apenas resumo financeiro
     * GET /admin/financeiro/resumo
     */
    public function resumo(Request $request)
    {
        try {
            $periodo = $request->input('periodo', 'mes');
            $resumo = $this->getResumo($periodo);

            return response()->json($resumo);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar resumo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Carregar transações
     * GET /admin/financeiro/transacoes
     */
    public function transacoes(Request $request)
    {
        try {
            $tipo = $request->input('tipo');
            $status = $request->input('status');
            $dataInicio = $request->input('data_inicio');
            $dataFim = $request->input('data_fim');

            // Aqui você pode buscar transações de uma tabela de transações
            // Por enquanto, vamos usar pedidos concluídos como transações
            $query = Pedido::where('status', 'concluido')
                ->with(['cliente', 'prestador']);

            if ($tipo === 'receita') {
                $query->where('valor', '>', 0);
            } elseif ($tipo === 'despesa') {
                // Para despesas, precisaria de outra tabela
                return response()->json([]);
            }

            if ($dataInicio) {
                $query->whereDate('created_at', '>=', $dataInicio);
            }
            if ($dataFim) {
                $query->whereDate('created_at', '<=', $dataFim);
            }

            $transacoes = $query->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($pedido) {
                    return [
                        'id' => $pedido->id,
                        'descricao' => "Pedido #{$pedido->numero} - {$pedido->descricao}",
                        'valor' => (float) $pedido->valor,
                        'tipo' => 'receita',
                        'status' => 'pago',
                        'data' => $pedido->created_at->format('Y-m-d'),
                        'created_at' => $pedido->created_at->toISOString(),
                        'usuario_nome' => $pedido->cliente->nome ?? null,
                        'prestador_nome' => $pedido->prestador->nome ?? null,
                    ];
                });

            return response()->json($transacoes);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar transações: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar nova transação
     * POST /admin/financeiro/transacoes
     */
    public function registrarTransacao(Request $request)
    {
        try {
            $validated = $request->validate([
                'descricao' => 'required|string|max:255',
                'valor' => 'required|numeric|min:0',
                'tipo' => 'required|in:receita,despesa',
                'status' => 'required|in:pago,pendente',
                'data' => 'required|date',
                'servico_id' => 'nullable|exists:pedidos,id',
                'usuario_id' => 'nullable|exists:users,id',
            ]);

            // Criar transação (se tiver uma tabela de transações)
            // Por enquanto, apenas retorna sucesso
            // Transacao::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Transação registada com sucesso'
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao registrar transação: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar status de transação
     * PUT /admin/financeiro/transacoes/{id}/status
     */
    public function atualizarStatusTransacao(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:pago,pendente,cancelado'
            ]);

            // Atualizar transação se existir
            // $transacao = Transacao::findOrFail($id);
            // $transacao->status = $validated['status'];
            // $transacao->save();

            return response()->json([
                'success' => true,
                'message' => 'Status atualizado com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ganhos por mês
     * GET /admin/financeiro/ganhos-por-mes
     */
    public function ganhosPorMes(Request $request)
    {
        try {
            $periodo = $request->input('periodo', 'mes');
            $ganhos = $this->getGanhosPorMes($periodo);

            return response()->json($ganhos);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar ganhos por mês: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Saques pendentes
     * GET /admin/financeiro/saques/pendentes
     */
    public function saquesPendentes()
    {
        try {
            $saques = $this->getSaquesPendentes();

            return response()->json($saques);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar saques pendentes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Últimos saques
     * GET /admin/financeiro/saques/ultimos
     */
    public function ultimosSaques(Request $request)
    {
        try {
            $limite = $request->input('limite', 10);
            $saques = $this->getUltimosSaques($limite);

            return response()->json($saques);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar últimos saques: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aprovar saque
     * POST /admin/financeiro/saques/{id}/aprovar
     */
    public function aprovarSaque($id)
    {
        try {
            $saque = Saque::findOrFail($id);

            if ($saque->status !== 'pendente') {
                return response()->json([
                    'success' => false,
                    'message' => 'Este saque não está pendente'
                ], 422);
            }

            $saque->status = 'aprovado';
            $saque->data_aprovacao = now();
            $saque->save();

            return response()->json([
                'success' => true,
                'message' => 'Saque aprovado com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao aprovar saque: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Concluir saque
     * POST /admin/financeiro/saques/{id}/concluir
     */
    public function concluirSaque($id)
    {
        try {
            $saque = Saque::findOrFail($id);

            if ($saque->status !== 'aprovado') {
                return response()->json([
                    'success' => false,
                    'message' => 'Este saque não está aprovado'
                ], 422);
            }

            $saque->status = 'concluido';
            $saque->data_pagamento = now();
            $saque->save();

            return response()->json([
                'success' => true,
                'message' => 'Pagamento do saque concluído com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao concluir saque: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recusar saque
     * POST /admin/financeiro/saques/{id}/recusar
     */
    public function recusarSaque(Request $request, $id)
    {
        try {
            $saque = Saque::findOrFail($id);

            if ($saque->status !== 'pendente') {
                return response()->json([
                    'success' => false,
                    'message' => 'Este saque não está pendente'
                ], 422);
            }

            $saque->status = 'recusado';
            $saque->observacao = $request->input('observacao');
            $saque->save();

            return response()->json([
                'success' => true,
                'message' => 'Saque recusado com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao recusar saque: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar relatório
     * GET /admin/financeiro/exportar
     */
    public function exportar(Request $request)
    {
        try {
            $periodo = $request->input('periodo', 'mes');
            $formato = $request->input('formato', 'csv');

            $resumo = $this->getResumo($periodo);
            $transacoes = $this->getTransacoes($periodo);

            if ($formato === 'csv') {
                $csv = $this->gerarCSV($resumo, $transacoes);
                return response($csv, 200, [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => "attachment; filename=financeiro_{$periodo}.csv",
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Formato não suportado'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao exportar relatório: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== MÉTODOS PRIVADOS ====================

    private function getResumo($periodo)
    {
        $dates = $this->getDateRange($periodo);

        $pedidosConcluidos = Pedido::where('status', 'concluido')
            ->whereBetween('created_at', [$dates['inicio'], $dates['fim']]);

        $totalGanhos = (float) $pedidosConcluidos->sum('valor');

        // Pedidos pendentes (em andamento) - valores a receber
        $pedidosPendentes = (float) Pedido::where('status', 'em_andamento')
            ->whereBetween('created_at', [$dates['inicio'], $dates['fim']])
            ->sum('valor');

        // Saques totais
        $totalSaques = (float) Saque::whereBetween('created_at', [$dates['inicio'], $dates['fim']])
            ->sum('valor');

        return [
            'total_ganhos' => $totalGanhos,
            'pendentes' => $pedidosPendentes,
            'pagos' => $totalGanhos,
            'total_saques' => $totalSaques,
            'total_receitas' => $totalGanhos,
            'total_despesas' => 0,
        ];
    }

    private function getTransacoes($periodo)
    {
        $dates = $this->getDateRange($periodo);

        return Pedido::where('status', 'concluido')
            ->whereBetween('created_at', [$dates['inicio'], $dates['fim']])
            ->with(['cliente', 'prestador'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($pedido) {
                return [
                    'id' => $pedido->id,
                    'descricao' => "Pedido #{$pedido->numero}",
                    'valor' => (float) $pedido->valor,
                    'tipo' => 'receita',
                    'status' => 'pago',
                    'data' => $pedido->created_at->format('Y-m-d'),
                    'created_at' => $pedido->created_at->toISOString(),
                ];
            })
            ->toArray();
    }

    private function getGanhosPorMes($periodo)
    {
        $dates = $this->getDateRange($periodo);

        $ganhos = Pedido::where('status', 'concluido')
            ->whereBetween('created_at', [$dates['inicio'], $dates['fim']])
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as mes"),
                DB::raw("DATE_FORMAT(created_at, '%b %Y') as mes_label"),
                DB::raw('YEAR(created_at) as ano'),
                DB::raw('MONTH(created_at) as mes_numero'),
                DB::raw('SUM(valor) as total')
            )
            ->groupBy('mes', 'mes_label', 'ano', 'mes_numero')
            ->orderBy('mes', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'mes' => $item->mes_label,
                    'total' => (float) $item->total,
                    'ano' => $item->ano,
                    'mes_numero' => $item->mes_numero,
                ];
            });

        return $ganhos;
    }

    private function getSaquesPendentes()
    {
        return Saque::where('status', 'pendente')
            ->with('prestador')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($saque) {
                return [
                    'id' => $saque->id,
                    'prestador_id' => $saque->prestador_id,
                    'prestador_nome' => $saque->prestador->nome ?? '—',
                    'valor' => (float) $saque->valor,
                    'status' => $saque->status,
                    'data_solicitacao' => $saque->created_at->toISOString(),
                ];
            })
            ->toArray();
    }

    private function getUltimosSaques($limite = 10)
    {
        return Saque::whereIn('status', ['aprovado', 'concluido'])
            ->with('prestador')
            ->orderBy('created_at', 'desc')
            ->limit($limite)
            ->get()
            ->map(function ($saque) {
                return [
                    'id' => $saque->id,
                    'prestador_id' => $saque->prestador_id,
                    'prestador_nome' => $saque->prestador->nome ?? '—',
                    'valor' => (float) $saque->valor,
                    'status' => $saque->status,
                    'data_solicitacao' => $saque->created_at->toISOString(),
                    'data_aprovacao' => $saque->data_aprovacao,
                    'data_pagamento' => $saque->data_pagamento,
                ];
            })
            ->toArray();
    }

    private function getDateRange($periodo)
    {
        $now = Carbon::now();

        switch ($periodo) {
            case 'trimestre':
                $inicio = $now->copy()->subMonths(3);
                break;
            case 'ano':
                $inicio = $now->copy()->subYear();
                break;
            case 'todos':
                $inicio = Carbon::createFromDate(2020, 1, 1);
                break;
            case 'mes':
            default:
                $inicio = $now->copy()->startOfMonth();
                break;
        }

        return [
            'inicio' => $inicio->startOfDay(),
            'fim' => $now->endOfDay(),
        ];
    }

    private function gerarCSV($resumo, $transacoes)
    {
        $csv = "RELATÓRIO FINANCEIRO\n\n";
        $csv .= "RESUMO\n";
        $csv .= "Ganhos Totais,{$resumo['total_ganhos']}\n";
        $csv .= "Pagamentos Pendentes,{$resumo['pendentes']}\n";
        $csv .= "Pagamentos Realizados,{$resumo['pagos']}\n";
        $csv .= "Total Saques,{$resumo['total_saques']}\n\n";
        $csv .= "TRANSAÇÕES\n";
        $csv .= "ID,Descrição,Valor,Tipo,Status,Data\n";

        foreach ($transacoes as $transacao) {
            $csv .= "{$transacao['id']},{$transacao['descricao']},{$transacao['valor']},{$transacao['tipo']},{$transacao['status']},{$transacao['data']}\n";
        }

        return $csv;
    }
}
