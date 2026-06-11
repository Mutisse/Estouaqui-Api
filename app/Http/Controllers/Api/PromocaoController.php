<?php
// app/Http/Controllers/Api/PromocaoController.php

namespace App\Http\Controllers\Api;

use App\Models\Promocao;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class PromocaoController extends BaseController
{
    /**
     * Listar todas as promoções ativas
     * GET /api/promocoes
     */
    public function index()
    {
        $promocoes = Promocao::where('ativo', true)
            ->where('validade_inicio', '<=', now())
            ->where('validade_fim', '>=', now())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $promocoes
        ]);
    }

    /**
     * Validar um cupom
     * POST /api/promocoes/validar
     */
    public function validar(Request $request)
    {
        $request->validate([
            'codigo' => 'required|string|uppercase'
        ]);

        $codigo = strtoupper($request->codigo);
        $promocao = Promocao::where('codigo', $codigo)->first();

        if (!$promocao) {
            return response()->json([
                'success' => false,
                'message' => 'Cupom inválido'
            ], 404);
        }

        if (!$promocao->isValida()) {
            return response()->json([
                'success' => false,
                'message' => 'Cupom expirado ou inativo'
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cupom válido!',
            'data' => [
                'codigo' => $promocao->codigo,
                'desconto' => $promocao->valor_desconto,
                'tipo' => $promocao->tipo_desconto,
                'valor_minimo' => $promocao->valor_minimo,
            ]
        ]);
    }

    /**
     * Aplicar cupom e gerar notificação
     * POST /api/promocoes/aplicar
     */
    public function aplicarCupom(Request $request)
    {
        $request->validate([
            'codigo' => 'required|string|uppercase',
            'valor_pedido' => 'required|numeric|min:0'
        ]);

        $codigo = strtoupper($request->codigo);
        $promocao = Promocao::where('codigo', $codigo)->first();

        if (!$promocao) {
            return response()->json([
                'success' => false,
                'message' => 'Cupom inválido'
            ], 404);
        }

        if (!$promocao->isValida()) {
            return response()->json([
                'success' => false,
                'message' => 'Cupom expirado ou inativo'
            ], 422);
        }

        $valorPedido = $request->valor_pedido;

        if ($valorPedido < $promocao->valor_minimo) {
            return response()->json([
                'success' => false,
                'message' => "Valor mínimo para este cupom é de " . number_format($promocao->valor_minimo, 2) . " MZN"
            ], 422);
        }

        $desconto = $promocao->calcularDesconto($valorPedido);
        $valorFinal = $valorPedido - $desconto;

        // Incrementar uso do cupom
        $promocao->aplicar();

        // 🔔 NOTIFICAÇÃO: Cupom aplicado com sucesso
        NotificationService::send('promocao.cupom_aplicado', $request->user()->id, [
            'codigo' => $promocao->codigo,
            'desconto' => $promocao->tipo_desconto === 'percentual'
                ? "{$promocao->valor_desconto}%"
                : "MZN " . number_format($promocao->valor_desconto, 2)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cupom aplicado com sucesso!',
            'data' => [
                'codigo' => $promocao->codigo,
                'desconto' => $desconto,
                'valor_final' => $valorFinal,
                'tipo_desconto' => $promocao->tipo_desconto
            ]
        ]);
    }

    /**
     * Mostrar detalhes de uma promoção específica
     * GET /api/promocoes/{id}
     */
    public function show($id)
    {
        $promocao = Promocao::find($id);

        if (!$promocao) {
            return response()->json([
                'success' => false,
                'message' => 'Promoção não encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $promocao
        ]);
    }
}
