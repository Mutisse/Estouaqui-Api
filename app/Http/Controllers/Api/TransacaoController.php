<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransacaoController extends Controller
{
    /**
     * Listar transações (admin)
     * GET /api/admin/financeiro/transacoes
     */
    public function index(Request $request)
    {
        $query = Transacao::with('user', 'pedido');

        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $transacoes = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $transacoes
        ]);
    }

    /**
     * Mostrar uma transação
     * GET /api/admin/financeiro/transacoes/{id}
     */
    public function show($id)
    {
        $transacao = Transacao::with(['user', 'pedido'])->find($id);

        if (!$transacao) {
            return response()->json([
                'success' => false,
                'error' => 'Transação não encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $transacao
        ]);
    }

    /**
     * Criar transação
     * POST /api/admin/financeiro/transacoes
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'pedido_id' => 'nullable|exists:pedidos,id',
            'tipo' => 'required|in:entrada,saida,comissao',
            'valor' => 'required|numeric|min:0',
            'descricao' => 'nullable|string',
            'metodo' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            $transacao = Transacao::create([
                'user_id' => $request->user_id,
                'pedido_id' => $request->pedido_id,
                'tipo' => $request->tipo,
                'valor' => $request->valor,
                'descricao' => $request->descricao,
                'metodo' => $request->metodo,
                'status' => 'pendente',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transação criada com sucesso',
                'data' => $transacao
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar transação'
            ], 500);
        }
    }

    /**
     * Atualizar status da transação
     * PUT /api/admin/financeiro/transacoes/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        $transacao = Transacao::find($id);

        if (!$transacao) {
            return response()->json([
                'success' => false,
                'error' => 'Transação não encontrada'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pendente,processando,concluido,cancelado'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            $transacao->status = $request->status;
            if ($request->status === 'concluido') {
                $transacao->data_confirmacao = now();
            }
            $transacao->save();

            return response()->json([
                'success' => true,
                'message' => 'Status atualizado com sucesso',
                'data' => $transacao
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar status'
            ], 500);
        }
    }

    /**
     * Resumo financeiro
     * GET /api/admin/financeiro/resumo
     */
    public function resumo()
    {
        $saldoAtual = Transacao::where('status', 'concluido')
            ->where('tipo', 'entrada')
            ->sum('valor') -
            Transacao::where('status', 'concluido')
                ->where('tipo', 'saida')
                ->sum('valor');

        $pendente = Transacao::where('status', 'pendente')->sum('valor');
        $processadoMes = Transacao::where('status', 'concluido')
            ->whereMonth('created_at', now()->month)
            ->sum('valor');
        $comissoes = Transacao::where('tipo', 'comissao')
            ->whereMonth('created_at', now()->month)
            ->sum('valor');

        return response()->json([
            'success' => true,
            'data' => [
                'saldo_atual' => $saldoAtual,
                'pendente' => $pendente,
                'processado_mes' => $processadoMes,
                'comissoes' => $comissoes,
            ]
        ]);
    }
}
