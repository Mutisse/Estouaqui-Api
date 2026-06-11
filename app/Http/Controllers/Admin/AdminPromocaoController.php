<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promocao;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class AdminPromocaoController extends Controller
{
    /**
     * Listar todas as promoções com paginação e filtros
     * GET /admin/promocoes
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search');
            $status = $request->input('status');
            $tipoDesconto = $request->input('tipo_desconto');
            $dataInicio = $request->input('data_inicio');
            $dataFim = $request->input('data_fim');

            $query = Promocao::query();

            // Aplicar filtros
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('codigo', 'like', "%{$search}%")
                      ->orWhere('descricao', 'like', "%{$search}%");
                });
            }

            if ($status) {
                $hoje = Carbon::now();
                if ($status === 'ativa') {
                    $query->where('ativo', true)
                          ->where('validade_inicio', '<=', $hoje)
                          ->where('validade_fim', '>=', $hoje);
                } elseif ($status === 'expirada') {
                    $query->where('validade_fim', '<', $hoje);
                } elseif ($status === 'inativa') {
                    $query->where('ativo', false);
                }
            }

            if ($tipoDesconto) {
                $query->where('tipo_desconto', $tipoDesconto);
            }

            if ($dataInicio) {
                $query->whereDate('validade_inicio', '>=', $dataInicio);
            }

            if ($dataFim) {
                $query->whereDate('validade_fim', '<=', $dataFim);
            }

            $promocoes = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $promocoes->items(),
                'current_page' => $promocoes->currentPage(),
                'last_page' => $promocoes->lastPage(),
                'per_page' => $promocoes->perPage(),
                'total' => $promocoes->total(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar promoções: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar uma promoção específica
     * GET /admin/promocoes/{id}
     */
    public function show($id)
    {
        try {
            $promocao = Promocao::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $promocao
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Promoção não encontrada'
            ], 404);
        }
    }

    /**
     * Criar uma nova promoção
     * POST /admin/promocoes
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'codigo' => 'required|string|max:50|unique:promocoes,codigo',
                'descricao' => 'nullable|string',
                'tipo_desconto' => 'required|in:percentual,fixo',
                'valor_desconto' => 'required|numeric|min:0',
                'valor_minimo' => 'numeric|min:0',
                'validade_inicio' => 'required|date',
                'validade_fim' => 'required|date|after_or_equal:validade_inicio',
                'ativo' => 'boolean',
                'uso_maximo' => 'integer|min:0', // CORRIGIDO: uso_maximo
            ]);

            // Validar percentual máximo
            if ($validated['tipo_desconto'] === 'percentual' && $validated['valor_desconto'] > 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Desconto percentual não pode ultrapassar 100%'
                ], 422);
            }

            $promocao = Promocao::create([
                'codigo' => strtoupper($validated['codigo']),
                'descricao' => $validated['descricao'] ?? null,
                'tipo_desconto' => $validated['tipo_desconto'],
                'valor_desconto' => $validated['valor_desconto'],
                'valor_minimo' => $validated['valor_minimo'] ?? 0,
                'validade_inicio' => $validated['validade_inicio'],
                'validade_fim' => $validated['validade_fim'],
                'ativo' => $validated['ativo'] ?? true,
                'uso_maximo' => $validated['uso_maximo'] ?? 0, // CORRIGIDO
                'uso_atual' => 0, // CORRIGIDO
            ]);

            return response()->json([
                'success' => true,
                'data' => $promocao,
                'message' => 'Promoção criada com sucesso'
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
                'message' => 'Erro ao criar promoção: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar uma promoção
     * PUT /admin/promocoes/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $promocao = Promocao::findOrFail($id);

            $validated = $request->validate([
                'codigo' => ['sometimes', 'string', 'max:50', Rule::unique('promocoes')->ignore($id)],
                'descricao' => 'nullable|string',
                'tipo_desconto' => 'sometimes|in:percentual,fixo',
                'valor_desconto' => 'sometimes|numeric|min:0',
                'valor_minimo' => 'numeric|min:0',
                'validade_inicio' => 'sometimes|date',
                'validade_fim' => 'sometimes|date|after_or_equal:validade_inicio',
                'ativo' => 'boolean',
                'uso_maximo' => 'integer|min:0', // CORRIGIDO
            ]);

            // Validar percentual máximo
            if (isset($validated['tipo_desconto']) && $validated['tipo_desconto'] === 'percentual'
                && isset($validated['valor_desconto']) && $validated['valor_desconto'] > 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Desconto percentual não pode ultrapassar 100%'
                ], 422);
            }

            if (isset($validated['codigo'])) {
                $validated['codigo'] = strtoupper($validated['codigo']);
            }

            $promocao->update($validated);

            return response()->json([
                'success' => true,
                'data' => $promocao,
                'message' => 'Promoção atualizada com sucesso'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar promoção: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar uma promoção
     * DELETE /admin/promocoes/{id}
     */
    public function destroy($id)
    {
        try {
            $promocao = Promocao::findOrFail($id);
            $promocao->delete();

            return response()->json([
                'success' => true,
                'message' => 'Promoção excluída com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir promoção: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Alternar status da promoção (ativar/inativar)
     * PUT /admin/promocoes/{id}/status
     */
    public function alternarStatus(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'ativo' => 'required|boolean'
            ]);

            $promocao = Promocao::findOrFail($id);
            $promocao->ativo = $validated['ativo'];
            $promocao->save();

            return response()->json([
                'success' => true,
                'data' => $promocao,
                'message' => $validated['ativo'] ? 'Promoção ativada com sucesso' : 'Promoção desativada com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao alternar status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estatísticas de promoções
     * GET /admin/promocoes/estatisticas
     */
    public function estatisticas()
    {
        try {
            $hoje = Carbon::now();

            $estatisticas = [
                'total' => Promocao::count(),
                'ativas' => Promocao::where('ativo', true)
                    ->where('validade_inicio', '<=', $hoje)
                    ->where('validade_fim', '>=', $hoje)
                    ->count(),
                'expiradas' => Promocao::where('validade_fim', '<', $hoje)->count(),
                'uso_total' => Promocao::sum('uso_atual'), // CORRIGIDO: uso_atual
            ];

            return response()->json([
                'success' => true,
                'data' => $estatisticas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar estatísticas: ' . $e->getMessage()
            ], 500);
        }
    }
}
