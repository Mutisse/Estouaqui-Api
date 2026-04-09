<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promocao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PromocaoController extends Controller
{
    /**
     * Listar todas as promoções
     * GET /api/promocoes
     */
    public function index(Request $request)
    {
        try {
            $promocoes = Cache::remember('promocoes_all', 300, function () {
                return Promocao::orderBy('created_at', 'desc')
                    ->limit(50)
                    ->get()
                    ->map(function ($promocao) {
                        return $this->formatPromocao($promocao);
                    })
                    ->toArray();
            });

            return response()->json([
                'success' => true,
                'data' => $promocoes
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar promoções: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao listar promoções',
                'data' => []
            ], 500);
        }
    }

    /**
     * Listar promoções ativas
     * GET /api/promocoes/ativas
     */
    public function ativas(Request $request)
    {
        try {
            $promocoes = Cache::remember('promocoes_ativas', 300, function () {
                return Promocao::where('ativo', 1)
                    ->whereDate('validade', '>=', now()->toDateString())
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function ($promocao) {
                        return $this->formatPromocao($promocao);
                    })
                    ->toArray();
            });

            return response()->json([
                'success' => true,
                'data' => $promocoes
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar promoções ativas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao listar promoções ativas',
                'data' => []
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
            $cacheKey = "promocao_{$id}";

            $promocao = Cache::remember($cacheKey, 300, function () use ($id) {
                return Promocao::find($id);
            });

            if (!$promocao) {
                return response()->json([
                    'success' => false,
                    'message' => 'Promoção não encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatPromocao($promocao)
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar promoção: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar promoção'
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
            $cacheKey = "promocao_codigo_" . strtoupper($codigo);

            $promocao = Cache::remember($cacheKey, 300, function () use ($codigo) {
                return Promocao::where('codigo', strtoupper($codigo))
                    ->where('ativo', 1)
                    ->whereDate('validade', '>=', now()->toDateString())
                    ->first();
            });

            if (!$promocao) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cupom não encontrado ou expirado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatPromocao($promocao)
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar promoção por código: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar promoção'
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
                'total' => 'nullable|numeric|min:0',
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
                ], 422);
            }

            // Verificar validade
            if ($promocao->validade < now()->toDateString()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cupom expirado'
                ], 422);
            }

            $valorPedido = (float) ($request->total ?? 0);
            $valorMinimo = (float) $promocao->valor_minimo;

            if ($valorPedido > 0 && $valorPedido < $valorMinimo) {
                return response()->json([
                    'success' => false,
                    'message' => "Valor mínimo do pedido: " . number_format($valorMinimo, 2) . " MZN"
                ], 422);
            }

            // Calcular desconto
            if ($promocao->tipo_desconto === 'percentual') {
                $desconto = ($valorPedido * (float) $promocao->valor_desconto) / 100;
                $desconto = min($desconto, $valorPedido);
            } else {
                $desconto = (float) $promocao->valor_desconto;
                $desconto = min($desconto, $valorPedido);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'valido' => true,
                    'id' => (int) $promocao->id,
                    'codigo' => (string) $promocao->codigo,
                    'titulo' => (string) $promocao->titulo,
                    'descricao' => $promocao->descricao ? (string) $promocao->descricao : null,
                    'tipo_desconto' => (string) $promocao->tipo_desconto,
                    'valor_desconto' => (float) $promocao->valor_desconto,
                    'desconto_aplicado' => round($desconto, 2),
                    'valor_minimo' => (float) $promocao->valor_minimo,
                    'validade' => (string) $promocao->validade,
                    'novo_total' => round($valorPedido - $desconto, 2),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao validar cupom: ' . $e->getMessage());
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
                'codigo' => 'required|string|max:50|unique:promocoes,codigo',
                'titulo' => 'required|string|max:255',
                'descricao' => 'nullable|string',
                'tipo_desconto' => 'required|in:percentual,fixo',
                'valor_desconto' => 'required|numeric|min:0',
                'valor_minimo' => 'required|numeric|min:0',
                'validade' => 'required|date|after:today',
                'ativo' => 'sometimes|boolean',
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

            $this->clearPromocaoCache();

            return response()->json([
                'success' => true,
                'message' => 'Promoção criada com sucesso',
                'data' => $this->formatPromocao($promocao)
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erro ao criar promoção: ' . $e->getMessage());
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
                'ativo' => 'sometimes|boolean',
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

            $this->clearPromocaoCache();

            return response()->json([
                'success' => true,
                'message' => 'Promoção atualizada com sucesso',
                'data' => $this->formatPromocao($promocao)
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar promoção: ' . $e->getMessage());
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

            $this->clearPromocaoCache();

            return response()->json([
                'success' => true,
                'message' => 'Promoção removida com sucesso'
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao deletar promoção: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao deletar promoção: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Formatar promoção para JSON com casts corretos
     */
    private function formatPromocao($promocao): array
    {
        if (!$promocao) {
            return [];
        }

        return [
            'id' => (int) $promocao->id,
            'codigo' => (string) $promocao->codigo,
            'titulo' => (string) $promocao->titulo,
            'descricao' => $promocao->descricao ? (string) $promocao->descricao : null,
            'tipo_desconto' => (string) $promocao->tipo_desconto,
            'valor_desconto' => (float) $promocao->valor_desconto,
            'valor_minimo' => (float) ($promocao->valor_minimo ?? 0),
            'validade' => $promocao->validade ? (string) $promocao->validade : null,
            'ativo' => (bool) ($promocao->ativo ?? true),
            'imagem' => $promocao->imagem ? (string) $promocao->imagem : null,
            'created_at' => $promocao->created_at ? $promocao->created_at->toISOString() : null,
            'updated_at' => $promocao->updated_at ? $promocao->updated_at->toISOString() : null,
        ];
    }

    /**
     * Limpar cache de promoções
     */
    private function clearPromocaoCache(): void
    {
        Cache::forget('promocoes_all');
        Cache::forget('promocoes_ativas');

        // Limpar cache de páginas
        for ($page = 1; $page <= 5; $page++) {
            Cache::forget("promocoes_page_{$page}");
        }
    }
}
