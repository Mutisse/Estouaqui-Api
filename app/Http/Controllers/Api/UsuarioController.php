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

/**
 * Controller genérico para funcionalidades comuns a todos os usuários
 * (cliente, prestador, admin)
 */
class UsuarioController extends Controller
{
    // ==========================================
    // 1. FUNCIONALIDADES COMUNS (todos os perfis) - COM CACHE
    // ==========================================

    /**
     * Obter perfil do usuário autenticado - COM CACHE
     * GET /api/me
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $cacheKey = "user_profile_{$user->id}";

        $data = Cache::remember($cacheKey, 3600, function() use ($user) {
            return [
                'id' => $user->id,
                'nome' => $user->nome,
                'email' => $user->email,
                'telefone' => $user->telefone,
                'endereco' => $user->endereco,
                'foto' => $user->foto ? asset('storage/' . $user->foto) : null,
                'tipo' => $user->tipo,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
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
     * Dashboard do usuário - COM CACHE
     * GET /api/dashboard
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $cacheKey = "dashboard_{$user->id}";

        $data = Cache::remember($cacheKey, 300, function() use ($user) {
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

            // Estatísticas específicas por perfil
            if ($user->isCliente()) {
                $result['stats']['pedidos_ativos'] = Pedido::where('cliente_id', $user->id)
                    ->whereIn('status', ['pendente', 'aceito', 'em_andamento'])
                    ->count();
                $result['stats']['pedidos_concluidos'] = Pedido::where('cliente_id', $user->id)
                    ->where('status', 'concluido')
                    ->count();
                $result['stats']['favoritos'] = $user->favoritos()->count();
                $result['stats']['avaliacoes'] = Avaliacao::where('cliente_id', $user->id)->count();
            } elseif ($user->isPrestador()) {
                $result['stats']['servicos_ativos'] = Pedido::where('prestador_id', $user->id)
                    ->whereIn('status', ['pendente', 'aceito', 'em_andamento'])
                    ->count();
                $result['stats']['servicos_concluidos'] = Pedido::where('prestador_id', $user->id)
                    ->where('status', 'concluido')
                    ->count();
                $result['stats']['avaliacoes'] = Avaliacao::where('prestador_id', $user->id)->count();
                $result['stats']['rating'] = $user->media_avaliacao ?? 0;
            } elseif ($user->isAdmin()) {
                $result['stats']['total_usuarios'] = User::count();
                $result['stats']['total_clientes'] = User::where('tipo', 'cliente')->count();
                $result['stats']['total_prestadores'] = User::where('tipo', 'prestador')->count();
            }

            return $result;
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    // ==========================================
    // 2. NOTIFICAÇÕES - COM CACHE
    // ==========================================

    /**
     * Listar notificações do usuário - COM CACHE
     * GET /api/notifications
     */
    public function notifications(Request $request)
    {
        $user = $request->user();
        $cacheKey = "notifications_{$user->id}";

        $notifications = Cache::remember($cacheKey, 120, function() use ($user) {
            return $this->getMockNotificationsByProfile($user);
        });

        return response()->json([
            'success' => true,
            'data' => [
                'current_page' => 1,
                'data' => $notifications,
                'per_page' => 20,
                'total' => count($notifications),
                'last_page' => 1,
            ]
        ]);
    }

    /**
     * Marcar notificação como lida - LIMPAR CACHE
     * PUT /api/notifications/{id}/read
     */
    public function markNotificationRead(Request $request, $id)
    {
        $user = $request->user();
        Cache::forget("notifications_{$user->id}");
        return response()->json(['success' => true]);
    }

