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

/**
 * Controller genérico para funcionalidades comuns a todos os usuários
 * (cliente, prestador, admin)
 */
class UsuarioController extends Controller
{
    // ==========================================
    // 1. FUNCIONALIDADES COMUNS (todos os perfis)
    // ==========================================

    /**
     * Obter perfil do usuário autenticado
     * GET /api/me
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'nome' => $user->nome,
                'email' => $user->email,
                'telefone' => $user->telefone,
                'endereco' => $user->endereco,
                'foto' => $user->foto ? asset('storage/' . $user->foto) : null,
                'tipo' => $user->tipo,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
            ]
        ]);
    }

    /**
     * Atualizar perfil do usuário autenticado
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
     * Atualizar foto de perfil
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
     * Remover foto de perfil
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
     * Alterar senha
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
     * Deletar conta (soft delete)
     * DELETE /api/me
     */
    public function destroy(Request $request)
    {
        $user = $request->user();

        try {
            $user->delete();

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
     * Dashboard do usuário (dados específicos por perfil)
     * GET /api/dashboard
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();

        $data = [
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
            $data['stats']['pedidos_ativos'] = Pedido::where('cliente_id', $user->id)
                ->whereIn('status', ['pendente', 'aceito', 'em_andamento'])
                ->count();
            $data['stats']['pedidos_concluidos'] = Pedido::where('cliente_id', $user->id)
                ->where('status', 'concluido')
                ->count();
            $data['stats']['favoritos'] = $user->favoritos()->count();
            $data['stats']['avaliacoes'] = Avaliacao::where('cliente_id', $user->id)->count();
        } elseif ($user->isPrestador()) {
            $data['stats']['servicos_ativos'] = Pedido::where('prestador_id', $user->id)
                ->whereIn('status', ['pendente', 'aceito', 'em_andamento'])
                ->count();
            $data['stats']['servicos_concluidos'] = Pedido::where('prestador_id', $user->id)
                ->where('status', 'concluido')
                ->count();
            $data['stats']['avaliacoes'] = Avaliacao::where('prestador_id', $user->id)->count();
            $data['stats']['rating'] = $user->media_avaliacao ?? 0;
        } elseif ($user->isAdmin()) {
            $data['stats']['total_usuarios'] = User::count();
            $data['stats']['total_clientes'] = User::where('tipo', 'cliente')->count();
            $data['stats']['total_prestadores'] = User::where('tipo', 'prestador')->count();
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    // ==========================================
    // 2. NOTIFICAÇÕES (AJUSTADAS PARA FUNCIONAR SEM TABELA)
    // ==========================================

    /**
     * Listar notificações do usuário
     * GET /api/notifications
     */
    public function notifications(Request $request)
    {
        $user = $request->user();

        // 🔄 TEMPORÁRIO: Retornar dados mockados até criar tabela de notificações
        // TODO: Substituir por $user->notifications()->paginate(20) quando tabela existir

        $mockNotifications = $this->getMockNotificationsByProfile($user);

        return response()->json([
            'success' => true,
            'data' => [
                'current_page' => 1,
                'data' => $mockNotifications,
                'per_page' => 20,
                'total' => count($mockNotifications),
                'last_page' => 1,
            ]
        ]);
    }

    /**
     * Marcar notificação como lida
     * PUT /api/notifications/{id}/read
     */
    public function markNotificationRead(Request $request, $id)
    {
        // TODO: Implementar quando tabela de notificações existir
        return response()->json(['success' => true]);
    }

    /**
     * Marcar todas notificações como lidas
     * PUT /api/notifications/read-all
     */
    public function markAllNotificationsRead(Request $request)
    {
        // TODO: Implementar quando tabela de notificações existir
        return response()->json(['success' => true]);
    }

    /**
     * Obter preferências de notificação
     * GET /api/notifications/preferences
     */
    public function notificationPreferences(Request $request)
    {
        $user = $request->user();

        // Preferências padrão por perfil
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
        $preferences = $user->preferences['notifications'] ?? $defaultPreferences[$profile] ?? $defaultPreferences['cliente'];

        return response()->json([
            'success' => true,
            'data' => $preferences
        ]);
    }

    /**
     * Atualizar preferências de notificação
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

        return response()->json([
            'success' => true,
            'message' => 'Preferências de notificação atualizadas',
            'data' => $notifPref
        ]);
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
                    'title' => 'Pedido Confirmado',
                    'message' => 'Seu pedido #PED-123 foi confirmado pelo prestador.',
                    'data' => ['pedido_id' => 123],
                    'read_at' => null,
                    'created_at' => now()->subHours(2)->toISOString(),
                ],
                [
                    'id' => '2',
                    'type' => 'pedido_em_andamento',
                    'title' => 'Serviço em Andamento',
                    'message' => 'O prestador iniciou o serviço do seu pedido #PED-123.',
                    'data' => ['pedido_id' => 123],
                    'read_at' => null,
                    'created_at' => now()->subHours(1)->toISOString(),
                ],
                [
                    'id' => '3',
                    'type' => 'promocao_nova',
                    'title' => 'Nova Promoção!',
                    'message' => '20% de desconto no seu primeiro serviço. Use o cupom BEMVINDO20',
                    'data' => [],
                    'read_at' => now()->subMinutes(30)->toISOString(),
                    'created_at' => now()->subHours(5)->toISOString(),
                ],
            ];
        } elseif ($user->isPrestador()) {
            $notifications = [
                [
                    'id' => '1',
                    'type' => 'nova_solicitacao',
                    'title' => 'Nova Solicitação',
                    'message' => 'Você recebeu uma nova solicitação de serviço.',
                    'data' => ['pedido_id' => 456],
                    'read_at' => null,
                    'created_at' => now()->subHours(3)->toISOString(),
                ],
                [
                    'id' => '2',
                    'type' => 'cliente_avaliou',
                    'title' => 'Nova Avaliação',
                    'message' => 'Um cliente avaliou seu serviço com 5 estrelas!',
                    'data' => ['avaliacao_id' => 789],
                    'read_at' => null,
                    'created_at' => now()->subDay()->toISOString(),
                ],
            ];
        } elseif ($user->isAdmin()) {
            $notifications = [
                [
                    'id' => '1',
                    'type' => 'novo_prestador_pendente',
                    'title' => 'Novo Prestador Pendente',
                    'message' => 'Um novo prestador aguarda aprovação.',
                    'data' => ['prestador_id' => 101],
                    'read_at' => null,
                    'created_at' => now()->subHours(6)->toISOString(),
                ],
                [
                    'id' => '2',
                    'type' => 'relatorio_semanal',
                    'title' => 'Relatório Semanal',
                    'message' => 'Relatório da semana está disponível para download.',
                    'data' => [],
                    'read_at' => now()->subDays(2)->toISOString(),
                    'created_at' => now()->subDays(2)->toISOString(),
                ],
            ];
        }

        return $notifications;
    }

    // ==========================================
    // 3. PREFERÊNCIAS DO USUÁRIO
    // ==========================================

    /**
     * Obter preferências do usuário
     * GET /api/preferences
     */
    public function preferences(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'theme' => $user->preferences['theme'] ?? 'light',
                'language' => $user->preferences['language'] ?? 'pt',
                'notifications' => $user->preferences['notifications'] ?? true,
            ]
        ]);
    }

