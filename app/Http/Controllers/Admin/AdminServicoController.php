<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Servico;
use App\Models\Categoria;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminServicoController extends Controller
{
    /**
     * Cache time (5 minutes)
     */
    private const CACHE_TIME = 300;
    private const PER_PAGE_DEFAULT = 15;

    /**
     * Listar todos os serviços com paginação e filtros (OTIMIZADO)
     * GET /admin/servicos
     */
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->input('per_page', self::PER_PAGE_DEFAULT);

            $query = Servico::with(['categoria:id,nome', 'prestador:id,nome,email'])
                ->select(['id', 'nome', 'descricao', 'preco', 'duracao', 'categoria_id', 'prestador_id', 'ativo', 'created_at', 'updated_at']);

            $this->applyFilters($query, $request);

            $servicos = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Converter para array
            $items = [];
            foreach ($servicos as $servico) {
                $item = $servico->toArray();
                $item['preco'] = (float) $servico->preco;
                $item['categoria'] = $servico->categoria ? $servico->categoria->toArray() : null;
                $item['prestador'] = $servico->prestador ? $servico->prestador->toArray() : null;
                $items[] = $item;
            }

            return response()->json([
                'success' => true,
                'data' => $items,
                'current_page' => $servicos->currentPage(),
                'last_page' => $servicos->lastPage(),
                'per_page' => $servicos->perPage(),
                'total' => $servicos->total(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar serviços: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar um serviço específico
     * GET /admin/servicos/{id}
     */
    public function show($id)
    {
        try {
            $servico = Servico::with(['categoria:id,nome', 'prestador:id,nome,email,telefone'])
                ->findOrFail($id);

            return response()->json(['success' => true, 'data' => $servico]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Serviço não encontrado'
            ], 404);
        }
    }

    /**
     * Criar um novo serviço (COM NOTIFICAÇÃO)
     * POST /admin/servicos
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nome' => 'required|string|max:255',
                'descricao' => 'nullable|string',
                'categoria_id' => 'required|exists:categorias,id',
                'duracao' => 'required|integer|min:15',
                'preco' => 'required|numeric|min:0',
                'ativo' => 'boolean',
                'prestador_id' => 'nullable|exists:users,id',
            ]);

            $servico = Servico::create([
                'nome' => $validated['nome'],
                'descricao' => $validated['descricao'] ?? null,
                'categoria_id' => $validated['categoria_id'],
                'duracao' => $validated['duracao'],
                'preco' => $validated['preco'],
                'ativo' => $validated['ativo'] ?? true,
                'prestador_id' => $validated['prestador_id'] ?? null,
            ]);

            // 🔔 NOTIFICAÇÃO para o prestador (se existir)
            if ($servico->prestador_id) {
                NotificationService::send(
                    'servico.criado',
                    $servico->prestador_id,
                    ['nome' => $servico->nome]
                );
            }

            return response()->json([
                'success' => true,
                'data' => $servico,
                'message' => 'Serviço criado com sucesso'
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
                'message' => 'Erro ao criar serviço: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar um serviço (COM NOTIFICAÇÃO)
     * PUT /admin/servicos/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $servico = Servico::with('prestador')->findOrFail($id);
            $dadosAntigos = $servico->getOriginal();

            $validated = $request->validate([
                'nome' => 'sometimes|string|max:255',
                'descricao' => 'nullable|string',
                'categoria_id' => 'sometimes|exists:categorias,id',
                'duracao' => 'sometimes|integer|min:15',
                'preco' => 'sometimes|numeric|min:0',
                'ativo' => 'boolean',
            ]);

            $servico->update($validated);

            // 🔔 NOTIFICAÇÃO se houve mudança significativa e tem prestador
            if ($servico->prestador_id && ($dadosAntigos['nome'] !== $servico->nome || $dadosAntigos['preco'] != $servico->preco)) {
                NotificationService::send(
                    'servico.atualizado',
                    $servico->prestador_id,
                    ['nome' => $servico->nome]
                );
            }

            return response()->json([
                'success' => true,
                'data' => $servico,
                'message' => 'Serviço atualizado com sucesso'
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
                'message' => 'Erro ao atualizar serviço: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar um serviço (COM NOTIFICAÇÃO)
     * DELETE /admin/servicos/{id}
     */
    public function destroy($id)
    {
        try {
            $servico = Servico::with('prestador')->findOrFail($id);
            $nomeServico = $servico->nome;
            $prestadorId = $servico->prestador_id;

            $servico->delete();

            // 🔔 NOTIFICAÇÃO para o prestador
            if ($prestadorId) {
                NotificationService::send(
                    'servico.removido',
                    $prestadorId,
                    ['nome' => $nomeServico]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Serviço excluído com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir serviço: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Alternar status do serviço (COM NOTIFICAÇÃO)
     * PUT /admin/servicos/{id}/status
     */
    public function alternarStatus(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'ativo' => 'required|boolean'
            ]);

            $servico = Servico::with('prestador')->findOrFail($id);
            $servico->ativo = $validated['ativo'];
            $servico->save();

            // 🔔 NOTIFICAÇÃO para o prestador
            if ($servico->prestador_id) {
                $evento = $validated['ativo'] ? 'servico.ativado' : 'servico.desativado';
                NotificationService::send(
                    $evento,
                    $servico->prestador_id,
                    ['nome' => $servico->nome]
                );
            }

            return response()->json([
                'success' => true,
                'data' => $servico,
                'message' => $validated['ativo'] ? 'Serviço ativado com sucesso' : 'Serviço desativado com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao alternar status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estatísticas de serviços
     * GET /admin/servicos/estatisticas
     */
    public function estatisticas()
    {
        $estatisticas = Cache::remember('servicos_estatisticas', 300, function () {
            return [
                'total' => Servico::count(),
                'ativos' => Servico::where('ativo', true)->count(),
                'inativos' => Servico::where('ativo', false)->count(),
                'preco_medio' => (float) Servico::avg('preco') ?? 0,
                'duracao_media' => (float) Servico::avg('duracao') ?? 0,
                'total_prestadores' => Servico::distinct('prestador_id')->count('prestador_id'),
            ];
        });

        return response()->json(['success' => true, 'data' => $estatisticas]);
    }

    // ==================== MÉTODOS PRIVADOS ====================

    /**
     * Aplica filtros de forma eficiente
     */
    private function applyFilters($query, Request $request): void
    {
        $search = $request->input('search');
        $categoriaId = $request->input('categoria_id');
        $status = $request->input('status');
        $prestadorId = $request->input('prestador_id');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('descricao', 'like', "%{$search}%");
            });
        }

        if ($categoriaId) {
            $query->where('categoria_id', $categoriaId);
        }

        if ($status === 'ativo') {
            $query->where('ativo', true);
        } elseif ($status === 'inativo') {
            $query->where('ativo', false);
        }

        if ($prestadorId) {
            $query->where('prestador_id', $prestadorId);
        }
    }
}
