<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCategoriaController extends Controller
{
    /**
     * Listar todas as categorias (incluindo inativas)
     * GET /admin/categorias
     */
    public function index()
    {
        try {
            $categorias = Categoria::orderBy('ordem', 'asc')
                ->orderBy('created_at', 'desc')
                ->get();

            // Adicionar contagem de serviços
            foreach ($categorias as $categoria) {
                $categoria->servicos_count = $categoria->servicos()->count();
            }

            return response()->json([
                'success' => true,
                'data' => $categorias
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar categorias: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar uma categoria específica
     * GET /admin/categorias/{id}
     */
    public function show($id)
    {
        try {
            $categoria = Categoria::findOrFail($id);
            $categoria->servicos_count = $categoria->servicos()->count();

            return response()->json([
                'success' => true,
                'data' => $categoria
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Categoria não encontrada'
            ], 404);
        }
    }

    /**
     * Criar uma nova categoria
     * POST /admin/categorias
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nome' => 'required|string|max:255|unique:categorias,nome',
                'icone' => 'nullable|string|max:100',
                'cor' => 'nullable|string|max:20',
                'descricao' => 'nullable|string',
                'ativo' => 'boolean',
                'slug' => 'nullable|string|unique:categorias,slug',
            ]);

            // Gerar slug se não foi enviado
            if (empty($validated['slug'])) {
                $validated['slug'] = $this->gerarSlug($validated['nome']);
            }

            // Definir ordem padrão (última)
            $ultimaOrdem = Categoria::max('ordem') ?? 0;
            $validated['ordem'] = $ultimaOrdem + 1;

            // Valores padrão
            $validated['icone'] = $validated['icone'] ?? 'category';
            $validated['cor'] = $validated['cor'] ?? '#667EEA';
            $validated['ativo'] = $validated['ativo'] ?? true;

            $categoria = Categoria::create($validated);

            return response()->json([
                'success' => true,
                'data' => $categoria,
                'message' => 'Categoria criada com sucesso'
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
                'message' => 'Erro ao criar categoria: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar uma categoria
     * PUT /admin/categorias/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $categoria = Categoria::findOrFail($id);

            $validated = $request->validate([
                'nome' => ['sometimes', 'string', 'max:255', Rule::unique('categorias')->ignore($id)],
                'icone' => 'nullable|string|max:100',
                'cor' => 'nullable|string|max:20',
                'descricao' => 'nullable|string',
                'ativo' => 'boolean',
            ]);

            // Se o nome foi alterado, atualizar o slug
            if (isset($validated['nome']) && $validated['nome'] !== $categoria->nome) {
                $validated['slug'] = $this->gerarSlug($validated['nome']);
            }

            $categoria->update($validated);

            return response()->json([
                'success' => true,
                'data' => $categoria,
                'message' => 'Categoria atualizada com sucesso'
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
                'message' => 'Erro ao atualizar categoria: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar uma categoria
     * DELETE /admin/categorias/{id}
     */
    public function destroy($id)
    {
        try {
            $categoria = Categoria::findOrFail($id);

            // Verificar se a categoria tem serviços associados
            $servicosCount = $categoria->servicos()->count();
            if ($servicosCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Não é possível excluir a categoria pois ela possui {$servicosCount} serviço(s) associado(s)"
                ], 422);
            }

            $categoria->delete();

            return response()->json([
                'success' => true,
                'message' => 'Categoria excluída com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir categoria: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Alternar status da categoria (ativar/inativar)
     * PUT /admin/categorias/{id}/status
     */
    public function alternarStatus(Request $request, $id)
    {
        try {
            $categoria = Categoria::findOrFail($id);
            $validated = $request->validate([
                'ativo' => 'required|boolean'
            ]);

            $categoria->ativo = $validated['ativo'];
            $categoria->save();

            return response()->json([
                'success' => true,
                'data' => $categoria,
                'message' => $validated['ativo'] ? 'Categoria ativada com sucesso' : 'Categoria desativada com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao alternar status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reordenar categorias
     * POST /admin/categorias/reordenar
     */
    public function reordenar(Request $request)
    {
        try {
            $validated = $request->validate([
                'ordens' => 'required|array',
                'ordens.*.id' => 'required|integer|exists:categorias,id',
                'ordens.*.ordem' => 'required|integer|min:0'
            ]);

            foreach ($validated['ordens'] as $item) {
                Categoria::where('id', $item['id'])->update(['ordem' => $item['ordem']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Ordem atualizada com sucesso'
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
                'message' => 'Erro ao reordenar categorias: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gerar slug a partir do nome
     */
    private function gerarSlug($nome)
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $nome);
        $slug = strtolower(trim($slug, '-'));

        // Garantir que o slug é único
        $originalSlug = $slug;
        $counter = 1;
        while (Categoria::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        return $slug;
    }
}
