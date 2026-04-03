<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CategoriaController extends Controller
{
    /**
     * Listar todas as categorias (admin) - COM CACHE E TOARRAY
     * GET /api/admin/categorias
     */
    public function index(Request $request)
    {
        $cacheKey = 'admin_categorias_' . md5($request->fullUrl());

        $categorias = Cache::remember($cacheKey, 600, function() use ($request) {
            $query = Categoria::query();

            if ($request->has('ativo')) {
                $query->where('ativo', $request->ativo);
            }

            return $query->withCount('servicos')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($categoria) {
                    return $this->formatCategoria($categoria);
                })
                ->toArray(); // ✅ CONVERTER PARA ARRAY
        });

        return response()->json([
            'success' => true,
            'data' => $categorias
        ]);
    }

    /**
     * Listar categorias públicas (ativas) - COM CACHE E TOARRAY
     * GET /api/prestadores/categorias
     */
    public function publicas()
    {
        $categorias = Cache::remember('categorias_publicas', 3600, function() {
            return Categoria::where('ativo', true)
                ->withCount('servicos')
                ->orderBy('nome', 'asc')
                ->get()
                ->map(function ($categoria) {
                    return $this->formatCategoria($categoria);
                })
                ->toArray(); // ✅ CONVERTER PARA ARRAY
        });

        return response()->json([
            'success' => true,
            'data' => $categorias
        ]);
    }

    /**
     * Criar nova categoria - LIMPAR CACHE
     * POST /api/admin/categorias
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:255|unique:categorias',
            'descricao' => 'nullable|string',
            'icone' => 'nullable|string',
            'cor' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            $categoria = Categoria::create([
                'nome' => $request->nome,
                'slug' => Str::slug($request->nome),
                'descricao' => $request->descricao,
                'icone' => $request->icone ?? 'category',
                'cor' => $request->cor ?? 'primary',
                'ativo' => true,
            ]);

            $this->clearCategoriaCache();

            // ✅ RETORNAR ARRAY COM CASTS
            return response()->json([
                'success' => true,
                'message' => 'Categoria criada com sucesso',
                'data' => $this->formatCategoria($categoria)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar categoria: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar uma categoria - COM CACHE E TOARRAY
     * GET /api/admin/categorias/{id}
     */
    public function show($id)
    {
        $cacheKey = "categoria_{$id}";

        $categoria = Cache::remember($cacheKey, 3600, function() use ($id) {
            return Categoria::with(['servicos' => function($query) {
                $query->select('id', 'nome', 'preco', 'duracao', 'categoria_id');
            }])->find($id);
        });

        if (!$categoria) {
            return response()->json([
                'success' => false,
                'error' => 'Categoria não encontrada'
            ], 404);
        }

        // ✅ RETORNAR ARRAY COM CASTS
        return response()->json([
            'success' => true,
            'data' => $this->formatCategoria($categoria, true)
        ]);
    }

    /**
     * Atualizar categoria - LIMPAR CACHE
     * PUT /api/admin/categorias/{id}
     */
    public function update(Request $request, $id)
    {
        $categoria = Categoria::find($id);

        if (!$categoria) {
            return response()->json([
                'success' => false,
                'error' => 'Categoria não encontrada'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nome' => 'sometimes|string|max:255|unique:categorias,nome,' . $id,
            'descricao' => 'nullable|string',
            'icone' => 'nullable|string',
            'cor' => 'nullable|string',
            'ativo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            if ($request->has('nome')) {
                $categoria->nome = $request->nome;
                $categoria->slug = Str::slug($request->nome);
            }
            if ($request->has('descricao')) $categoria->descricao = $request->descricao;
            if ($request->has('icone')) $categoria->icone = $request->icone;
            if ($request->has('cor')) $categoria->cor = $request->cor;
            if ($request->has('ativo')) $categoria->ativo = $request->ativo;

            $categoria->save();

            $this->clearCategoriaCache();

            // ✅ RETORNAR ARRAY COM CASTS
            return response()->json([
                'success' => true,
                'message' => 'Categoria atualizada com sucesso',
                'data' => $this->formatCategoria($categoria)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar categoria: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deletar categoria - LIMPAR CACHE
     * DELETE /api/admin/categorias/{id}
     */
    public function destroy($id)
    {
        $categoria = Categoria::find($id);

        if (!$categoria) {
            return response()->json([
                'success' => false,
                'error' => 'Categoria não encontrada'
            ], 404);
        }

        $categoria->delete();

        $this->clearCategoriaCache();

        return response()->json([
            'success' => true,
            'message' => 'Categoria removida com sucesso'
        ]);
    }

    /**
     * Formatar categoria com casts corretos
     */
    private function formatCategoria($categoria, $withServicos = false)
    {
        $data = [
            'id' => (int) $categoria->id,
            'nome' => (string) $categoria->nome,
            'slug' => (string) $categoria->slug,
            'descricao' => $categoria->descricao ? (string) $categoria->descricao : null,
            'icone' => (string) ($categoria->icone ?? 'category'),
            'cor' => (string) ($categoria->cor ?? 'primary'),
            'ativo' => (bool) $categoria->ativo,
            'servicos_count' => (int) ($categoria->servicos_count ?? 0),
            'created_at' => $categoria->created_at ? $categoria->created_at->toISOString() : null,
            'updated_at' => $categoria->updated_at ? $categoria->updated_at->toISOString() : null,
        ];

        // Incluir serviços se solicitado
        if ($withServicos && $categoria->servicos) {
            $data['servicos'] = $categoria->servicos->map(function ($servico) {
                return [
                    'id' => (int) $servico->id,
                    'nome' => (string) $servico->nome,
                    'preco' => (float) $servico->preco,
                    'duracao' => (int) $servico->duracao,
                ];
            })->toArray();
        }

        return $data;
    }

    /**
     * Limpar cache de categorias
     */
    private function clearCategoriaCache()
    {
        Cache::forget('categorias_publicas');

        // Limpar cache de admin (várias páginas)
        for ($page = 1; $page <= 5; $page++) {
            Cache::forget("admin_categorias_page_{$page}");
        }

        // Limpar cache de categorias específicas (padrão)
        // Não podemos limpar todas individualmente, mas podemos limpar por padrão
        // Em um ambiente de produção, seria melhor usar Redis com tags
    }
}
