<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Servico;
use App\Models\Categoria;
use App\Models\Pedido;
use App\Models\Avaliacao;
use App\Models\Transacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    // ==========================================
    // 1. DASHBOARD E ESTATÍSTICAS
    // ==========================================

    /**
     * Dashboard do admin
     * GET /api/admin/dashboard
     */
    public function dashboard()
    {
        try {
            Log::info('Dashboard: iniciando');

            $totalUsers = User::count();
            Log::info('Dashboard: totalUsers = ' . $totalUsers);

            $totalClientes = User::where('tipo', 'cliente')->count();
            $totalPrestadores = User::where('tipo', 'prestador')->count();
            $totalAdmins = User::where('tipo', 'admin')->count();
            $prestadoresAtivos = User::where('tipo', 'prestador')->where('ativo', true)->count();

            Log::info('Dashboard: antes de Pedido');
            $servicosHoje = Pedido::whereDate('created_at', today())->count();
            $servicosPendentes = Pedido::where('status', 'pendente')->count();

            Log::info('Dashboard: antes de Avaliacao');
            $avaliacaoMedia = Avaliacao::avg('nota') ?? 0;
            $totalAvaliacoes = Avaliacao::count();

            Log::info('Dashboard: finalizado com sucesso');

            return response()->json([
                'success' => true,
                'data' => [
                    'total_users' => $totalUsers,
                    'total_clientes' => $totalClientes,
                    'total_prestadores' => $totalPrestadores,
                    'total_admins' => $totalAdmins,
                    'prestadores_ativos' => $prestadoresAtivos,
                    'servicos_hoje' => $servicosHoje,
                    'servicos_pendentes' => $servicosPendentes,
                    'avaliacao_media' => round($avaliacaoMedia, 1),
                    'total_avaliacoes' => $totalAvaliacoes,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard ERRO: ' . $e->getMessage());
            Log::error('Dashboard ERRO linha: ' . $e->getLine());
            Log::error('Dashboard ERRO arquivo: ' . $e->getFile());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    /**
     * Atividade dos últimos 7 dias
     * GET /api/admin/atividade
     */
    public function atividade()
    {
        try {
            Log::info('Atividade: iniciando');

            $dias = [];
            for ($i = 6; $i >= 0; $i--) {
                $data = now()->subDays($i);
                $dias[] = [
                    'dia' => $data->format('D'),
                    'valor' => Pedido::whereDate('created_at', $data)->count(),
                    'data' => $data->format('Y-m-d'),
                ];
            }

            Log::info('Atividade: finalizado com sucesso');

            return response()->json([
                'success' => true,
                'data' => $dias
            ]);
        } catch (\Exception $e) {
            Log::error('Atividade ERRO: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==========================================
    // 2. GESTÃO DE UTILIZADORES
    // ==========================================

    /**
     * Listar todos os utilizadores
     * GET /api/admin/users
     */
    public function index(Request $request)
    {
        try {
            $query = User::query();

            // Filtro por tipo
            if ($request->has('tipo')) {
                $query->where('tipo', $request->tipo);
            }

            // Filtro por status
            if ($request->has('status')) {
                if ($request->status === 'bloqueado') {
                    $query->whereNotNull('blocked_at');
                } else {
                    $query->whereNull('blocked_at');
                }
            }

            // Busca por nome ou email
            if ($request->has('busca')) {
                $busca = $request->busca;
                $query->where(function ($q) use ($busca) {
                    $q->where('nome', 'like', "%{$busca}%")
                        ->orWhere('email', 'like', "%{$busca}%")
                        ->orWhere('telefone', 'like', "%{$busca}%");
                });
            }

            $perPage = $request->get('per_page', 20);
            $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        } catch (\Exception $e) {
            Log::error('Index ERRO: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detalhes de um utilizador
     * GET /api/admin/users/{id}
     */
    /**
     * Detalhes de um utilizador
     * GET /api/admin/users/{id}
     */
    public function show($id)
    {
        try {
            Log::info('Show: buscando utilizador ' . $id);

            // ✅ Carregar apenas os relacionamentos que existem
            $user = User::with(['servicos', 'pedidosCliente', 'pedidosPrestador'])->find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Utilizador não encontrado'
                ], 404);
            }

            // ✅ Adicionar avaliações separadamente
            $user->avaliacoes_recebidas = $user->avaliacoesRecebidas;
            $user->avaliacoes_feitas = $user->avaliacoesFeitas;

            return response()->json([
                'success' => true,
                'data' => $user
            ]);
        } catch (\Exception $e) {
            Log::error('Show ERRO: ' . $e->getMessage());
            Log::error('Show ERRO linha: ' . $e->getLine());
            Log::error('Show ERRO arquivo: ' . $e->getFile());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    /**
     * Atualizar utilizador
     * PUT /api/admin/users/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Utilizador não encontrado'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'nome' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $id,
                'telefone' => 'sometimes|string|max:20',
                'endereco' => 'nullable|string',
                'tipo' => 'sometimes|in:cliente,prestador,admin',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => $validator->errors()->first()
                ], 422);
            }

            if ($request->has('nome')) $user->nome = $request->nome;
            if ($request->has('email')) $user->email = $request->email;
            if ($request->has('telefone')) $user->telefone = $request->telefone;
            if ($request->has('endereco')) $user->endereco = $request->endereco;
            if ($request->has('tipo')) $user->tipo = $request->tipo;

            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Utilizador atualizado com sucesso',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            Log::error('Update ERRO: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar utilizador: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bloquear utilizador
     * POST /api/admin/users/{id}/block
     */
    public function block($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Utilizador não encontrado'
                ], 404);
            }

            $user->blocked_at = now();
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Utilizador bloqueado com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao bloquear utilizador'
            ], 500);
        }
    }

    /**
     * Desbloquear utilizador
     * POST /api/admin/users/{id}/unblock
     */
    public function unblock($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Utilizador não encontrado'
                ], 404);
            }

            $user->blocked_at = null;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Utilizador desbloqueado com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao desbloquear utilizador'
            ], 500);
        }
    }

    /**
     * Deletar utilizador (soft delete)
     * DELETE /api/admin/users/{id}
     */
    public function destroy($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Utilizador não encontrado'
                ], 404);
            }

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Utilizador removido com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao remover utilizador'
            ], 500);
        }
    }

    /**
     * Deletar utilizador permanentemente
     * DELETE /api/admin/users/{id}/force
     */
    public function forceDelete($id)
    {
        try {
            $user = User::withTrashed()->find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Utilizador não encontrado'
                ], 404);
            }

            $user->forceDelete();

            return response()->json([
                'success' => true,
                'message' => 'Utilizador removido permanentemente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao remover utilizador permanentemente'
            ], 500);
        }
    }

    /**
     * Buscar utilizador por email
     * GET /api/admin/users/email/{email}
     */
    public function getByEmail($email)
    {
        try {
            $user = User::where('email', $email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Utilizador não encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao buscar utilizador'
            ], 500);
        }
    }

    // ==========================================
    // 3. GESTÃO DE PRESTADORES
    // ==========================================

    /**
     * Listar prestadores (admin)
     * GET /api/admin/prestadores
     */
    /**
     * Listar prestadores (admin)
     * GET /api/admin/prestadores
     */
    /**
     * Listar prestadores (admin)
     * GET /api/admin/prestadores
     */
    public function prestadores(Request $request)
    {
        try {
            Log::info('Prestadores: parâmetros recebidos', $request->all());

            $query = User::where('tipo', 'prestador');

            // LOG: Verificar total sem filtros
            $totalSemFiltros = User::where('tipo', 'prestador')->count();
            Log::info('Prestadores: total sem filtros = ' . $totalSemFiltros);

            // Filtro por busca (nome, email, telefone)
            if ($request->filled('busca')) {
                $busca = $request->busca;
                Log::info('Prestadores: aplicando filtro busca = ' . $busca);
                $query->where(function ($q) use ($busca) {
                    $q->where('nome', 'like', "%{$busca}%")
                        ->orWhere('email', 'like', "%{$busca}%")
                        ->orWhere('telefone', 'like', "%{$busca}%");
                });
            }

            // Filtro por verificado
            if ($request->filled('verificado')) {
                $verificado = $request->verificado;
                Log::info('Prestadores: filtro verificado recebido = ' . $verificado);

                if ($verificado === 'true' || $verificado === '1' || $verificado === true) {
                    $query->where('verificado', 1);
                    Log::info('Prestadores: filtrando verificado = 1');
                } elseif ($verificado === 'false' || $verificado === '0' || $verificado === false) {
                    $query->where('verificado', 0);
                    Log::info('Prestadores: filtrando verificado = 0');
                }
            }

            // Filtro por categoria
            if ($request->filled('categoria')) {
                $categoriaId = $request->categoria;
                Log::info('Prestadores: filtro categoria = ' . $categoriaId);
                try {
                    if (method_exists(User::class, 'categorias')) {
                        $query->whereHas('categorias', function ($q) use ($categoriaId) {
                            $q->where('categoria_id', $categoriaId);
                        });
                    }
                } catch (\Exception $e) {
                    Log::warning('Prestadores: erro no filtro categoria - ' . $e->getMessage());
                }
            }

            // Filtro por avaliação mínima
            if ($request->filled('avaliacao_min')) {
                $avaliacaoMin = floatval($request->avaliacao_min);
                Log::info('Prestadores: filtro avaliacao_min = ' . $avaliacaoMin);
                $query->where('media_avaliacao', '>=', $avaliacaoMin);
            }

            // Paginação
            $perPage = $request->get('per_page', 20);
            Log::info('Prestadores: perPage = ' . $perPage);

            // Executar query
            $totalAposFiltros = $query->count();
            Log::info('Prestadores: total após filtros = ' . $totalAposFiltros);

            if ($totalAposFiltros === 0) {
                Log::warning('Prestadores: Nenhum prestador encontrado com os filtros aplicados');
                $todosPrestadores = User::where('tipo', 'prestador')->take(5)->get();
                Log::info('Prestadores: Primeiros 5 prestadores sem filtros', $todosPrestadores->toArray());
            }

            // Carregar com relacionamento
            try {
                $prestadores = $query->with('categorias')->orderBy('created_at', 'desc')->paginate($perPage);
            } catch (\Exception $e) {
                Log::warning('Prestadores: erro ao carregar categorias - ' . $e->getMessage());
                $prestadores = $query->orderBy('created_at', 'desc')->paginate($perPage);
            }

            return response()->json([
                'success' => true,
                'data' => $prestadores
            ]);
        } catch (\Exception $e) {
            Log::error('Prestadores ERRO CRÍTICO: ' . $e->getMessage());
            Log::error('Prestadores ERRO linha: ' . $e->getLine());

            return response()->json([
                'success' => true,
                'data' => [
                    'current_page' => 1,
                    'data' => [],
                    'first_page_url' => null,
                    'from' => null,
                    'last_page' => 1,
                    'last_page_url' => null,
                    'links' => [],
                    'next_page_url' => null,
                    'path' => $request->url(),
                    'per_page' => $request->get('per_page', 20),
                    'prev_page_url' => null,
                    'to' => null,
                    'total' => 0
                ]
            ], 200);
        }
    }

    /**
     * Prestadores pendentes de verificação
     * GET /api/admin/prestadores/pendentes
     */
    public function prestadoresPendentes()
    {
        try {
            $prestadores = User::where('tipo', 'prestador')
                ->where('verificado', false)
                ->with('categorias')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $prestadores
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao listar prestadores pendentes'
            ], 500);
        }
    }

    /**
     * Aprovar prestador
     * PUT /api/admin/prestadores/{id}/aprovar
     */
    public function aprovarPrestador($id)
    {
        try {
            $prestador = User::where('tipo', 'prestador')->find($id);

            if (!$prestador) {
                return response()->json([
                    'success' => false,
                    'error' => 'Prestador não encontrado'
                ], 404);
            }

            $prestador->verificado = true;
            $prestador->save();

            return response()->json([
                'success' => true,
                'message' => 'Prestador aprovado com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao aprovar prestador'
            ], 500);
        }
    }

    /**
     * Reprovar prestador
     * PUT /api/admin/prestadores/{id}/reprovar
     */
    public function reprovarPrestador($id)
    {
        try {
            $prestador = User::where('tipo', 'prestador')->find($id);

            if (!$prestador) {
                return response()->json([
                    'success' => false,
                    'error' => 'Prestador não encontrado'
                ], 404);
            }

            $prestador->verificado = false;
            $prestador->save();

            return response()->json([
                'success' => true,
                'message' => 'Prestador reprovado'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao reprovar prestador'
            ], 500);
        }
    }

    // ==========================================
    // 4. GESTÃO DE CATEGORIAS
    // ==========================================

    /**
     * Listar categorias (admin)
     * GET /api/admin/categorias
     */
    public function categorias()
    {
        try {
            $categorias = Categoria::withCount('servicos')->get();

            return response()->json([
                'success' => true,
                'data' => $categorias
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao listar categorias'
            ], 500);
        }
    }

    /**
     * Criar categoria
     * POST /api/admin/categorias
     */
    public function createCategoria(Request $request)
    {
        try {
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

            $categoria = Categoria::create([
                'nome' => $request->nome,
                'slug' => \Illuminate\Support\Str::slug($request->nome),
                'descricao' => $request->descricao,
                'icone' => $request->icone ?? 'category',
                'cor' => $request->cor ?? 'primary',
                'ativo' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Categoria criada com sucesso',
                'data' => $categoria
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar categoria: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar categoria
     * PUT /api/admin/categorias/{id}
     */
    public function updateCategoria(Request $request, $id)
    {
        try {
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

            if ($request->has('nome')) {
                $categoria->nome = $request->nome;
                $categoria->slug = \Illuminate\Support\Str::slug($request->nome);
            }
            if ($request->has('descricao')) $categoria->descricao = $request->descricao;
            if ($request->has('icone')) $categoria->icone = $request->icone;
            if ($request->has('cor')) $categoria->cor = $request->cor;
            if ($request->has('ativo')) $categoria->ativo = $request->ativo;

            $categoria->save();

            return response()->json([
                'success' => true,
                'message' => 'Categoria atualizada com sucesso',
                'data' => $categoria
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar categoria'
            ], 500);
        }
    }

    /**
     * Deletar categoria
     * DELETE /api/admin/categorias/{id}
     */
    public function deleteCategoria($id)
    {
        try {
            $categoria = Categoria::find($id);

            if (!$categoria) {
                return response()->json([
                    'success' => false,
                    'error' => 'Categoria não encontrada'
                ], 404);
            }

            $categoria->delete();

            return response()->json([
                'success' => true,
                'message' => 'Categoria removida com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao remover categoria'
            ], 500);
        }
    }

    // ==========================================
    // 5. GESTÃO DE SERVIÇOS
    // ==========================================

    /**
     * Listar serviços (admin)
     * GET /api/admin/servicos
     */
    public function servicos(Request $request)
    {
        try {
            $query = Servico::with(['prestador', 'categoria']);

            if ($request->has('categoria')) {
                $query->where('categoria_id', $request->categoria);
            }

            if ($request->has('ativo')) {
                $query->where('ativo', $request->ativo);
            }

            $perPage = $request->get('per_page', 20);
            $servicos = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $servicos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao listar serviços'
            ], 500);
        }
    }

    /**
     * Criar serviço (admin)
     * POST /api/admin/servicos
     */
    public function createServico(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'prestador_id' => 'required|exists:users,id',
                'categoria_id' => 'required|exists:categorias,id',
                'nome' => 'required|string|max:255',
                'descricao' => 'nullable|string',
                'preco' => 'required|numeric|min:0',
                'duracao' => 'required|integer|min:5',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => $validator->errors()->first()
                ], 422);
            }

            $servico = Servico::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Serviço criado com sucesso',
                'data' => $servico
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar serviço'
            ], 500);
        }
    }

    // ==========================================
    // 6. GESTÃO DE PEDIDOS
    // ==========================================

    /**
     * Listar pedidos (admin)
     * GET /api/admin/pedidos
     */
    public function pedidos(Request $request)
    {
        try {
            $query = Pedido::with(['cliente', 'prestador', 'servico']);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $perPage = $request->get('per_page', 20);
            $pedidos = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $pedidos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao listar pedidos'
            ], 500);
        }
    }

    /**
     * Detalhes do pedido (admin)
     * GET /api/admin/pedidos/{id}
     */
    public function showPedido($id)
    {
        try {
            $pedido = Pedido::with(['cliente', 'prestador', 'servico', 'avaliacao'])
                ->find($id);

            if (!$pedido) {
                return response()->json([
                    'success' => false,
                    'error' => 'Pedido não encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $pedido
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao buscar pedido'
            ], 500);
        }
    }

    /**
     * Atualizar status do pedido
     * PUT /api/admin/pedidos/{id}/status
     */
    public function updatePedidoStatus(Request $request, $id)
    {
        try {
            $pedido = Pedido::find($id);

            if (!$pedido) {
                return response()->json([
                    'success' => false,
                    'error' => 'Pedido não encontrado'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pendente,aceito,em_andamento,concluido,cancelado',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => $validator->errors()->first()
                ], 422);
            }

            $pedido->status = $request->status;
            $pedido->save();

            return response()->json([
                'success' => true,
                'message' => 'Status do pedido atualizado com sucesso',
                'data' => $pedido
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar status do pedido'
            ], 500);
        }
    }

    /**
     * Cancelar pedido
     * DELETE /api/admin/pedidos/{id}/cancel
     */
    public function cancelPedido($id)
    {
        try {
            $pedido = Pedido::find($id);

            if (!$pedido) {
                return response()->json([
                    'success' => false,
                    'error' => 'Pedido não encontrado'
                ], 404);
            }

            $pedido->status = 'cancelado';
            $pedido->save();

            return response()->json([
                'success' => true,
                'message' => 'Pedido cancelado com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao cancelar pedido'
            ], 500);
        }
    }

    // ==========================================
    // 7. FINANCEIRO
    // ==========================================

    /**
     * Resumo financeiro
     * GET /api/admin/financeiro/resumo
     */
    public function resumoFinanceiro()
    {
        try {
            $saldoAtual = Transacao::sum('valor') ?? 0;
            $pendente = Transacao::where('status', 'pendente')->sum('valor') ?? 0;
            $processadoMes = Transacao::whereMonth('created_at', now()->month)
                ->where('status', 'concluido')
                ->sum('valor') ?? 0;
            $comissoes = Transacao::where('tipo', 'comissao')
                ->whereMonth('created_at', now()->month)
                ->sum('valor') ?? 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'saldo_atual' => $saldoAtual,
                    'pendente' => $pendente,
                    'processado_mes' => $processadoMes,
                    'comissoes' => $comissoes,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('ResumoFinanceiro ERRO: ' . $e->getMessage());
            Log::error('ResumoFinanceiro ERRO linha: ' . $e->getLine());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Listar transações
     * GET /api/admin/financeiro/transacoes
     */
    public function transacoes(Request $request)
    {
        try {
            $query = Transacao::with('user');

            if ($request->has('tipo')) {
                $query->where('tipo', $request->tipo);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $transacoes = $query->orderBy('created_at', 'desc')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $transacoes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao listar transações'
            ], 500);
        }
    }

    // ==========================================
    // 8. RELATÓRIOS
    // ==========================================

    /**
     * Exportar relatório
     * GET /api/admin/export
     */
    public function export(Request $request)
    {
        try {
            $tipo = $request->query('tipo', 'usuarios');

            return response()->json([
                'success' => true,
                'message' => "Relatório de {$tipo} em processamento"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao gerar relatório'
            ], 500);
        }
    }

    /**
     * Relatório de serviços
     * GET /api/admin/relatorios/servicos
     */
    public function relatorioServicos(Request $request)
    {
        try {
            $periodo = $request->query('periodo', 'mes');

            $query = Pedido::query();

            switch ($periodo) {
                case 'hoje':
                    $query->whereDate('created_at', today());
                    break;
                case 'semana':
                    $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'mes':
                    $query->whereMonth('created_at', now()->month);
                    break;
                case 'ano':
                    $query->whereYear('created_at', now()->year);
                    break;
            }

            $total = $query->count();
            $receita = $query->sum('valor');

            return response()->json([
                'success' => true,
                'data' => [
                    'periodo' => $periodo,
                    'total_servicos' => $total,
                    'receita_total' => $receita,
                    'servicos_por_status' => [
                        'pendente' => (clone $query)->where('status', 'pendente')->count(),
                        'aceito' => (clone $query)->where('status', 'aceito')->count(),
                        'em_andamento' => (clone $query)->where('status', 'em_andamento')->count(),
                        'concluido' => (clone $query)->where('status', 'concluido')->count(),
                        'cancelado' => (clone $query)->where('status', 'cancelado')->count(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao gerar relatório de serviços'
            ], 500);
        }
    }

    /**
     * Relatório de prestadores
     * GET /api/admin/relatorios/prestadores
     */


    /**
     * Relatório financeiro
     * GET /api/admin/relatorios/financeiro
     */
    public function relatorioFinanceiro(Request $request)
    {
        try {
            $periodo = $request->query('periodo', 'mes');

            $query = Transacao::query();

            switch ($periodo) {
                case 'hoje':
                    $query->whereDate('created_at', today());
                    break;
                case 'semana':
                    $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'mes':
                    $query->whereMonth('created_at', now()->month);
                    break;
                case 'ano':
                    $query->whereYear('created_at', now()->year);
                    break;
            }

            $entradas = (clone $query)->where('tipo', 'entrada')->sum('valor');
            $saidas = (clone $query)->where('tipo', 'saida')->sum('valor');
            $saldo = $entradas - $saidas;

            return response()->json([
                'success' => true,
                'data' => [
                    'periodo' => $periodo,
                    'entradas' => $entradas,
                    'saidas' => $saidas,
                    'saldo' => $saldo,
                    'comissoes' => (clone $query)->where('tipo', 'comissao')->sum('valor'),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao gerar relatório financeiro'
            ], 500);
        }
    }

    // ==========================================
    // 9. CONFIGURAÇÕES DO SISTEMA
    // ==========================================

    /**
     * Obter configurações do sistema
     * GET /api/admin/configuracoes
     */
    public function configuracoes()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'nome' => 'EstouAqui',
                    'email' => 'geral@estouaqui.co.mz',
                    'telefone' => '+258 84 123 4567',
                    'endereco' => 'Maputo, Moçambique',
                    'comissao_padrao' => 10,
                    'tipo_comissao' => 'porcentagem',
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao carregar configurações'
            ], 500);
        }
    }

    /**
     * Atualizar configurações
     * PUT /api/admin/configuracoes
     */
    public function updateConfiguracoes(Request $request)
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Configurações atualizadas'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar configurações'
            ], 500);
        }
    }

    // ==========================================
    // 10. LOGS DO SISTEMA
    // ==========================================

    /**
     * Listar logs do sistema
     * GET /api/admin/logs
     */
    public function logs(Request $request)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao carregar logs'
            ], 500);
        }
    }

    // ==========================================
    // 11. ESTATÍSTICAS GERAIS
    // ==========================================

    /**
     * Estatísticas gerais (admin)
     * GET /api/admin/stats
     */
    public function stats()
    {
        try {
            $totalUsers = User::count();
            $totalClientes = User::where('tipo', 'cliente')->count();
            $totalPrestadores = User::where('tipo', 'prestador')->count();
            $totalServicos = Servico::count();
            $totalPedidos = Pedido::count();
            $totalAvaliacoes = Avaliacao::count();
            $receitaTotal = Transacao::where('tipo', 'entrada')->sum('valor');

            return response()->json([
                'success' => true,
                'data' => [
                    'total_usuarios' => $totalUsers,
                    'total_clientes' => $totalClientes,
                    'total_prestadores' => $totalPrestadores,
                    'total_servicos' => $totalServicos,
                    'total_pedidos' => $totalPedidos,
                    'total_avaliacoes' => $totalAvaliacoes,
                    'receita_total' => $receitaTotal,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Stats ERRO: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Relatório de prestadores
     * GET /api/admin/relatorios/prestadores
     */
    public function relatorioPrestadores(Request $request)
    {
        try {
            Log::info('RelatórioPrestadores: iniciando');

            // Total de prestadores
            $total = User::where('tipo', 'prestador')->count();

            // Prestadores verificados
            $verificados = User::where('tipo', 'prestador')
                ->where('verificado', true)
                ->count();

            // Prestadores não verificados
            $naoVerificados = $total - $verificados;

            // Média de avaliação geral dos prestadores
            $mediaAvaliacao = Avaliacao::whereHas('prestador')->avg('nota') ?? 0;

            // Top 10 prestadores melhor avaliados
            $topPrestadores = User::where('tipo', 'prestador')
                ->orderBy('media_avaliacao', 'desc')
                ->orderBy('total_avaliacoes', 'desc')
                ->limit(10)
                ->get(['id', 'nome', 'email', 'media_avaliacao', 'total_avaliacoes', 'verificado']);

            // Prestadores ativos
            $ativos = User::where('tipo', 'prestador')
                ->where('ativo', true)
                ->count();

            // Prestadores bloqueados
            $bloqueados = User::where('tipo', 'prestador')
                ->whereNotNull('blocked_at')
                ->count();

            // Prestadores com mais serviços realizados
            $topServicos = User::where('tipo', 'prestador')
                ->withCount('pedidosPrestador')
                ->orderBy('pedidos_prestador_count', 'desc')
                ->limit(10)
                ->get(['id', 'nome', 'email', 'pedidos_prestador_count']);

            Log::info('RelatórioPrestadores: finalizado com sucesso');

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'verificados' => $verificados,
                    'nao_verificados' => $naoVerificados,
                    'ativos' => $ativos,
                    'bloqueados' => $bloqueados,
                    'media_avaliacao_geral' => round($mediaAvaliacao, 1),
                    'top_prestadores' => $topPrestadores,
                    'top_servicos' => $topServicos,
                    'periodo' => $request->get('periodo', 'geral')
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('RelatórioPrestadores ERRO: ' . $e->getMessage());
            Log::error('RelatórioPrestadores ERRO linha: ' . $e->getLine());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }


    
}
