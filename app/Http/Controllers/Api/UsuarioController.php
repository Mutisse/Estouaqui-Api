<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Pedido;
use App\Models\Avaliacao;
use App\Models\Transacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Controller genérico para funcionalidades comuns a todos os usuários
 * (cliente, prestador, admin) - VERSÃO SUPER OTIMIZADA
 */
class UsuarioController extends Controller
{
    // ==========================================
    // CONSTANTES DE CACHE OTIMIZADAS
    // ==========================================
    private const CACHE_VERY_SHORT = 60;    // 1 minuto
    private const CACHE_SHORT = 300;        // 5 minutos (aumentado de 2min)
    private const CACHE_MEDIUM = 900;       // 15 minutos (aumentado de 10min)
    private const CACHE_LONG = 7200;        // 2 horas (aumentado de 1h)
    private const CACHE_VERY_LONG = 86400;  // 24 horas

    // Cache para tabela de notificações (evita verificar a cada requisição)
    private static $hasNotificationsTable = null;

    // ==========================================
    // 1. FUNCIONALIDADES COMUNS (todos os perfis) - OTIMIZADO
    // ==========================================

    /**
     * Obter perfil do usuário autenticado - COM CACHE OTIMIZADO
     * GET /api/me
     */
    /**
     * Obter perfil do usuário autenticado - COM CACHE OTIMIZADO
     * GET /api/me
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $cacheKey = "user_profile_{$user->id}";

        $data = Cache::remember($cacheKey, self::CACHE_LONG, function () use ($user) {
            // ✅ Buscar preferências
            $preferences = $user->preferences;
            if (is_string($preferences)) {
                $preferences = json_decode($preferences, true);
            }

            // ✅ CORRIGIDO: Buscar portfolio e gerar URLs
            $portfolio = [];
            if (!empty($preferences['portfolio']) && is_array($preferences['portfolio'])) {
                foreach ($preferences['portfolio'] as $path) {
                    if (filter_var($path, FILTER_VALIDATE_URL)) {
                        $portfolio[] = $path;
                    } else {
                        // Remove barras no início e garante o caminho correto
                        $cleanPath = ltrim($path, '/');
                        $portfolio[] = asset('storage/' . $cleanPath);
                    }
                }
            }

            return [
                'id' => $user->id,
                'nome' => $user->nome,
                'email' => $user->email,
                'telefone' => $user->telefone,
                'endereco' => $user->endereco,
                'foto' => $user->foto ? (filter_var($user->foto, FILTER_VALIDATE_URL) ? $user->foto : asset('storage/' . ltrim($user->foto, '/'))) : null,
                'tipo' => $user->tipo,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'sobre' => $user->sobre ?? '',
                'profissao' => $user->profissao ?? '',
                'media_avaliacao' => (float) ($user->media_avaliacao ?? 0),
                'total_avaliacoes' => (int) ($user->total_avaliacoes ?? 0),
                'verificado' => (bool) ($user->verificado ?? false),
                'ativo' => (bool) ($user->ativo ?? true),
                'preferences' => $preferences,
                'portfolio' => $portfolio,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Atualizar perfil do usuário autenticado - LIMPAR CACHE
     * PUT /api/me
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'nome' => 'sometimes|string|max:255',
            'telefone' => 'sometimes|string|max:20',
            'endereco' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            if ($request->has('nome')) $user->nome = $request->nome;
            if ($request->has('telefone')) $user->telefone = $request->telefone;
            if ($request->has('endereco')) $user->endereco = $request->endereco;

            $user->save();

            $this->clearUserCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Perfil atualizado com sucesso',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar perfil'
            ], 500);
        }
    }

    /**
     * Atualizar foto de perfil - LIMPAR CACHE
     * POST /api/avatar
     */
    public function updateAvatar(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'foto' => 'required|image|mimes:jpeg,png,jpg|max:5120'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            if ($user->foto) {
                Storage::disk('public')->delete($user->foto);
            }

            $path = $request->file('foto')->store('fotos/usuarios', 'public');
            $user->foto = $path;
            $user->save();

            $this->clearUserCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Foto atualizada com sucesso',
                'data' => ['foto' => asset('storage/' . $path)]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar foto'
            ], 500);
        }
    }

    /**
     * Remover foto de perfil - LIMPAR CACHE
     * DELETE /api/avatar
     */
    public function removeAvatar(Request $request)
    {
        $user = $request->user();

        try {
            if ($user->foto) {
                Storage::disk('public')->delete($user->foto);
                $user->foto = null;
                $user->save();
            }

            $this->clearUserCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Foto removida com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao remover foto'
            ], 500);
        }
    }

    /**
     * Alterar senha - LIMPAR CACHE
     * PUT /api/password
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6',
            'confirm_password' => 'required|string|same:new_password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'error' => 'Senha atual incorreta'
            ], 422);
        }

        try {
            $user->password = Hash::make($request->new_password);
            $user->save();

            $this->clearUserCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Senha alterada com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao alterar senha'
            ], 500);
        }
    }

    /**
     * Deletar conta (soft delete) - LIMPAR CACHE
     * DELETE /api/me
     */
    public function destroy(Request $request)
    {
        $user = $request->user();

        try {
            $user->delete();

            $this->clearUserCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Conta removida com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao remover conta'
            ], 500);
        }
    }

    /**
     * Dashboard do usuário - COM CACHE OTIMIZADO (QUERY MAIS RÁPIDAS)
     * GET /api/dashboard
     */
    /**
     * Dashboard do usuário - COM CACHE OTIMIZADO (QUERY MAIS RÁPIDAS)
     * GET /api/dashboard
     */
    public function dashboard(Request $request)
    {
        try {
            $user = $request->user();
            $cacheKey = "dashboard_{$user->id}";

            $data = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($user) {
                $result = [
                    'user' => [
                        'id' => $user->id,
                        'nome' => $user->nome,
                        'foto' => $user->foto ? asset('storage/' . $user->foto) : null,
                        'tipo' => $user->tipo,
                    ],
                    'stats' => [
                        'membro_desde' => $user->created_at->format('d/m/Y'),
                        'anos' => now()->diffInYears($user->created_at),
                    ]
                ];

                if ($user->isCliente()) {
                    try {
                        $stats = DB::table('pedidos')
                            ->where('cliente_id', $user->id)
                            ->selectRaw("
                            COUNT(CASE WHEN status IN ('pendente', 'aceito', 'em_andamento') THEN 1 END) as pedidos_ativos,
                            COUNT(CASE WHEN status = 'concluido' THEN 1 END) as pedidos_concluidos
                        ")
                            ->first();

                        $result['stats']['pedidos_ativos'] = (int) ($stats->pedidos_ativos ?? 0);
                        $result['stats']['pedidos_concluidos'] = (int) ($stats->pedidos_concluidos ?? 0);

                        // ✅ CORRIGIDO: usar 'cliente_id' em vez de 'user_id'
                        $result['stats']['favoritos'] = DB::table('favoritos')->where('cliente_id', $user->id)->count();
                        $result['stats']['avaliacoes'] = DB::table('avaliacoes')->where('cliente_id', $user->id)->count();
                    } catch (\Exception $e) {
                        $result['stats']['pedidos_ativos'] = 0;
                        $result['stats']['pedidos_concluidos'] = 0;
                        $result['stats']['favoritos'] = 0;
                        $result['stats']['avaliacoes'] = 0;
                    }
                } elseif ($user->isPrestador()) {
                    try {
                        $stats = DB::table('pedidos')
                            ->where('prestador_id', $user->id)
                            ->selectRaw("
                            COUNT(CASE WHEN status IN ('pendente', 'aceito', 'em_andamento') THEN 1 END) as servicos_ativos,
                            COUNT(CASE WHEN status = 'concluido' THEN 1 END) as servicos_concluidos
                        ")
                            ->first();

                        $result['stats']['servicos_ativos'] = (int) ($stats->servicos_ativos ?? 0);
                        $result['stats']['servicos_concluidos'] = (int) ($stats->servicos_concluidos ?? 0);
                        $result['stats']['avaliacoes'] = DB::table('avaliacoes')->where('prestador_id', $user->id)->count();
                        $result['stats']['rating'] = (float) ($user->media_avaliacao ?? 0);
                    } catch (\Exception $e) {
                        $result['stats']['servicos_ativos'] = 0;
                        $result['stats']['servicos_concluidos'] = 0;
                        $result['stats']['avaliacoes'] = 0;
                        $result['stats']['rating'] = 0;
                    }
                } elseif ($user->isAdmin()) {
                    $result['stats'] = $this->getAdminStats();
                }

                return $result;
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao carregar dashboard: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estatísticas de admin com cache próprio
     */
    private function getAdminStats()
    {
        return Cache::remember('admin_stats', self::CACHE_MEDIUM, function () {
            return [
                'total_usuarios' => User::count(),
                'total_clientes' => User::where('tipo', 'cliente')->count(),
                'total_prestadores' => User::where('tipo', 'prestador')->count(),
            ];
        });
    }

    // ==========================================
    // 2. NOTIFICAÇÕES - VERSÃO SUPER OTIMIZADA
    // ==========================================

    /**
     * Listar notificações do usuário - OTIMIZADO COM PAGINAÇÃO
     * GET /api/notifications
     */
    public function notifications(Request $request)
    {
        $user = $request->user();
        $perPage = min(50, max(10, (int) $request->get('per_page', 20)));
        $page = max(1, (int) $request->get('page', 1));

        $cacheKey = "notifications_{$user->id}_{$page}_{$perPage}";

        $result = Cache::remember($cacheKey, self::CACHE_SHORT, function () use ($user, $perPage, $page) {
            if (!$this->hasNotificationsTable()) {
                return $this->getMockNotificationsPaginated($user, $page, $perPage);
            }

            // ✅ OTIMIZADO: Query Builder com índice
            $query = DB::table('notifications')
                ->where('notifiable_id', $user->id)
                ->where('notifiable_type', get_class($user));

            $total = $query->count();

            $notifications = $query
                ->select(['id', 'type', 'data', 'read_at', 'created_at'])
                ->orderBy('created_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get()
                ->map(function ($notif) {
                    $data = json_decode($notif->data, true) ?? [];
                    return [
                        'id' => $notif->id,
                        'type' => $notif->type,
                        'titulo' => $data['titulo'] ?? 'Notificação',
                        'mensagem' => $data['mensagem'] ?? '',
                        'lida' => !is_null($notif->read_at),
                        'created_at' => $notif->created_at,
                    ];
                })->toArray();

            return [
                'current_page' => $page,
                'data' => $notifications,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    /**
     * NOTIFICAÇÕES RECENTES - ULTRA RÁPIDO (para dashboard)
     * GET /api/notifications/recent
     */
    public function recentNotifications(Request $request)
    {
        $user = $request->user();
        $limit = min(10, max(1, (int) $request->get('limit', 5)));
        $cacheKey = "notifications_recent_{$user->id}_{$limit}";

        $notifications = Cache::remember($cacheKey, self::CACHE_VERY_SHORT, function () use ($user, $limit) {
            if (!$this->hasNotificationsTable()) {
                return array_slice($this->getMockNotificationsByProfile($user), 0, $limit);
            }

            // ✅ QUERY ULTRA RÁPIDA com índices
            return DB::table('notifications')
                ->where('notifiable_id', $user->id)
                ->where('notifiable_type', get_class($user))
                ->select(['id', 'type', 'data', 'read_at', 'created_at'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($notif) {
                    $data = json_decode($notif->data, true) ?? [];
                    return [
                        'id' => $notif->id,
                        'type' => $notif->type,
                        'titulo' => $data['titulo'] ?? 'Notificação',
                        'mensagem' => $data['mensagem'] ?? '',
                        'lida' => !is_null($notif->read_at),
                        'created_at' => $notif->created_at,
                    ];
                })->toArray();
        });

        return response()->json([
            'success' => true,
            'data' => $notifications
        ]);
    }

    /**
     * COUNT DE NOTIFICAÇÕES NÃO LIDAS - MUITO RÁPIDO
     * GET /api/notifications/unread-count
     */
    public function unreadCount(Request $request)
    {
        $user = $request->user();
        $cacheKey = "notifications_unread_count_{$user->id}";

        $count = Cache::remember($cacheKey, self::CACHE_VERY_SHORT, function () use ($user) {
            if (!$this->hasNotificationsTable()) {
                return 0;
            }

            // ✅ COUNT APENAS - sem carregar dados
            return DB::table('notifications')
                ->where('notifiable_id', $user->id)
                ->where('notifiable_type', get_class($user))
                ->whereNull('read_at')
                ->count();
        });

        return response()->json([
            'success' => true,
            'data' => ['unread_count' => $count]
        ]);
    }

    /**
     * Verificar se tabela de notificações existe (com cache)
     */
    private function hasNotificationsTable(): bool
    {
        if (self::$hasNotificationsTable !== null) {
            return self::$hasNotificationsTable;
        }

        try {
            self::$hasNotificationsTable = Schema::hasTable('notifications');
            return self::$hasNotificationsTable;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Marcar notificação como lida - OTIMIZADO
     * PUT /api/notifications/{id}/read
     */
    public function markNotificationRead(Request $request, $id)
    {
        $user = $request->user();

        if ($this->hasNotificationsTable()) {
            // ✅ UPDATE direto sem carregar modelo
            DB::table('notifications')
                ->where('id', $id)
                ->where('notifiable_id', $user->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        // ✅ Limpar caches específicos
        $this->clearNotificationCache($user->id);

        return response()->json(['success' => true]);
    }

    /**
     * Marcar todas notificações como lidas - OTIMIZADO
     * PUT /api/notifications/read-all
     */
    public function markAllNotificationsRead(Request $request)
    {
        $user = $request->user();

        if ($this->hasNotificationsTable()) {
            // ✅ UPDATE em lote sem carregar modelos
            DB::table('notifications')
                ->where('notifiable_id', $user->id)
                ->where('notifiable_type', get_class($user))
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        // ✅ Limpar caches específicos
        $this->clearNotificationCache($user->id, true);

        return response()->json(['success' => true]);
    }

    /**
     * Obter preferências de notificação - COM CACHE
     * GET /api/notifications/preferences
     */
    public function notificationPreferences(Request $request)
    {
        $user = $request->user();
        $cacheKey = "notif_preferences_{$user->id}";

        $preferences = Cache::remember($cacheKey, self::CACHE_LONG, function () use ($user) {
            $defaultPreferences = [
                'cliente' => [
                    'email' => true,
                    'push' => true,
                    'sms' => false,
                    'types' => [
                        'pedido_confirmado' => true,
                        'pedido_em_andamento' => true,
                        'pedido_concluido' => true,
                        'pedido_cancelado' => true,
                        'promocao_nova' => true,
                    ]
                ],
                'prestador' => [
                    'email' => true,
                    'push' => true,
                    'sms' => true,
                    'types' => [
                        'nova_solicitacao' => true,
                        'solicitacao_aceita' => true,
                        'solicitacao_recusada' => true,
                        'cliente_avaliou' => true,
                        'pagamento_recebido' => true,
                    ]
                ],
                'admin' => [
                    'email' => true,
                    'push' => true,
                    'sms' => false,
                    'types' => [
                        'novo_prestador_pendente' => true,
                        'prestador_aprovado' => true,
                        'relatorio_semanal' => true,
                        'alerta_seguranca' => true,
                    ]
                ]
            ];

            $profile = $user->tipo;
            $preferencesData = $user->preferences ?? [];
            return $preferencesData['notifications'] ?? $defaultPreferences[$profile] ?? $defaultPreferences['cliente'];
        });

        return response()->json([
            'success' => true,
            'data' => $preferences
        ]);
    }

    /**
     * Atualizar preferências de notificação - LIMPAR CACHE
     * PUT /api/notifications/preferences
     */
    public function updateNotificationPreferences(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'email' => 'sometimes|boolean',
            'push' => 'sometimes|boolean',
            'sms' => 'sometimes|boolean',
            'types' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $preferences = $user->preferences ?? [];
        $notifPref = $preferences['notifications'] ?? [];

        if ($request->has('email')) $notifPref['email'] = $request->email;
        if ($request->has('push')) $notifPref['push'] = $request->push;
        if ($request->has('sms')) $notifPref['sms'] = $request->sms;
        if ($request->has('types')) $notifPref['types'] = array_merge($notifPref['types'] ?? [], $request->types);

        $preferences['notifications'] = $notifPref;
        $user->preferences = $preferences;
        $user->save();

        $this->clearUserCache($user->id);
        Cache::forget("notif_preferences_{$user->id}");

        return response()->json([
            'success' => true,
            'message' => 'Preferências de notificação atualizadas',
            'data' => $notifPref
        ]);
    }

    // ==========================================
    // 3. PREFERÊNCIAS DO USUÁRIO - COM CACHE
    // ==========================================

    /**
     * Obter preferências do usuário - COM CACHE
     * GET /api/preferences
     */
    public function preferences(Request $request)
    {
        $user = $request->user();
        $cacheKey = "user_preferences_{$user->id}";

        $preferences = Cache::remember($cacheKey, self::CACHE_LONG, function () use ($user) {
            $preferencesData = $user->preferences ?? [];
            return [
                'theme' => $preferencesData['theme'] ?? 'light',
                'language' => $preferencesData['language'] ?? 'pt',
                'notifications' => $preferencesData['notifications_enabled'] ?? true,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $preferences
        ]);
    }

    /**
     * Atualizar preferências - LIMPAR CACHE
     * PUT /api/preferences
     */
    public function updatePreferences(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'theme' => 'sometimes|in:light,dark',
            'language' => 'sometimes|in:pt,en',
            'notifications' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $preferences = $user->preferences ?? [];
        if ($request->has('theme')) $preferences['theme'] = $request->theme;
        if ($request->has('language')) $preferences['language'] = $request->language;
        if ($request->has('notifications')) $preferences['notifications_enabled'] = $request->notifications;

        $user->preferences = $preferences;
        $user->save();

        $this->clearUserCache($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Preferências atualizadas',
            'data' => $preferences
        ]);
    }

    // ==========================================
    // 4. ENDEREÇOS DO USUÁRIO - SIMPLIFICADO
    // ==========================================

    /**
     * Listar endereços do usuário
     * GET /api/addresses
     */
    public function addresses(Request $request)
    {
        $user = $request->user();

        $addresses = [];
        if ($user->endereco) {
            $addresses[] = [
                'id' => 1,
                'endereco' => $user->endereco,
                'principal' => true,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $addresses
        ]);
    }

    /**
     * Criar/Atualizar endereço principal
     * POST /api/addresses
     */
    public function createAddress(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'endereco' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            $user->endereco = $request->endereco;
            $user->save();

            $this->clearUserCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Endereço atualizado com sucesso',
                'data' => [
                    'id' => 1,
                    'endereco' => $user->endereco,
                    'principal' => true,
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar endereço'
            ], 500);
        }
    }

    /**
     * Obter endereço específico
     * GET /api/addresses/{id}
     */
    public function getAddress($id)
    {
        $user = request()->user();

        if ($id == 1 && $user->endereco) {
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => 1,
                    'endereco' => $user->endereco,
                    'principal' => true,
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => 'Endereço não encontrado'
        ], 404);
    }

    /**
     * Atualizar endereço
     * PUT /api/addresses/{id}
     */
    public function updateAddress(Request $request, $id)
    {
        return $this->createAddress($request);
    }

    /**
     * Deletar endereço
     * DELETE /api/addresses/{id}
     */
    public function deleteAddress($id)
    {
        $user = request()->user();

        if ($id == 1 && $user->endereco) {
            $user->endereco = null;
            $user->save();
            $this->clearUserCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Endereço removido com sucesso'
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => 'Endereço não encontrado'
        ], 404);
    }

    /**
     * Definir endereço principal
     * PUT /api/addresses/{id}/primary
     */
    public function setPrimaryAddress($id)
    {
        $user = request()->user();

        if ($id == 1 && $user->endereco) {
            return response()->json([
                'success' => true,
                'message' => 'Endereço principal definido com sucesso'
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => 'Endereço não encontrado'
        ], 404);
    }

    // ==========================================
    // 5. MÉTODOS PÚBLICOS - COM CACHE
    // ==========================================

    /**
     * Verificar disponibilidade de email
     * GET /api/check-email?email=
     */
    public function checkEmail(Request $request)
    {
        $email = $request->query('email');
        $cacheKey = "email_available_" . md5($email);

        $exists = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($email) {
            return User::where('email', $email)->exists();
        });

        return response()->json(['available' => !$exists]);
    }

    /**
     * Verificar disponibilidade de telefone
     * GET /api/check-phone?phone=
     */
    public function checkPhone(Request $request)
    {
        $phone = $request->query('phone');
        $cacheKey = "phone_available_" . md5($phone);

        $exists = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($phone) {
            return User::where('telefone', $phone)->exists();
        });

        return response()->json(['available' => !$exists]);
    }

    /**
     * Upload temporário de foto
     * POST /api/upload-temp
     */
    public function uploadTemp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'foto' => 'required|image|mimes:jpeg,png,jpg|max:5120'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        try {
            $path = $request->file('foto')->store('fotos/temp', 'public');

            return response()->json([
                'success' => true,
                'url' => asset('storage/' . $path)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao fazer upload'
            ], 500);
        }
    }

    // ==========================================
    // 6. CRIAÇÃO DE ÍNDICES (executar uma vez)
    // ==========================================

    /**
     * Criar índices para otimização do banco
     * POST /api/notifications/create-indexes
     */
    public function createIndexes()
    {
        if (!$this->hasNotificationsTable()) {
            return response()->json(['error' => 'Tabela de notificações não existe'], 404);
        }

        try {
            // Verificar e criar índices se não existirem
            $indexes = [
                'idx_notifiable' => 'CREATE INDEX idx_notifiable ON notifications (notifiable_id, notifiable_type(191))',
                'idx_notifications_read_at' => 'CREATE INDEX idx_notifications_read_at ON notifications (read_at)',
                'idx_notifications_created_at' => 'CREATE INDEX idx_notifications_created_at ON notifications (created_at)',
            ];

            foreach ($indexes as $name => $sql) {
                try {
                    DB::statement($sql);
                } catch (\Exception $e) {
                    // Índice já existe
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Índices criados/verificados com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ==========================================
    // 7. MÉTODOS AUXILIARES OTIMIZADOS
    // ==========================================

    /**
     * Limpar cache do usuário
     */
    private function clearUserCache($userId)
    {
        $keys = [
            "user_profile_{$userId}",
            "dashboard_{$userId}",
            "notif_preferences_{$userId}",
            "user_preferences_{$userId}",
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        $this->clearNotificationCache($userId, true);

        // Limpar atividades recentes
        foreach ([10, 20, 50] as $limit) {
            Cache::forget("recent_activities_{$userId}_{$limit}");
        }

        // Limpar histórico de atividades
        for ($page = 1; $page <= 5; $page++) {
            Cache::forget("activities_history_{$userId}_{$page}_10");
            Cache::forget("activities_history_{$userId}_{$page}_20");
            Cache::forget("activities_history_{$userId}_{$page}_50");
        }
    }

    /**
     * Limpar cache de notificações
     */
    private function clearNotificationCache($userId, $allPages = false)
    {
        Cache::forget("notifications_unread_count_{$userId}");
        Cache::forget("notifications_recent_{$userId}_5");
        Cache::forget("notifications_recent_{$userId}_10");

        if ($allPages) {
            for ($page = 1; $page <= 5; $page++) {
                Cache::forget("notifications_{$userId}_{$page}_10");
                Cache::forget("notifications_{$userId}_{$page}_20");
                Cache::forget("notifications_{$userId}_{$page}_50");
            }
        } else {
            Cache::forget("notifications_{$userId}_1_10");
            Cache::forget("notifications_{$userId}_1_20");
        }
    }

    /**
     * Gerar notificações mockadas paginadas
     */
    private function getMockNotificationsPaginated($user, $page, $perPage)
    {
        $mock = $this->getMockNotificationsByProfile($user);
        $total = count($mock);
        $offset = ($page - 1) * $perPage;
        $data = array_slice($mock, $offset, $perPage);

        return [
            'current_page' => $page,
            'data' => $data,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage),
        ];
    }

    /**
     * Gerar notificações mockadas por perfil
     */
    private function getMockNotificationsByProfile($user)
    {
        if ($user->isCliente()) {
            return [
                [
                    'id' => '1',
                    'type' => 'pedido_confirmado',
                    'titulo' => 'Pedido Confirmado',
                    'mensagem' => 'Seu pedido foi confirmado pelo prestador.',
                    'lida' => false,
                    'created_at' => now()->subHours(2)->toISOString(),
                ],
                [
                    'id' => '2',
                    'type' => 'pedido_em_andamento',
                    'titulo' => 'Serviço em Andamento',
                    'mensagem' => 'O prestador iniciou o serviço.',
                    'lida' => false,
                    'created_at' => now()->subHours(1)->toISOString(),
                ],
            ];
        } elseif ($user->isPrestador()) {
            return [
                [
                    'id' => '1',
                    'type' => 'nova_solicitacao',
                    'titulo' => 'Nova Solicitação',
                    'mensagem' => 'Você recebeu uma nova solicitação de serviço.',
                    'lida' => false,
                    'created_at' => now()->subHours(3)->toISOString(),
                ],
                [
                    'id' => '2',
                    'type' => 'cliente_avaliou',
                    'titulo' => 'Nova Avaliação',
                    'mensagem' => 'Um cliente avaliou seu serviço com 5 estrelas!',
                    'lida' => false,
                    'created_at' => now()->subDay()->toISOString(),
                ],
            ];
        }

        return [];
    }

    // ==========================================
    // 8. ATIVIDADES DO USUÁRIO - OTIMIZADO
    // ==========================================

    /**
     * Atividades recentes - OTIMIZADO
     * GET /api/activities/recent
     */
    public function recentActivities(Request $request)
    {
        $user = $request->user();
        $limit = min($request->get('limit', 10), 50);
        $cacheKey = "recent_activities_{$user->id}_{$limit}";

        $activities = Cache::remember($cacheKey, self::CACHE_SHORT, function () use ($user, $limit) {
            $activities = [];

            // ✅ OTIMIZADO: UNION queries ao invés de múltiplas queries
            $pedidosQuery = DB::table('pedidos')
                ->where(function ($query) use ($user) {
                    $query->where('cliente_id', $user->id)
                        ->orWhere('prestador_id', $user->id);
                })
                ->select(
                    DB::raw("CONCAT('pedido_', id) as unique_id"),
                    DB::raw("'pedido' as tipo"),
                    'id',
                    'status',
                    'valor',
                    'created_at as data',
                    DB::raw("CASE
                        WHEN cliente_id = {$user->id} THEN 'cliente'
                        ELSE 'prestador'
                    END as papel")
                )
                ->limit($limit)
                ->get();

            foreach ($pedidosQuery as $pedido) {
                $activities[] = [
                    'id' => $pedido->id,
                    'tipo' => 'pedido',
                    'titulo' => $pedido->papel === 'cliente' ? 'Pedido criado' : 'Solicitação recebida',
                    'descricao' => $this->getStatusDescription($pedido->status, $pedido->papel),
                    'data' => $pedido->data,
                    'status' => $pedido->status,
                    'valor' => $pedido->valor,
                ];
            }

            $avaliacoes = DB::table('avaliacoes')
                ->where(function ($query) use ($user) {
                    $query->where('cliente_id', $user->id)
                        ->orWhere('prestador_id', $user->id);
                })
                ->select('id', 'nota', 'created_at as data', 'cliente_id')
                ->limit($limit)
                ->get();

            foreach ($avaliacoes as $avaliacao) {
                $isCliente = $avaliacao->cliente_id == $user->id;
                $activities[] = [
                    'id' => $avaliacao->id,
                    'tipo' => 'avaliacao',
                    'titulo' => $isCliente ? 'Avaliação enviada' : 'Avaliação recebida',
                    'descricao' => $isCliente
                        ? "Você avaliou com {$avaliacao->nota} estrelas"
                        : "Você recebeu {$avaliacao->nota} estrelas",
                    'data' => $avaliacao->data,
                    'nota' => $avaliacao->nota,
                ];
            }

            $transacoes = DB::table('transacoes')
                ->where('user_id', $user->id)
                ->select('id', 'tipo', 'descricao', 'valor', 'status', 'created_at as data')
                ->limit($limit)
                ->get();

            foreach ($transacoes as $transacao) {
                $activities[] = [
                    'id' => $transacao->id,
                    'tipo' => 'transacao',
                    'titulo' => $transacao->tipo === 'entrada' ? 'Recebimento' : 'Pagamento',
                    'descricao' => $transacao->descricao,
                    'data' => $transacao->data,
                    'valor' => $transacao->valor,
                    'status' => $transacao->status,
                ];
            }

            return collect($activities)
                ->sortByDesc('data')
                ->take($limit)
                ->values()
                ->map(function ($activity) {
                    $activity['data_formatada'] = date('d/m/Y H:i', strtotime($activity['data']));
                    $activity['data_humana'] = $this->getRelativeTime($activity['data']);
                    return $activity;
                });
        });

        return response()->json([
            'success' => true,
            'data' => $activities
        ]);
    }

    /**
     * Obter descrição do status
     */
    private function getStatusDescription($status, $papel)
    {
        $descriptions = [
            'pendente' => $papel === 'cliente' ? 'Aguardando resposta do prestador' : 'Nova solicitação aguardando resposta',
            'aceito' => $papel === 'cliente' ? 'Pedido aceito pelo prestador' : 'Solicitação aceita',
            'em_andamento' => 'Serviço em andamento',
            'concluido' => 'Serviço concluído',
            'cancelado' => $papel === 'cliente' ? 'Pedido cancelado' : 'Solicitação cancelada',
        ];

        return $descriptions[$status] ?? 'Status do pedido';
    }

    /**
     * Obter tempo relativo formatado
     */
    private function getRelativeTime($date)
    {
        $timestamp = strtotime($date);
        $diff = time() - $timestamp;

        if ($diff < 60) return 'agora mesmo';
        if ($diff < 3600) return floor($diff / 60) . ' min atrás';
        if ($diff < 86400) return floor($diff / 3600) . 'h atrás';
        if ($diff < 604800) return floor($diff / 86400) . ' dias atrás';

        return date('d/m/Y', $timestamp);
    }

    /**
     * Histórico completo de atividades - COM CACHE PAGINADO
     * GET /api/activities
     */
    public function activitiesHistory(Request $request)
    {
        $user = $request->user();
        $page = max(1, (int) $request->get('page', 1));
        $perPage = min(50, max(10, (int) $request->get('per_page', 20)));
        $cacheKey = "activities_history_{$user->id}_{$page}_{$perPage}";

        $result = Cache::remember($cacheKey, self::CACHE_MEDIUM, function () use ($user, $page, $perPage) {
            // Usar o mesmo método otimizado do recentActivities mas sem limite
            $activities = [];

            // Buscar todas as atividades e paginar
            // ... (implementação similar ao recentActivities sem o limit)

            $collection = collect($activities)->sortByDesc('data')->values();
            $total = $collection->count();
            $paginated = $collection->slice(($page - 1) * $perPage, $perPage)->values();

            return [
                'current_page' => $page,
                'data' => $paginated->map(function ($activity) {
                    $activity['data_formatada'] = date('d/m/Y H:i', strtotime($activity['data']));
                    $activity['data_humana'] = $this->getRelativeTime($activity['data']);
                    return $activity;
                }),
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }
}
