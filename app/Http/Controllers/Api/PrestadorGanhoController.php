<?php
// app/Http/Controllers/Api/PrestadorGanhoController.php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Pedido;
use App\Models\Saque;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PrestadorGanhoController extends BaseController
{
    /**
     * GET /api/prestador/ganhos
     * Resumo de ganhos do prestador
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Total de ganhos (pedidos concluídos)
        $total = Pedido::where('prestador_id', $user->id)
            ->where('status', 'concluido')
            ->sum('valor');

        // Ganhos do mês atual
        $mes = Pedido::where('prestador_id', $user->id)
            ->where('status', 'concluido')
            ->whereMonth('concluido_em', Carbon::now()->month)
            ->whereYear('concluido_em', Carbon::now()->year)
            ->sum('valor');

        // Ganhos da semana atual
        $semana = Pedido::where('prestador_id', $user->id)
            ->where('status', 'concluido')
            ->whereBetween('concluido_em', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->sum('valor');

        // Saques pendentes
        $pendente = Saque::where('prestador_id', $user->id)
            ->where('status', 'pendente')
            ->sum('valor');

        return response()->json([
            'success' => true,
            'data' => [
                'total' => (float) $total,
                'mes' => (float) $mes,
                'semana' => (float) $semana,
                'pendente' => (float) $pendente,
            ]
        ]);
    }

    /**
     * GET /api/prestador/ganhos/extrato
     * Extrato detalhado de ganhos
     */
    public function extrato(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'data_inicio' => 'nullable|date',
            'data_fim' => 'nullable|date',
        ]);

        $query = Pedido::where('prestador_id', $user->id)
            ->where('status', 'concluido')
            ->with(['cliente' => function($q) {
                $q->select('id', 'nome', 'foto');
            }]);

        if ($request->has('data_inicio')) {
            $query->whereDate('concluido_em', '>=', $request->data_inicio);
        }

        if ($request->has('data_fim')) {
            $query->whereDate('concluido_em', '<=', $request->data_fim);
        }

        $extrato = $query->orderBy('concluido_em', 'desc')
            ->get()
            ->map(function($pedido) {
                return [
                    'id' => $pedido->id,
                    'cliente_nome' => $pedido->cliente?->nome,
                    'servico_nome' => $pedido->categoria?->nome,
                    'valor' => (float) $pedido->valor,
                    'data' => $pedido->concluido_em,
                    'created_at' => $pedido->concluido_em,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $extrato
        ]);
    }

    /**
     * POST /api/prestador/saques
     * Solicitar saque
     */
    public function solicitarSaque(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'valor' => 'required|numeric|min:100|max:100000',
            'metodo' => 'required|in:mbway,transferencia_bancaria,mpesa',
            'dados_pagamento' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Verificar saldo disponível
        $saldoDisponivel = Pedido::where('prestador_id', $user->id)
            ->where('status', 'concluido')
            ->sum('valor');

        $saquesPendentes = Saque::where('prestador_id', $user->id)
            ->where('status', 'pendente')
            ->sum('valor');

        $saldoAtual = $saldoDisponivel - $saquesPendentes;

        if ($request->valor > $saldoAtual) {
            return response()->json([
                'success' => false,
                'message' => 'Saldo insuficiente para este saque',
                'saldo_disponivel' => $saldoAtual
            ], 400);
        }

        $saque = Saque::create([
            'prestador_id' => $user->id,
            'valor' => $request->valor,
            'metodo' => $request->metodo,
            'dados_pagamento' => $request->dados_pagamento,
            'status' => 'pendente',
            'solicitado_em' => Carbon::now(),
        ]);

        // 🔔 NOTIFICAÇÃO: Saque solicitado
        NotificationService::send('sistema.saque_solicitado', $user->id, [
            'valor' => $request->valor,
            'metodo' => $request->metodo,
            'saque_id' => $saque->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Saque solicitado com sucesso',
            'data' => $saque
        ]);
    }

    /**
     * GET /api/prestador/saques/historico
     * Histórico de saques
     */
    public function historicoSaques(Request $request)
    {
        $user = $request->user();

        $saques = Saque::where('prestador_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($saque) {
                return [
                    'id' => $saque->id,
                    'valor' => (float) $saque->valor,
                    'metodo' => $saque->metodo,
                    'status' => $saque->status,
                    'solicitado_em' => $saque->solicitado_em,
                    'processado_em' => $saque->processado_em,
                    'created_at' => $saque->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $saques
        ]);
    }
}
