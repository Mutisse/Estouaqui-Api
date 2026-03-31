<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promocao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PromocaoController extends Controller
{
    /**
     * Listar todas as promoções
     * GET /api/promocoes
     */
    public function index()
    {
        $promocoes = Promocao::orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $promocoes
        ]);
    }

    /**
     * Listar promoções ativas
     * GET /api/promocoes/ativas
     */
    public function ativas()
    {
        $promocoes = Promocao::ativas()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $promocoes
        ]);
    }

    /**
     * Detalhes de uma promoção
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

    /**
     * Buscar promoção por código
     * GET /api/promocoes/codigo/{codigo}
     */
    public function showByCodigo($codigo)
    {
        $promocao = Promocao::where('codigo', strtoupper($codigo))
            ->first();

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

    /**
     * Validar cupom
     * POST /api/promocoes/validar
     */
    public function validarCupom(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'codigo' => 'required|string|max:50',
            'valor_pedido' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $promocao = Promocao::where('codigo', strtoupper($request->codigo))
            ->first();

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

        $valorPedido = $request->valor_pedido ?? 0;

        if ($valorPedido < $promocao->valor_minimo) {
            return response()->json([
                'success' => false,
                'message' => "Valor mínimo do pedido: " . number_format($promocao->valor_minimo, 2) . " MZN"
            ], 422);
        }

        $desconto = $promocao->calcularDesconto($valorPedido);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $promocao->id,
                'codigo' => $promocao->codigo,
                'titulo' => $promocao->titulo,
                'descricao' => $promocao->descricao,
                'tipo_desconto' => $promocao->tipo_desconto,
                'valor_desconto' => $promocao->valor_desconto,
                'desconto_aplicado' => $desconto,
                'valor_minimo' => $promocao->valor_minimo,
                'validade' => $promocao->validade,
            ]
        ]);
    }

    /**
     * Criar nova promoção (admin)
     * POST /api/promocoes
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'codigo' => 'required|string|max:50|unique:promocoes',
            'titulo' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'tipo_desconto' => 'required|in:percentual,fixo',
            'valor_desconto' => 'required|numeric|min:0',
            'valor_minimo' => 'required|numeric|min:0',
            'validade' => 'required|date|after:today',
            'ativo' => 'boolean',
            'imagem' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $promocao = Promocao::create([
            'codigo' => strtoupper($request->codigo),
            'titulo' => $request->titulo,
            'descricao' => $request->descricao,
            'tipo_desconto' => $request->tipo_desconto,
            'valor_desconto' => $request->valor_desconto,
            'valor_minimo' => $request->valor_minimo,
            'validade' => $request->validade,
            'ativo' => $request->ativo ?? true,
            'imagem' => $request->imagem,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Promoção criada com sucesso',
            'data' => $promocao
        ], 201);
    }

    /**
     * Atualizar promoção (admin)
     * PUT /api/promocoes/{id}
     */
    public function update(Request $request, $id)
    {
        $promocao = Promocao::find($id);

        if (!$promocao) {
            return response()->json([
                'success' => false,
                'message' => 'Promoção não encontrada'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'codigo' => 'sometimes|string|max:50|unique:promocoes,codigo,' . $id,
            'titulo' => 'sometimes|string|max:255',
            'descricao' => 'nullable|string',
            'tipo_desconto' => 'sometimes|in:percentual,fixo',
            'valor_desconto' => 'sometimes|numeric|min:0',
            'valor_minimo' => 'sometimes|numeric|min:0',
            'validade' => 'sometimes|date',
            'ativo' => 'boolean',
            'imagem' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        if ($request->has('codigo')) $promocao->codigo = strtoupper($request->codigo);
        if ($request->has('titulo')) $promocao->titulo = $request->titulo;
        if ($request->has('descricao')) $promocao->descricao = $request->descricao;
        if ($request->has('tipo_desconto')) $promocao->tipo_desconto = $request->tipo_desconto;
        if ($request->has('valor_desconto')) $promocao->valor_desconto = $request->valor_desconto;
        if ($request->has('valor_minimo')) $promocao->valor_minimo = $request->valor_minimo;
        if ($request->has('validade')) $promocao->validade = $request->validade;
        if ($request->has('ativo')) $promocao->ativo = $request->ativo;
        if ($request->has('imagem')) $promocao->imagem = $request->imagem;

        $promocao->save();

        return response()->json([
            'success' => true,
            'message' => 'Promoção atualizada com sucesso',
            'data' => $promocao
        ]);
    }

    /**
     * Deletar promoção (admin)
     * DELETE /api/promocoes/{id}
     */
    public function destroy($id)
    {
        $promocao = Promocao::find($id);

        if (!$promocao) {
            return response()->json([
                'success' => false,
                'message' => 'Promoção não encontrada'
            ], 404);
        }

        $promocao->delete();

        return response()->json([
            'success' => true,
            'message' => 'Promoção removida com sucesso'
        ]);
    }
}
