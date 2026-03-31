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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    // ==========================================
    // 1. DASHBOARD E ESTATÍSTICAS - COM CACHE
    // ==========================================

    /**
     * Dashboard do admin - COM CACHE
     * GET /api/admin/dashboard
     */
    public function dashboard()
    {
        try {
            $dashboard = Cache::remember('admin_dashboard', 300, function () {
                return [
                    'total_users' => User::count(),
                    'total_clientes' => User::where('tipo', 'cliente')->count(),
                    'total_prestadores' => User::where('tipo', 'prestador')->count(),
                    'total_admins' => User::where('tipo', 'admin')->count(),
                    'prestadores_ativos' => User::where('tipo', 'prestador')->where('ativo', true)->count(),
                    'servicos_hoje' => Pedido::whereDate('created_at', today())->count(),
                    'servicos_pendentes' => Pedido::where('status', 'pendente')->count(),
                    'avaliacao_media' => round(Avaliacao::avg('nota') ?? 0, 1),
                    'total_avaliacoes' => Avaliacao::count(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $dashboard
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard ERRO: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atividade dos últimos 7 dias - COM CACHE
     * GET /api/admin/atividade
     */
    public function atividade()
    {
        try {
            $atividade = Cache::remember('admin_atividade', 300, function () {
                $dias = [];
                for ($i = 6; $i >= 0; $i--) {
                    $data = now()->subDays($i);
                    $dias[] = [
                        'dia' => $data->format('D'),
                        'valor' => Pedido::whereDate('created_at', $data)->count(),
                        'data' => $data->format('Y-m-d'),
                    ];
                }
                return $dias;
            });

            return response()->json([
                'success' => true,
                'data' => $atividade
            ]);
        } catch (\Exception $e) {
            Log::error('Atividade ERRO: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estatísticas gerais - COM CACHE
     * GET /api/admin/stats
     */
    public function stats()
    {
        try {
            $stats = Cache::remember('admin_stats', 300, function () {
                return [
                    'total_usuarios' => User::count(),
                    'total_clientes' => User::where('tipo', 'cliente')->count(),
                    'total_prestadores' => User::where('tipo', 'prestador')->count(),
                    'total_servicos' => Servico::count(),
                    'total_pedidos' => Pedido::count(),
                    'total_avaliacoes' => Avaliacao::count(),
                    'receita_total' => Transacao::where('tipo', 'entrada')->sum('valor'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Stats ERRO: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==========================================
    // 2. GESTÃO DE UTILIZADORES - COM CACHE OTIMIZADO
    // ==========================================

    /**
     * Listar todos os utilizadores - COM CACHE
     * GET /api/admin/users
     */
    public function index(Request $request)
    {
        try {
            $cacheKey = 'admin_users_' . md5($request->fullUrl());

            $users = Cache::remember($cacheKey, 120, function () use ($request) {
                $query = User::query();

                if ($request->has('tipo')) {
                    $query->where('tipo', $request->tipo);
                }

                if ($request->has('status')) {
                    if ($request->status === 'bloqueado') {
                        $query->whereNotNull('blocked_at');
                    } else {
                        $query->whereNull('blocked_at');
                    }
                }

                if ($request->has('busca')) {
                    $busca = $request->busca;
                    $query->where(function ($q) use ($busca) {
                        $q->where('nome', 'like', "%{$busca}%")
                            ->orWhere('email', 'like', "%{$busca}%")
                            ->orWhere('telefone', 'like', "%{$busca}%");
                    });
                }

                $perPage = $request->get('per_page', 20);
                return $query->orderBy('created_at', 'desc')->paginate($perPage);
            });

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
     * Detalhes de um utilizador - COM CACHE
     * GET /api/admin/users/{id}
     */
    public function show($id)
    {
        try {
            $cacheKey = "admin_user_{$id}";

            $user = Cache::remember($cacheKey, 3600, function () use ($id) {
                return User::with(['servicos', 'pedidosCliente', 'pedidosPrestador'])
                    ->find($id);
            });

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Utilizador não encontrado'
                ], 404);
            }

            // Adicionar avaliações separadamente
            $user->avaliacoes_recebidas = $user->avaliacoesRecebidas;
            $user->avaliacoes_feitas = $user->avaliacoesFeitas;

            return response()->json([
                'success' => true,
                'data' => $user
            ]);
        } catch (\Exception $e) {
            Log::error('Show ERRO: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar utilizador - LIMPAR CACHE
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

            $this->clearAdminCache($id);

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
     * Bloquear utilizador - LIMPAR CACHE
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

            $this->clearAdminCache($id);

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
     * Desbloquear utilizador - LIMPAR CACHE
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

            $this->clearAdminCache($id);

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

    // ==========================================
    // 3. GESTÃO DE PRESTADORES - COM CACHE
    // ==========================================

    /**
     * Listar prestadores - COM CACHE
     * GET /api/admin/prestadores
     */
    public function prestadores(Request $request)
    {
        try {
            $cacheKey = 'admin_prestadores_' . md5($request->fullUrl());

            $prestadores = Cache::remember($cacheKey, 300, function () use ($request) {
                $query = User::where('tipo', 'prestador');

                if ($request->filled('busca')) {
                    $busca = $request->busca;
                    $query->where(function ($q) use ($busca) {
                        $q->where('nome', 'like', "%{$busca}%")
                            ->orWhere('email', 'like', "%{$busca}%")
                            ->orWhere('telefone', 'like', "%{$busca}%");
                    });
                }

                if ($request->filled('verificado')) {
                    $verificado = $request->verificado === 'true' || $request->verificado === '1';
                    $query->where('verificado', $verificado);
                }

                if ($request->filled('avaliacao_min')) {
                    $query->where('media_avaliacao', '>=', floatval($request->avaliacao_min));
                }

                $perPage = $request->get('per_page', 20);
                return $query->orderBy('created_at', 'desc')->paginate($perPage);
            });

            return response()->json([
                'success' => true,
                'data' => $prestadores
            ]);
        } catch (\Exception $e) {
            Log::error('Prestadores ERRO: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'data' => ['data' => [], 'total' => 0]
            ], 200);
        }
    }

    /**
     * Aprovar prestador - LIMPAR CACHE
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

            $this->clearAdminCache($id);

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

    // ==========================================
    // 4. FINANCEIRO - COM CACHE
    // ==========================================

    /**
     * Resumo financeiro - COM CACHE
     * GET /api/admin/financeiro/resumo
     */
    public function resumoFinanceiro()
    {
        try {
            $resumo = Cache::remember('admin_resumo_financeiro', 300, function () {
                return [
                    'saldo_atual' => Transacao::sum('valor') ?? 0,
                    'pendente' => Transacao::where('status', 'pendente')->sum('valor') ?? 0,
                    'processado_mes' => Transacao::whereMonth('created_at', now()->month)
                        ->where('status', 'concluido')
                        ->sum('valor') ?? 0,
                    'comissoes' => Transacao::where('tipo', 'comissao')
                        ->whereMonth('created_at', now()->month)
                        ->sum('valor') ?? 0,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $resumo
            ]);
        } catch (\Exception $e) {
            Log::error('ResumoFinanceiro ERRO: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar transações - COM CACHE
     * GET /api/admin/financeiro/transacoes
     */
    public function transacoes(Request $request)
    {
        try {
            $cacheKey = 'admin_transacoes_' . md5($request->fullUrl());

            $transacoes = Cache::remember($cacheKey, 120, function () use ($request) {
                $query = Transacao::with('user');

                if ($request->has('tipo')) {
                    $query->where('tipo', $request->tipo);
                }

                if ($request->has('status')) {
                    $query->where('status', $request->status);
                }

                return $query->orderBy('created_at', 'desc')->paginate(20);
            });

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
    // 5. GESTÃO DE PEDIDOS - COM CACHE
    // ==========================================

    /**
     * Listar pedidos - COM CACHE
     * GET /api/admin/pedidos
     */
    public function pedidos(Request $request)
    {
        try {
            $cacheKey = 'admin_pedidos_' . md5($request->fullUrl());

            $pedidos = Cache::remember($cacheKey, 120, function () use ($request) {
                $query = Pedido::with(['cliente:id,nome,foto', 'prestador:id,nome,foto', 'servico:id,nome,preco']);

                if ($request->has('status')) {
                    $query->where('status', $request->status);
                }

                $perPage = $request->get('per_page', 20);
                return $query->orderBy('created_at', 'desc')->paginate($perPage);
            });

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

    // ==========================================
    // 6. NOTIFICAÇÕES DO ADMIN - COM CACHE
    // ==========================================

    /**
     * Listar notificações do admin - COM CACHE
     * GET /api/admin/notifications
     */
    public function notifications()
    {
        try {
            $notifications = Cache::remember('admin_notifications', 60, function () {
                // Retorna array vazio por enquanto
                // TODO: Implementar quando tiver notificações reais
                return [];
            });

            return response()->json([
                'success' => true,
                'data' => $notifications
            ]);
        } catch (\Exception $e) {
            Log::error('Notifications ERRO: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'data' => []
            ], 200);
        }
    }

    /**
     * Marcar notificação como lida - LIMPAR CACHE
     * PUT /api/admin/notifications/{id}/read
     */
    public function markNotificationRead($id)
    {
        Cache::forget('admin_notifications');
        return response()->json(['success' => true]);
    }

    /**
     * Marcar todas notificações como lidas - LIMPAR CACHE
     * PUT /api/admin/notifications/read-all
     */
    public function markAllNotificationsRead()
    {
        Cache::forget('admin_notifications');
        return response()->json(['success' => true]);
    }

    // ==========================================
    // 7. RELATÓRIOS - COM CACHE
    // ==========================================

    /**
     * Relatório de prestadores - COM CACHE
     * GET /api/admin/relatorios/prestadores
     */
    public function relatorioPrestadores(Request $request)
    {
        try {
            $periodo = $request->get('periodo', 'geral');
            $cacheKey = "admin_relatorio_prestadores_{$periodo}";

            $relatorio = Cache::remember($cacheKey, 600, function () use ($periodo) {
                $total = User::where('tipo', 'prestador')->count();
                $verificados = User::where('tipo', 'prestador')->where('verificado', true)->count();
                $naoVerificados = $total - $verificados;
                $mediaAvaliacao = Avaliacao::whereHas('prestador')->avg('nota') ?? 0;
                $ativos = User::where('tipo', 'prestador')->where('ativo', true)->count();
                $bloqueados = User::where('tipo', 'prestador')->whereNotNull('blocked_at')->count();

                $topPrestadores = User::where('tipo', 'prestador')
                    ->orderBy('media_avaliacao', 'desc')
                    ->orderBy('total_avaliacoes', 'desc')
                    ->limit(10)
                    ->get(['id', 'nome', 'email', 'media_avaliacao', 'total_avaliacoes', 'verificado']);

                return [
                    'total' => $total,
                    'verificados' => $verificados,
                    'nao_verificados' => $naoVerificados,
                    'ativos' => $ativos,
                    'bloqueados' => $bloqueados,
                    'media_avaliacao_geral' => round($mediaAvaliacao, 1),
                    'top_prestadores' => $topPrestadores,
                    'periodo' => $periodo
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $relatorio
            ]);
        } catch (\Exception $e) {
            Log::error('RelatorioPrestadores ERRO: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Relatório de serviços - COM CACHE
     * GET /api/admin/relatorios/servicos
     */
    public function relatorioServicos(Request $request)
    {
        try {
            $periodo = $request->query('periodo', 'mes');
            $cacheKey = "admin_relatorio_servicos_{$periodo}";

            $relatorio = Cache::remember($cacheKey, 300, function () use ($periodo) {
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

                return [
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
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $relatorio
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao gerar relatório de serviços'
            ], 500);
        }
    }

    // ==========================================
    // 8. MÉTODOS AUXILIARES
    // ==========================================

    /**
     * Limpar cache do admin
     */
    private function clearAdminCache($userId = null)
    {
        Cache::forget('admin_dashboard');
        Cache::forget('admin_atividade');
        Cache::forget('admin_stats');
        Cache::forget('admin_resumo_financeiro');
        Cache::forget('admin_notifications');

        if ($userId) {
            Cache::forget("admin_user_{$userId}");
        }

        // Limpar cache de listagens (várias páginas)
        for ($page = 1; $page <= 10; $page++) {
            Cache::forget("admin_users_page_{$page}");
            Cache::forget("admin_prestadores_page_{$page}");
            Cache::forget("admin_pedidos_page_{$page}");
            Cache::forget("admin_transacoes_page_{$page}");
        }

        // Limpar cache de relatórios
        $periodos = ['hoje', 'semana', 'mes', 'ano', 'geral'];
        foreach ($periodos as $periodo) {
            Cache::forget("admin_relatorio_servicos_{$periodo}");
            Cache::forget("admin_relatorio_prestadores_{$periodo}");
        }
    }
}
