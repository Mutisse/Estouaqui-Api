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
        try {
            $promocoes = Promocao::orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->toArray(); // ✅ JÁ CORRETO

            return response()->json([
                'success' => true,
                'data' => $promocoes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao listar promoções: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar promoções ativas
     * GET /api/promocoes/ativas
     */
    public function ativas()
    {
        try {
            $promocoes = Promocao::where('ativo', 1)
                ->whereDate('validade', '>=', date('Y-m-d'))
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->toArray(); // ✅ JÁ CORRETO

            return response()->json([
                'success' => true,
                'data' => $promocoes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao listar promoções ativas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detalhes de uma promoção
     * GET /api/promocoes/{id}
     */
    public function show($id)
    {
        try {
            $promocao = Promocao::find($id);

            if (!$promocao) {
                return response()->json([
                    'success' => false,
                    'message' => 'Promoção não encontrada'
                ], 404);
            }

            // ✅ CONVERTER PARA ARRAY COM CASTS CORRETOS
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => (int) $promocao->id,
                    'codigo' => $promocao->codigo,
                    'titulo' => $promocao->titulo,
                    'descricao' => $promocao->descricao,
                    'tipo_desconto' => $promocao->tipo_desconto,
                    'valor_desconto' => (float) $promocao->valor_desconto,
                    'valor_minimo' => (float) $promocao->valor_minimo,
                    'validade' => $promocao->validade,
                    'ativo' => (bool) $promocao->ativo,
                    'imagem' => $promocao->imagem,
                    'created_at' => $promocao->created_at,
                    'updated_at' => $promocao->updated_at,
                    'deleted_at' => $promocao->deleted_at,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar promoção: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar promoção por código
     * GET /api/promocoes/codigo/{codigo}
     */
    public function showByCodigo($codigo)
    {
        try {
            $promocao = Promocao::where('codigo', strtoupper($codigo))
                ->where('ativo', 1)
                ->whereDate('validade', '>=', date('Y-m-d'))
                ->first();

            if (!$promocao) {
                return response()->json([
                    'success' => false,
                    'message' => 'Promoção não encontrada'
                ], 404);
            }

            // ✅ CONVERTER PARA ARRAY COM CASTS CORRETOS
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => (int) $promocao->id,
                    'codigo' => $promocao->codigo,
                    'titulo' => $promocao->titulo,
                    'descricao' => $promocao->descricao,
                    'tipo_desconto' => $promocao->tipo_desconto,
                    'valor_desconto' => (float) $promocao->valor_desconto,
                    'valor_minimo' => (float) $promocao->valor_minimo,
                    'validade' => $promocao->validade,
                    'ativo' => (bool) $promocao->ativo,
                    'imagem' => $promocao->imagem,
                    'created_at' => $promocao->created_at,
                    'updated_at' => $promocao->updated_at,
                    'deleted_at' => $promocao->deleted_at,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar promoção: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validar cupom
     * POST /api/promocoes/validar
     */
    public function validarCupom(Request $request)
    {
        try {
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
                ->where('ativo', 1)
                ->first();

            if (!$promocao) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cupom inválido'
                ], 404);
            }

            // Verificar validade
            if ($promocao->validade < date('Y-m-d')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cupom expirado'
                ], 422);
            }

            $valorPedido = (float) ($request->valor_pedido ?? 0);

            if ($valorPedido < (float) $promocao->valor_minimo) {
                return response()->json([
                    'success' => false,
                    'message' => "Valor mínimo do pedido: " . number_format($promocao->valor_minimo, 2) . " MZN"
                ], 422);
            }

            // Calcular desconto
            if ($promocao->tipo_desconto === 'percentual') {
                $desconto = ($valorPedido * (float) $promocao->valor_desconto) / 100;
            } else {
                $desconto = (float) $promocao->valor_desconto;
            }

            // ✅ JÁ ESTÁ RETORNANDO ARRAY (CORRETO)
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => (int) $promocao->id,
                    'codigo' => $promocao->codigo,
                    'titulo' => $promocao->titulo,
                    'descricao' => $promocao->descricao,
                    'tipo_desconto' => $promocao->tipo_desconto,
                    'valor_desconto' => (float) $promocao->valor_desconto,
                    'desconto_aplicado' => round($desconto, 2),
                    'valor_minimo' => (float) $promocao->valor_minimo,
                    'validade' => $promocao->validade,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao validar cupom. Tente novamente.'
            ], 500);
        }
    }

    /**
     * Criar nova promoção (admin)
     * POST /api/admin/promocoes
     */
    public function store(Request $request)
    {
        try {
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
                'valor_desconto' => (float) $request->valor_desconto,
                'valor_minimo' => (float) $request->valor_minimo,
                'validade' => $request->validade,
                'ativo' => $request->ativo ?? true,
                'imagem' => $request->imagem,
            ]);

            // ✅ RETORNAR ARRAY NA CRIAÇÃO
            return response()->json([
                'success' => true,
                'message' => 'Promoção criada com sucesso',
                'data' => [
                    'id' => (int) $promocao->id,
                    'codigo' => $promocao->codigo,
                    'titulo' => $promocao->titulo,
                    'descricao' => $promocao->descricao,
                    'tipo_desconto' => $promocao->tipo_desconto,
                    'valor_desconto' => (float) $promocao->valor_desconto,
                    'valor_minimo' => (float) $promocao->valor_minimo,
                    'validade' => $promocao->validade,
                    'ativo' => (bool) $promocao->ativo,
                    'imagem' => $promocao->imagem,
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar promoção: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar promoção (admin)
     * PUT /api/admin/promocoes/{id}
     */
    public function update(Request $request, $id)
    {
        try {
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
            if ($request->has('valor_desconto')) $promocao->valor_desconto = (float) $request->valor_desconto;
            if ($request->has('valor_minimo')) $promocao->valor_minimo = (float) $request->valor_minimo;
            if ($request->has('validade')) $promocao->validade = $request->validade;
            if ($request->has('ativo')) $promocao->ativo = $request->ativo;
            if ($request->has('imagem')) $promocao->imagem = $request->imagem;

            $promocao->save();

            // ✅ RETORNAR ARRAY NA ATUALIZAÇÃO
            return response()->json([
                'success' => true,
                'message' => 'Promoção atualizada com sucesso',
                'data' => [
                    'id' => (int) $promocao->id,
                    'codigo' => $promocao->codigo,
                    'titulo' => $promocao->titulo,
                    'descricao' => $promocao->descricao,
                    'tipo_desconto' => $promocao->tipo_desconto,
                    'valor_desconto' => (float) $promocao->valor_desconto,
                    'valor_minimo' => (float) $promocao->valor_minimo,
                    'validade' => $promocao->validade,
                    'ativo' => (bool) $promocao->ativo,
                    'imagem' => $promocao->imagem,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar promoção: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deletar promoção (admin)
     * DELETE /api/admin/promocoes/{id}
     */
    public function destroy($id)
    {
        try {
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
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao deletar promoção: ' . $e->getMessage()
            ], 500);
        }
    }
}