    /**
     * Atualizar preferências
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

        return response()->json([
            'success' => true,
            'message' => 'Preferências atualizadas',
            'data' => $preferences
        ]);
    }

    // ==========================================
    // 4. ENDEREÇOS DO USUÁRIO (TODO)
    // ==========================================

    /**
     * Listar endereços do usuário
     * GET /api/addresses
     */
    public function addresses(Request $request)
    {
        // TODO: Implementar quando criar tabela de endereços
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
        // TODO: Implementar quando criar tabela de endereços
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
        // TODO: Implementar quando criar tabela de endereços
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
        // TODO: Implementar quando criar tabela de endereços
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
        // TODO: Implementar quando criar tabela de endereços
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
        // TODO: Implementar quando criar tabela de endereços
        return response()->json([
            'success' => true,
            'message' => 'Endereço principal definido com sucesso'
        ]);
    }

    // ==========================================
    // 5. MÉTODOS PÚBLICOS (sem autenticação)
    // ==========================================

    /**
     * Verificar disponibilidade de email
     * GET /api/check-email?email=
     */
    public function checkEmail(Request $request)
    {
        $email = $request->query('email');
        $exists = User::where('email', $email)->exists();

        return response()->json([
            'available' => !$exists
        ]);
    }

    /**
     * Verificar disponibilidade de telefone
     * GET /api/check-phone?phone=
     */
    public function checkPhone(Request $request)
    {
        $phone = $request->query('phone');
        $exists = User::where('telefone', $phone)->exists();

        return response()->json([
            'available' => !$exists
        ]);
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
    // 6. ATIVIDADES DO USUÁRIO
    // ==========================================

    /**
     * Atividades recentes do usuário
     * GET /api/activities/recent
     */
    public function recentActivities(Request $request)
    {
        $user = $request->user();
        $limit = $request->get('limit', 10);

        $activities = [];

        // Últimos pedidos (como cliente)
        $pedidosCliente = Pedido::where('cliente_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        foreach ($pedidosCliente as $pedido) {
            $activities[] = [
                'id' => $pedido->id,
                'tipo' => 'pedido',
                'titulo' => 'Pedido realizado',
                'descricao' => $this->getPedidoDescricao($pedido),
                'data' => $pedido->created_at,
                'status' => $pedido->status,
                'valor' => $pedido->valor,
            ];
        }

        // Últimos pedidos (como prestador)
        $pedidosPrestador = Pedido::where('prestador_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        foreach ($pedidosPrestador as $pedido) {
            $activities[] = [
                'id' => $pedido->id,
                'tipo' => 'solicitacao',
                'titulo' => 'Nova solicitação',
                'descricao' => $this->getPedidoDescricaoPrestador($pedido),
                'data' => $pedido->created_at,
                'status' => $pedido->status,
                'valor' => $pedido->valor,
            ];
        }

        // Últimas avaliações
        $avaliacoes = Avaliacao::where('cliente_id', $user->id)
            ->orWhere('prestador_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        foreach ($avaliacoes as $avaliacao) {
            $isCliente = $avaliacao->cliente_id === $user->id;
            $activities[] = [
                'id' => $avaliacao->id,
                'tipo' => 'avaliacao',
                'titulo' => $isCliente ? 'Avaliação enviada' : 'Avaliação recebida',
                'descricao' => $isCliente
                    ? "Você avaliou um serviço com {$avaliacao->nota} estrelas"
                    : "Você recebeu {$avaliacao->nota} estrelas em uma avaliação",
                'data' => $avaliacao->created_at,
                'nota' => $avaliacao->nota,
            ];
        }

        // Últimas transações
        $transacoes = Transacao::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        foreach ($transacoes as $transacao) {
            $activities[] = [
                'id' => $transacao->id,
                'tipo' => 'transacao',
                'titulo' => $transacao->tipo === 'entrada' ? 'Pagamento recebido' : 'Pagamento efetuado',
                'descricao' => $transacao->descricao,
                'data' => $transacao->created_at,
                'valor' => $transacao->valor,
                'tipo_transacao' => $transacao->tipo,
                'status' => $transacao->status,
            ];
        }

        // Ordenar por data e pegar os mais recentes
        $activities = collect($activities)
            ->sortByDesc('data')
            ->take($limit)
            ->values()
            ->map(function ($activity) {
                $activity['data_formatada'] = $activity['data']->format('d/m/Y H:i');
                $activity['data_humana'] = $activity['data']->diffForHumans();
                return $activity;
            });

        return response()->json([
            'success' => true,
            'data' => $activities
        ]);
    }

    /**
     * Histórico completo de atividades
     * GET /api/activities
     */
    public function activitiesHistory(Request $request)
    {
        $user = $request->user();
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);

        $activities = [];

        // Pedidos como cliente
        $pedidosCliente = Pedido::where('cliente_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($pedidosCliente as $pedido) {
            $activities[] = [
                'id' => $pedido->id,
                'tipo' => 'pedido',
                'titulo' => 'Pedido #' . $pedido->numero,
                'descricao' => $this->getPedidoDescricao($pedido),
                'data' => $pedido->created_at,
                'status' => $pedido->status,
                'valor' => $pedido->valor,
                'prestador_nome' => $pedido->prestador->nome ?? 'Prestador',
            ];
        }

        // Pedidos como prestador
        $pedidosPrestador = Pedido::where('prestador_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($pedidosPrestador as $pedido) {
            $activities[] = [
                'id' => $pedido->id,
                'tipo' => 'solicitacao',
                'titulo' => 'Solicitação #' . $pedido->numero,
                'descricao' => $this->getPedidoDescricaoPrestador($pedido),
                'data' => $pedido->created_at,
                'status' => $pedido->status,
                'valor' => $pedido->valor,
                'cliente_nome' => $pedido->cliente->nome ?? 'Cliente',
            ];
        }

        // Avaliações
        $avaliacoes = Avaliacao::where('cliente_id', $user->id)
            ->orWhere('prestador_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($avaliacoes as $avaliacao) {
            $isCliente = $avaliacao->cliente_id === $user->id;
            $activities[] = [
                'id' => $avaliacao->id,
                'tipo' => 'avaliacao',
                'titulo' => $isCliente ? 'Avaliação enviada' : 'Avaliação recebida',
                'descricao' => $isCliente
                    ? "Você avaliou o prestador com {$avaliacao->nota} estrelas"
                    : "O cliente te avaliou com {$avaliacao->nota} estrelas",
                'data' => $avaliacao->created_at,
                'nota' => $avaliacao->nota,
                'comentario' => $avaliacao->comentario,
            ];
        }

        // Transações
        $transacoes = Transacao::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($transacoes as $transacao) {
            $activities[] = [
                'id' => $transacao->id,
                'tipo' => 'transacao',
                'titulo' => $transacao->tipo === 'entrada' ? 'Recebimento' : 'Pagamento',
                'descricao' => $transacao->descricao,
                'data' => $transacao->created_at,
                'valor' => $transacao->valor,
                'status' => $transacao->status,
                'metodo' => $transacao->metodo,
                'numero' => $transacao->numero,
            ];
        }

        // Ordenar por data
        $activities = collect($activities)
            ->sortByDesc('data')
            ->values()
            ->map(function ($activity) {
                $activity['data_formatada'] = $activity['data']->format('d/m/Y H:i');
                $activity['data_humana'] = $activity['data']->diffForHumans();
                return $activity;
            });

        // Paginar manualmente
        $total = $activities->count();
        $paginated = $activities->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'success' => true,
            'data' => [
                'current_page' => $page,
                'data' => $paginated,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ]
        ]);
    }

    /**
     * Obter descrição do pedido para cliente
     */
    private function getPedidoDescricao($pedido)
    {
        switch ($pedido->status) {
            case 'pendente':
                return 'Pedido aguardando confirmação do prestador';
            case 'aceito':
                return 'Pedido aceito. Serviço será realizado em breve';
            case 'em_andamento':
                return 'Serviço em andamento';
            case 'concluido':
                return 'Serviço concluído com sucesso';
            case 'cancelado':
                return 'Pedido cancelado';
            default:
                return 'Pedido criado';
        }
    }

    /**
     * Obter descrição do pedido para prestador
     */
    private function getPedidoDescricaoPrestador($pedido)
    {
        switch ($pedido->status) {
            case 'pendente':
                return 'Nova solicitação de serviço aguardando sua resposta';
            case 'aceito':
                return 'Solicitação aceita. Prepare-se para realizar o serviço';
            case 'em_andamento':
                return 'Serviço em andamento';
            case 'concluido':
                return 'Serviço concluído';
            case 'cancelado':
                return 'Solicitação cancelada pelo cliente';
            default:
                return 'Nova solicitação';
        }
    }
}
