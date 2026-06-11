<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class AdminPrestadorController extends Controller
{
    /**
     * Cache time (5 minutes)
     */
    private const CACHE_TIME = 300;
    private const PER_PAGE_DEFAULT = 15;

    /**
     * Listar todos os prestadores com paginação e filtros (OTIMIZADO)
     * GET /admin/prestadores
     */
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->input('per_page', self::PER_PAGE_DEFAULT);
            $page = (int) $request->input('page', 1);

            // Criar chave de cache única
            $cacheKey = $this->getCacheKey($request);

            $result = Cache::remember($cacheKey, self::CACHE_TIME, function () use ($request, $perPage) {
                $query = User::where('tipo', 'prestador')
                    ->select(['id', 'nome', 'email', 'telefone', 'profissao', 'verificado', 'disponivel', 'media_avaliacao', 'total_avaliacoes', 'created_at', 'latitude', 'longitude', 'raio_atendimento']);

                // Aplicar filtros
                $this->applyFilters($query, $request);

                $prestadores = $query->orderBy('created_at', 'desc')->paginate($perPage);

                // Garantir que media_avaliacao seja número (já no select)
                $items = $prestadores->items();
                foreach ($items as $prestador) {
                    $prestador->media_avaliacao = (float) $prestador->media_avaliacao;
                }

                return [
                    'data' => $items,
                    'current_page' => $prestadores->currentPage(),
                    'last_page' => $prestadores->lastPage(),
                    'per_page' => $prestadores->perPage(),
                    'total' => $prestadores->total(),
                ];
            });

            return response()->json(['success' => true, ...$result]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar prestadores: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar um prestador específico (COM CACHE)
     * GET /admin/prestadores/{id}
     */
    public function show($id)
    {
        try {
            $cacheKey = "prestador_{$id}";

            $prestador = Cache::remember($cacheKey, self::CACHE_TIME, function () use ($id) {
                $prestador = User::where('tipo', 'prestador')
                    ->select(['id', 'nome', 'email', 'telefone', 'profissao', 'sobre', 'verificado', 'disponivel', 'media_avaliacao', 'total_avaliacoes', 'created_at', 'latitude', 'longitude', 'raio_atendimento'])
                    ->find($id);

                if (!$prestador) {
                    throw new \Exception('Prestador não encontrado');
                }

                $prestador->media_avaliacao = (float) $prestador->media_avaliacao;
                return $prestador;
            });

            return response()->json(['success' => true, 'data' => $prestador]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() === 'Prestador não encontrado' ? 'Prestador não encontrado' : 'Erro ao buscar prestador'
            ], 404);
        }
    }

    /**
     * Criar um novo prestador (INVALIDA CACHE)
     * POST /admin/prestadores
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nome' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'telefone' => 'nullable|string|max:20',
                'profissao' => 'required|string|max:255',
                'sobre' => 'nullable|string',
                'password' => 'required|string|min:6',
            ]);

            $prestador = User::create([
                'nome' => $validated['nome'],
                'email' => $validated['email'],
                'telefone' => $validated['telefone'] ?? null,
                'tipo' => 'prestador',
                'password' => bcrypt($validated['password']),
                'profissao' => $validated['profissao'],
                'sobre' => $validated['sobre'] ?? null,
                'verificado' => false,
                'disponivel' => true,
                'media_avaliacao' => 0,
                'total_avaliacoes' => 0,
                'raio_atendimento' => 10,
            ]);

            // Invalidar caches
            $this->invalidateCaches();

            return response()->json([
                'success' => true,
                'data' => $prestador,
                'message' => 'Prestador criado com sucesso'
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
                'message' => 'Erro ao criar prestador: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar um prestador (INVALIDA CACHE)
     * PUT /admin/prestadores/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $prestador = User::where('tipo', 'prestador')->find($id);

            if (!$prestador) {
                return response()->json([
                    'success' => false,
                    'message' => 'Prestador não encontrado'
                ], 404);
            }

            $validated = $request->validate([
                'nome' => 'sometimes|string|max:255',
                'email' => ['sometimes', 'email', Rule::unique('users')->ignore($id)],
                'telefone' => 'nullable|string|max:20',
                'profissao' => 'sometimes|string|max:255',
                'sobre' => 'nullable|string',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'raio_atendimento' => 'nullable|integer|min:1|max:100',
            ]);

            // Atualizar apenas campos enviados
            $updatableFields = ['nome', 'email', 'telefone', 'profissao', 'sobre', 'latitude', 'longitude', 'raio_atendimento'];
            foreach ($updatableFields as $field) {
                if (array_key_exists($field, $validated)) {
                    $prestador->$field = $validated[$field];
                }
            }

            $prestador->save();

            // Invalidar caches
            $this->invalidateCaches($id);

            return response()->json([
                'success' => true,
                'data' => $prestador,
                'message' => 'Prestador atualizado com sucesso'
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
                'message' => 'Erro ao atualizar prestador: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar um prestador (INVALIDA CACHE)
     * DELETE /admin/prestadores/{id}
     */
    public function destroy($id)
    {
        try {
            $prestador = User::where('tipo', 'prestador')->find($id);

            if (!$prestador) {
                return response()->json([
                    'success' => false,
                    'message' => 'Prestador não encontrado'
                ], 404);
            }

            $prestador->delete();

            // Invalidar caches
            $this->invalidateCaches($id);

            return response()->json([
                'success' => true,
                'message' => 'Prestador excluído com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir prestador: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar um prestador (INVALIDA CACHE)
     * PUT /admin/prestadores/{id}/verificar
     */
    public function verificar($id)
    {
        try {
            $prestador = User::where('tipo', 'prestador')->find($id);

            if (!$prestador) {
                return response()->json([
                    'success' => false,
                    'message' => 'Prestador não encontrado'
                ], 404);
            }

            $prestador->verificado = true;
            $prestador->save();

            // Invalidar caches
            $this->invalidateCaches($id);

            return response()->json([
                'success' => true,
                'data' => $prestador,
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
     * Ativar um prestador (INVALIDA CACHE)
     * PUT /admin/prestadores/{id}/ativar
     */
    public function ativar($id)
    {
        try {
            $prestador = User::where('tipo', 'prestador')->find($id);

            if (!$prestador) {
                return response()->json([
                    'success' => false,
                    'message' => 'Prestador não encontrado'
                ], 404);
            }

            $prestador->disponivel = true;
            $prestador->save();

            // Invalidar caches
            $this->invalidateCaches($id);

            return response()->json([
                'success' => true,
                'data' => $prestador,
                'message' => 'Prestador ativado com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao ativar prestador: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Desativar um prestador (INVALIDA CACHE)
     * PUT /admin/prestadores/{id}/desativar
     */
    public function desativar($id)
    {
        try {
            $prestador = User::where('tipo', 'prestador')->find($id);

            if (!$prestador) {
                return response()->json([
                    'success' => false,
                    'message' => 'Prestador não encontrado'
                ], 404);
            }

            $prestador->disponivel = false;
            $prestador->save();

            // Invalidar caches
            $this->invalidateCaches($id);

            return response()->json([
                'success' => true,
                'data' => $prestador,
                'message' => 'Prestador desativado com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao desativar prestador: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar todas as profissões distintas (COM CACHE)
     * GET /admin/prestadores/profissoes
     */
    public function profissoes()
    {
        try {
            $profissoes = Cache::remember('prestador_profissoes', self::CACHE_TIME, function () {
                return User::where('tipo', 'prestador')
                    ->whereNotNull('profissao')
                    ->where('profissao', '!=', '')
                    ->distinct()
                    ->pluck('profissao')
                    ->values();
            });

            return response()->json([
                'success' => true,
                'data' => $profissoes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar profissões: ' . $e->getMessage()
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
        $verificado = $request->input('verificado');
        $profissao = $request->input('profissao');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('profissao', 'like', "%{$search}%");
            });
        }

        if ($verificado !== null && $verificado !== '') {
            $query->where('verificado', filter_var($verificado, FILTER_VALIDATE_BOOLEAN));
        }

        if ($profissao) {
            $query->where('profissao', $profissao);
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
            'verificado' => $request->input('verificado', ''),
            'profissao' => $request->input('profissao', ''),
        ];

        return 'prestadores_' . md5(json_encode($params));
    }

    /**
     * Invalida todos os caches relacionados a prestadores
     */
    private function invalidateCaches(?int $prestadorId = null): void
    {
        // Invalidar cache específico do prestador
        if ($prestadorId) {
            Cache::forget("prestador_{$prestadorId}");
        }

        // Invalidar cache de profissões
        Cache::forget('prestador_profissoes');

        // Invalidar caches de listas (usando padrão)
        $keys = Cache::get('prestadores_keys', []);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        Cache::forget('prestadores_keys');

        // Invalidar estatísticas do dashboard
        Cache::forget('admin_dashboard_stats');
    }
}
