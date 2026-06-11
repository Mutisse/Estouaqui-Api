<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class AdminUtilizadorController extends Controller
{
    /**
     * Cache time for user lists (5 minutes)
     */
    private const CACHE_TIME = 300;
    private const PER_PAGE_DEFAULT = 15;

    /**
     * Listar todos os utilizadores com paginação e filtros (OTIMIZADO)
     * GET /admin/utilizadores
     */
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->input('per_page', self::PER_PAGE_DEFAULT);
            $page = (int) $request->input('page', 1);

            // Criar chave de cache única baseada nos filtros
            $cacheKey = $this->getCacheKey($request);

            // Tentar buscar do cache
            $result = Cache::remember($cacheKey, self::CACHE_TIME, function () use ($request, $perPage) {
                $query = User::query();

                // Aplicar filtros de forma eficiente
                $this->applyFilters($query, $request);

                // Selecionar apenas colunas necessárias
                $query->select(['id', 'nome', 'email', 'telefone', 'tipo', 'verificado', 'disponivel', 'created_at', 'profissao', 'media_avaliacao']);

                // Paginar
                $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

                return [
                    'data' => $paginated->items(),
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ];
            });

            return response()->json(['success' => true, ...$result]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar utilizadores: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar um utilizador específico (COM CACHE)
     * GET /admin/utilizadores/{id}
     */
    public function show($id)
    {
        try {
            $cacheKey = "user_{$id}";

            $utilizador = Cache::remember($cacheKey, self::CACHE_TIME, function () use ($id) {
                return User::select(['id', 'nome', 'email', 'telefone', 'tipo', 'verificado', 'disponivel', 'created_at', 'profissao', 'sobre', 'media_avaliacao', 'total_avaliacoes', 'raio_atendimento'])
                    ->findOrFail($id);
            });

            return response()->json(['success' => true, 'data' => $utilizador]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Utilizador não encontrado'
            ], 404);
        }
    }

    /**
     * Criar um novo utilizador (INVALIDA CACHE)
     * POST /admin/utilizadores
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nome' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'telefone' => 'nullable|string|max:20',
                'tipo' => 'required|in:cliente,prestador,admin,root',
                'password' => 'required|string|min:6',
                'profissao' => 'nullable|string|max:255',
                'sobre' => 'nullable|string',
            ]);

            $utilizador = User::create([
                'nome' => $validated['nome'],
                'email' => $validated['email'],
                'telefone' => $validated['telefone'] ?? null,
                'tipo' => $validated['tipo'],
                'password' => Hash::make($validated['password']),
                'profissao' => $validated['profissao'] ?? null,
                'sobre' => $validated['sobre'] ?? null,
                'verificado' => in_array($validated['tipo'], ['admin', 'root']),
                'disponivel' => true,
                'media_avaliacao' => 0,
                'total_avaliacoes' => 0,
                'raio_atendimento' => 10,
            ]);

            // Invalidar caches relacionados
            $this->invalidateUserCaches();

            return response()->json([
                'success' => true,
                'data' => $utilizador,
                'message' => 'Utilizador criado com sucesso'
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
                'message' => 'Erro ao criar utilizador: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar um utilizador (INVALIDA CACHE)
     * PUT /admin/utilizadores/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $utilizador = User::findOrFail($id);

            $validated = $request->validate([
                'nome' => 'sometimes|string|max:255',
                'email' => ['sometimes', 'email', Rule::unique('users')->ignore($id)],
                'telefone' => 'nullable|string|max:20',
                'tipo' => 'sometimes|in:cliente,prestador,admin,root',
                'profissao' => 'nullable|string|max:255',
                'sobre' => 'nullable|string',
                'password' => 'nullable|string|min:6',
            ]);

            // Atualizar apenas campos enviados (mais rápido)
            $updatableFields = ['nome', 'email', 'telefone', 'tipo', 'profissao', 'sobre'];
            foreach ($updatableFields as $field) {
                if (array_key_exists($field, $validated)) {
                    $utilizador->$field = $validated[$field];
                }
            }

            if (isset($validated['password'])) {
                $utilizador->password = Hash::make($validated['password']);
            }

            $utilizador->save();

            // Invalidar caches
            $this->invalidateUserCaches($id);

            return response()->json([
                'success' => true,
                'data' => $utilizador,
                'message' => 'Utilizador atualizado com sucesso'
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
                'message' => 'Erro ao atualizar utilizador: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar um utilizador (INVALIDA CACHE)
     * DELETE /admin/utilizadores/{id}
     */
    public function destroy($id)
    {
        try {
            $utilizador = User::findOrFail($id);
            $currentUser = Auth::user();

            if ($currentUser && $utilizador->id === $currentUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não pode excluir o seu próprio utilizador'
                ], 403);
            }

            $utilizador->delete();

            // Invalidar caches
            $this->invalidateUserCaches($id);

            return response()->json([
                'success' => true,
                'message' => 'Utilizador excluído com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir utilizador: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar um prestador (INVALIDA CACHE)
     * PUT /admin/utilizadores/{id}/verificar
     */
    public function verificar($id)
    {
        try {
            $utilizador = User::findOrFail($id);

            if ($utilizador->tipo !== 'prestador') {
                return response()->json([
                    'success' => false,
                    'message' => 'Apenas prestadores podem ser verificados'
                ], 422);
            }

            $utilizador->verificado = true;
            $utilizador->save();

            // Invalidar caches
            $this->invalidateUserCaches($id);

            return response()->json([
                'success' => true,
                'data' => $utilizador,
                'message' => 'Prestador verificado com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao verificar prestador: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bloquear um utilizador (INVALIDA CACHE)
     * PUT /admin/utilizadores/{id}/bloquear
     */
    public function bloquear($id)
    {
        try {
            $utilizador = User::findOrFail($id);
            $currentUser = Auth::user();

            if ($currentUser && $utilizador->id === $currentUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não pode bloquear o seu próprio utilizador'
                ], 403);
            }

            $utilizador->disponivel = false;
            $utilizador->save();

            // Invalidar caches
            $this->invalidateUserCaches($id);

            return response()->json([
                'success' => true,
                'data' => $utilizador,
                'message' => 'Utilizador bloqueado com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao bloquear utilizador: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Desbloquear um utilizador (INVALIDA CACHE)
     * PUT /admin/utilizadores/{id}/desbloquear
     */
    public function desbloquear($id)
    {
        try {
            $utilizador = User::findOrFail($id);
            $utilizador->disponivel = true;
            $utilizador->save();

            // Invalidar caches
            $this->invalidateUserCaches($id);

            return response()->json([
                'success' => true,
                'data' => $utilizador,
                'message' => 'Utilizador desbloqueado com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao desbloquear utilizador: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== MÉTODOS PRIVADOS OTIMIZADOS ====================

    /**
     * Aplica filtros de forma eficiente
     */
    private function applyFilters($query, Request $request): void
    {
        $search = $request->input('search');
        $tipo = $request->input('tipo');
        $verificado = $request->input('verificado');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
                if (is_numeric($search)) {
                    $q->orWhere('id', $search);
                }
            });
        }

        if ($tipo && in_array($tipo, ['cliente', 'prestador', 'admin', 'root'])) {
            $query->where('tipo', $tipo);
        }

        if ($verificado !== null && $verificado !== '') {
            $query->where('verificado', filter_var($verificado, FILTER_VALIDATE_BOOLEAN));
        }
    }

    /**
     * Gera chave de cache única baseada nos filtros
     */
    private function getCacheKey(Request $request): string
    {
        $params = [
            'per_page' => $request->input('per_page', self::PER_PAGE_DEFAULT),
            'page' => $request->input('page', 1),
            'search' => $request->input('search', ''),
            'tipo' => $request->input('tipo', ''),
            'verificado' => $request->input('verificado', ''),
        ];

        return 'admin_users_' . md5(json_encode($params));
    }

    /**
     * Invalida todos os caches relacionados a utilizadores
     */
    private function invalidateUserCaches(?int $userId = null): void
    {
        // Invalidar cache específico do usuário
        if ($userId) {
            Cache::forget("user_{$userId}");
        }

        // Invalidar cache de listas (usando padrão)
        $keys = Cache::get('admin_users_keys', []);
        foreach ($keys as $key) {
            Cache::forget($key);
        }

        Cache::forget('admin_users_keys');

        // Invalidar estatísticas do dashboard
        Cache::forget('admin_dashboard_stats');
        Cache::forget('admin_dashboard_atividade');
    }
}