    /**
     * Marcar todas notificações como lidas - LIMPAR CACHE
     * PUT /api/notifications/read-all
     */
    public function markAllNotificationsRead(Request $request)
    {
        $user = $request->user();
        Cache::forget("notifications_{$user->id}");
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

        $preferences = Cache::remember($cacheKey, 3600, function() use ($user) {
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
            return $user->preferences['notifications'] ?? $defaultPreferences[$profile] ?? $defaultPreferences['cliente'];
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

        $preferences = Cache::remember($cacheKey, 3600, function() use ($user) {
            return [
                'theme' => $user->preferences['theme'] ?? 'light',
                'language' => $user->preferences['language'] ?? 'pt',
                'notifications' => $user->preferences['notifications'] ?? true,
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
        if ($request->has('notifications')) $preferences['notifications'] = $request->notifications;

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
    // 4. ENDEREÇOS DO USUÁRIO
    // ==========================================

    /**
     * Listar endereços do usuário
     * GET /api/addresses
     */
    public function addresses(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => []
        ]);
    }

    /**
     * Criar novo endereço
     * POST /api/addresses
     */
    public function createAddress(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Endereço criado com sucesso',
            'data' => []
        ], 201);
    }

    /**
     * Obter endereço específico
     * GET /api/addresses/{id}
     */
    public function getAddress($id)
    {
        return response()->json([
            'success' => true,
            'data' => []
        ]);
    }

    /**
     * Atualizar endereço
     * PUT /api/addresses/{id}
     */
    public function updateAddress(Request $request, $id)
    {
        return response()->json([
            'success' => true,
            'message' => 'Endereço atualizado com sucesso'
        ]);
    }

    /**
     * Deletar endereço
     * DELETE /api/addresses/{id}
     */
    public function deleteAddress($id)
    {
        return response()->json([
            'success' => true,
            'message' => 'Endereço removido com sucesso'
        ]);
    }

    /**
     * Definir endereço principal
     * PUT /api/addresses/{id}/primary
     */
    public function setPrimaryAddress($id)
    {
        return response()->json([
            'success' => true,
            'message' => 'Endereço principal definido com sucesso'
        ]);
    }

    // ==========================================
    // 5. MÉTODOS PÚBLICOS (sem autenticação) - COM CACHE
    // ==========================================

    /**
     * Verificar disponibilidade de email - COM CACHE
     * GET /api/check-email?email=
     */
    public function checkEmail(Request $request)
    {
        $email = $request->query('email');
        $cacheKey = "email_available_" . md5($email);

        $exists = Cache::remember($cacheKey, 300, function() use ($email) {
            return User::where('email', $email)->exists();
        });

        return response()->json(['available' => !$exists]);
    }

    /**
     * Verificar disponibilidade de telefone - COM CACHE
     * GET /api/check-phone?phone=
     */
    public function checkPhone(Request $request)
    {
        $phone = $request->query('phone');
        $cacheKey = "phone_available_" . md5($phone);

        $exists = Cache::remember($cacheKey, 300, function() use ($phone) {
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
    // 6. ATIVIDADES DO USUÁRIO - COM CACHE
    // ==========================================

    /**
     * Atividades recentes do usuário - COM CACHE
     * GET /api/activities/recent
     */
    public function recentActivities(Request $request)
    {
        $user = $request->user();
        $limit = $request->get('limit', 10);
        $cacheKey = "recent_activities_{$user->id}_{$limit}";

        $activities = Cache::remember($cacheKey, 120, function() use ($user, $limit) {
            $activities = [];

            // Últimos pedidos (como cliente)
            $pedidosCliente = Pedido::where('cliente_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            foreach ($pedidosCliente as $pedido) {
                $activities[] = $this->formatPedidoActivity($pedido, 'pedido');
            }

            // Últimos pedidos (como prestador)
            $pedidosPrestador = Pedido::where('prestador_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            foreach ($pedidosPrestador as $pedido) {
                $activities[] = $this->formatPedidoActivity($pedido, 'solicitacao');
            }

            // Últimas avaliações
            $avaliacoes = Avaliacao::where('cliente_id', $user->id)
                ->orWhere('prestador_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            foreach ($avaliacoes as $avaliacao) {
                $activities[] = $this->formatAvaliacaoActivity($avaliacao, $user->id);
            }

            // Últimas transações
            $transacoes = Transacao::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            foreach ($transacoes as $transacao) {
                $activities[] = $this->formatTransacaoActivity($transacao);
            }

            return collect($activities)
                ->sortByDesc('data')
                ->take($limit)
                ->values()
                ->map(function ($activity) {
                    $activity['data_formatada'] = $activity['data']->format('d/m/Y H:i');
                    $activity['data_humana'] = $activity['data']->diffForHumans();
                    return $activity;
                });
        });

        return response()->json([
            'success' => true,
            'data' => $activities
        ]);
    }

    /**
     * Histórico completo de atividades - COM CACHE PAGINADO
     * GET /api/activities
     */
    public function activitiesHistory(Request $request)
    {
        $user = $request->user();
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);
        $cacheKey = "activities_history_{$user->id}_{$page}_{$perPage}";

        $result = Cache::remember($cacheKey, 300, function() use ($user, $page, $perPage) {
            $activities = [];

            // Pedidos como cliente
            $pedidosCliente = Pedido::where('cliente_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($pedidosCliente as $pedido) {
                $activities[] = $this->formatPedidoHistory($pedido, 'pedido', $user->id);
            }

            // Pedidos como prestador
            $pedidosPrestador = Pedido::where('prestador_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($pedidosPrestador as $pedido) {
                $activities[] = $this->formatPedidoHistory($pedido, 'solicitacao', $user->id);
            }

            // Avaliações
            $avaliacoes = Avaliacao::where('cliente_id', $user->id)
                ->orWhere('prestador_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($avaliacoes as $avaliacao) {
                $activities[] = $this->formatAvaliacaoHistory($avaliacao, $user->id);
            }

            // Transações
            $transacoes = Transacao::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($transacoes as $transacao) {
                $activities[] = $this->formatTransacaoHistory($transacao);
            }

            // Ordenar e paginar
            $collection = collect($activities)->sortByDesc('data')->values();
            $total = $collection->count();
            $paginated = $collection->slice(($page - 1) * $perPage, $perPage)->values();

            return [
                'current_page' => $page,
                'data' => $paginated->map(function ($activity) {
                    $activity['data_formatada'] = $activity['data']->format('d/m/Y H:i');
                    $activity['data_humana'] = $activity['data']->diffForHumans();
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

    // ==========================================
    // 7. MÉTODOS AUXILIARES
    // ==========================================

    /**
     * Limpar cache do usuário
     */
    private function clearUserCache($userId)
    {
        Cache::forget("user_profile_{$userId}");
        Cache::forget("dashboard_{$userId}");
        Cache::forget("notifications_{$userId}");
        Cache::forget("notif_preferences_{$userId}");
        Cache::forget("user_preferences_{$userId}");
        Cache::forget("recent_activities_{$userId}_10");

        for ($page = 1; $page <= 5; $page++) {
            Cache::forget("activities_history_{$userId}_{$page}_20");
        }
    }

    /**
     * Gerar notificações mockadas por perfil (temporário)
     */
    private function getMockNotificationsByProfile($user)
    {
        $notifications = [];

        if ($user->isCliente()) {
            $notifications = [
                [
                    'id' => '1',
                    'type' => 'pedido_confirmado',
                    'titulo' => 'Pedido Confirmado',
                    'mensagem' => 'Seu pedido #PED-123 foi confirmado pelo prestador.',
                    'lida' => false,
                    'created_at' => now()->subHours(2)->toISOString(),
                ],
                [
                    'id' => '2',
                    'type' => 'pedido_em_andamento',
                    'titulo' => 'Serviço em Andamento',
                    'mensagem' => 'O prestador iniciou o serviço do seu pedido #PED-123.',
                    'lida' => false,
                    'created_at' => now()->subHours(1)->toISOString(),
                ],
                [
                    'id' => '3',
                    'type' => 'promocao_nova',
                    'titulo' => 'Nova Promoção!',
                    'mensagem' => '20% de desconto no seu primeiro serviço. Use o cupom BEMVINDO20',
                    'lida' => true,
                    'created_at' => now()->subHours(5)->toISOString(),
                ],
            ];
        } elseif ($user->isPrestador()) {
            $notifications = [
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
        } elseif ($user->isAdmin()) {
            $notifications = [
                [
                    'id' => '1',
                    'type' => 'novo_prestador_pendente',
                    'titulo' => 'Novo Prestador Pendente',
                    'mensagem' => 'Um novo prestador aguarda aprovação.',
                    'lida' => false,
                    'created_at' => now()->subHours(6)->toISOString(),
                ],
                [
                    'id' => '2',
                    'type' => 'relatorio_semanal',
                    'titulo' => 'Relatório Semanal',
                    'mensagem' => 'Relatório da semana está disponível para download.',
                    'lida' => true,
                    'created_at' => now()->subDays(2)->toISOString(),
                ],
            ];
        }

        return $notifications;
    }

    /**
     * Formatar atividade de pedido
     */
    private function formatPedidoActivity($pedido, $tipo)
    {
        $descricoes = [
            'pendente' => $tipo === 'pedido' ? 'Pedido aguardando confirmação' : 'Nova solicitação aguardando resposta',
            'aceito' => $tipo === 'pedido' ? 'Pedido aceito' : 'Solicitação aceita',
            'em_andamento' => 'Serviço em andamento',
            'concluido' => 'Serviço concluído',
            'cancelado' => $tipo === 'pedido' ? 'Pedido cancelado' : 'Solicitação cancelada',
        ];

        return [
            'id' => $pedido->id,
            'tipo' => $tipo,
            'titulo' => $tipo === 'pedido' ? 'Pedido #' . $pedido->numero : 'Solicitação #' . $pedido->numero,
            'descricao' => $descricoes[$pedido->status] ?? 'Pedido criado',
            'data' => $pedido->created_at,
            'status' => $pedido->status,
            'valor' => $pedido->valor,
        ];
    }

    /**
     * Formatar avaliação para atividades
     */
    private function formatAvaliacaoActivity($avaliacao, $userId)
    {
        $isCliente = $avaliacao->cliente_id === $userId;
        return [
            'id' => $avaliacao->id,
            'tipo' => 'avaliacao',
            'titulo' => $isCliente ? 'Avaliação enviada' : 'Avaliação recebida',
            'descricao' => $isCliente
                ? "Você avaliou com {$avaliacao->nota} estrelas"
                : "Você recebeu {$avaliacao->nota} estrelas",
            'data' => $avaliacao->created_at,
            'nota' => $avaliacao->nota,
        ];
    }

    /**
     * Formatar transação para atividades
     */
    private function formatTransacaoActivity($transacao)
    {
        return [
            'id' => $transacao->id,
            'tipo' => 'transacao',
            'titulo' => $transacao->tipo === 'entrada' ? 'Recebimento' : 'Pagamento',
            'descricao' => $transacao->descricao,
            'data' => $transacao->created_at,
            'valor' => $transacao->valor,
            'status' => $transacao->status,
        ];
    }

    /**
     * Formatar pedido para histórico
     */
    private function formatPedidoHistory($pedido, $tipo, $userId)
    {
        $base = $this->formatPedidoActivity($pedido, $tipo);

        if ($tipo === 'pedido') {
            $base['prestador_nome'] = $pedido->prestador->nome ?? 'Prestador';
        } else {
            $base['cliente_nome'] = $pedido->cliente->nome ?? 'Cliente';
        }

        return $base;
    }

    /**
     * Formatar avaliação para histórico
     */
    private function formatAvaliacaoHistory($avaliacao, $userId)
    {
        $base = $this->formatAvaliacaoActivity($avaliacao, $userId);

        if ($avaliacao->cliente_id === $userId) {
            $base['prestador_nome'] = $avaliacao->prestador->nome ?? 'Prestador';
        } else {
            $base['cliente_nome'] = $avaliacao->cliente->nome ?? 'Cliente';
        }

        $base['comentario'] = $avaliacao->comentario;
        return $base;
    }

    /**
     * Formatar transação para histórico
     */
    private function formatTransacaoHistory($transacao)
    {
        $base = $this->formatTransacaoActivity($transacao);
        $base['metodo'] = $transacao->metodo;
        $base['numero'] = $transacao->numero;
        return $base;
    }
}
