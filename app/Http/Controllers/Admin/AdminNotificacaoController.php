<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Notificacao;
use App\Models\NotificationTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AdminNotificacaoController extends Controller
{
    private const CACHE_TIME = 300;
    private const PER_PAGE_DEFAULT = 15;

    /**
     * Listar todas as notificações com paginação e filtros
     * GET /admin/notificacoes
     */
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->input('per_page', self::PER_PAGE_DEFAULT);
            $page = (int) $request->input('page', 1);

            $cacheKey = $this->getCacheKey($request);

            $result = Cache::remember($cacheKey, self::CACHE_TIME, function () use ($request, $perPage, $page) {
                $query = Notificacao::query()
                    ->select(['id', 'user_id', 'tipo', 'titulo', 'mensagem', 'lida', 'lida_em', 'created_at']);

                $this->applyFilters($query, $request);

                $notificacoes = $query->orderBy('created_at', 'desc')->paginate($perPage);

                // Converter para o formato esperado pelo frontend
                $items = [];
                foreach ($notificacoes as $notif) {
                    $items[] = [
                        'id' => $notif->id,
                        'user_id' => $notif->user_id,
                        'type' => $notif->tipo,
                        'title' => $notif->titulo,
                        'message' => $notif->mensagem,
                        'read_at' => $notif->lida_em, // mapear lida_em para read_at
                        'created_at' => $notif->created_at,
                        'updated_at' => $notif->updated_at,
                    ];
                }

                return [
                    'data' => $items,
                    'current_page' => $notificacoes->currentPage(),
                    'last_page' => $notificacoes->lastPage(),
                    'per_page' => $notificacoes->perPage(),
                    'total' => $notificacoes->total(),
                ];
            });

            return response()->json(['success' => true, ...$result]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar notificações: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar uma notificação específica
     * GET /admin/notificacoes/{id}
     */
    public function show($id)
    {
        try {
            $notificacao = Notificacao::find($id);

            if (!$notificacao) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notificação não encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $notificacao->id,
                    'user_id' => $notificacao->user_id,
                    'type' => $notificacao->tipo,
                    'title' => $notificacao->titulo,
                    'message' => $notificacao->mensagem,
                    'read_at' => $notificacao->lida_em,
                    'created_at' => $notificacao->created_at,
                    'updated_at' => $notificacao->updated_at,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar notificação: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar notificação (OTIMIZADO)
     * POST /admin/notificacoes/enviar
     */
    public function enviar(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id' => 'nullable|exists:users,id',
                'user_ids' => 'nullable|array',
                'user_ids.*' => 'exists:users,id',
                'tipo_usuario' => 'nullable|in:cliente,prestador,admin,todos',
                'type' => 'required|string',
                'data' => 'required|array',
                'data.title' => 'required|string',
                'data.body' => 'required|string',
                'channels' => 'nullable|array',
            ]);

            $users = $this->getUsersToNotify($validated);
            $enviadas = 0;

            // Usar chunk para processar em lotes
            foreach ($users->chunk(100) as $chunk) {
                $notificacoes = [];
                foreach ($chunk as $user) {
                    $notificacoes[] = [
                        'user_id' => $user->id,
                        'tipo' => $validated['type'],
                        'titulo' => $validated['data']['title'],
                        'mensagem' => $validated['data']['body'],
                        'data' => json_encode($validated['data']),
                        'lida' => 0,
                        'lida_em' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (!empty($notificacoes)) {
                    DB::table('notificacoes')->insert($notificacoes);
                    $enviadas += count($notificacoes);
                }
            }

            // Invalidar cache de listas
            $this->invalidateCaches();

            return response()->json([
                'success' => true,
                'message' => "Notificação enviada para {$enviadas} utilizador(es)"
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
                'message' => 'Erro ao enviar notificação: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar uma notificação
     * DELETE /admin/notificacoes/{id}
     */
    public function destroy($id)
    {
        try {
            $notificacao = Notificacao::find($id);

            if (!$notificacao) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notificação não encontrada'
                ], 404);
            }

            $notificacao->delete();

            $this->invalidateCaches();

            return response()->json([
                'success' => true,
                'message' => 'Notificação excluída com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir notificação: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar notificação como lida
     * PUT /admin/notificacoes/{id}/marcar-lida
     */
    public function marcarComoLida($id)
    {
        try {
            $notificacao = Notificacao::find($id);

            if (!$notificacao) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notificação não encontrada'
                ], 404);
            }

            $notificacao->lida = 1;
            $notificacao->lida_em = now();
            $notificacao->save();

            $this->invalidateCaches();

            return response()->json([
                'success' => true,
                'message' => 'Notificação marcada como lida'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao marcar notificação como lida: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar todas as notificações como lidas
     * PUT /admin/notificacoes/marcar-todas-lidas
     */
    public function marcarTodasComoLidas()
    {
        try {
            DB::table('notificacoes')
                ->where('lida', 0)
                ->update([
                    'lida' => 1,
                    'lida_em' => now()
                ]);

            $this->invalidateCaches();

            return response()->json([
                'success' => true,
                'message' => 'Todas notificações marcadas como lidas'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao marcar notificações como lidas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estatísticas de notificações (COM CACHE)
     * GET /admin/notificacoes/estatisticas
     */
    public function estatisticas()
    {
        try {
            $estatisticas = Cache::remember('admin_notificacoes_estatisticas', self::CACHE_TIME, function () {
                return [
                    'total' => Notificacao::count(),
                    'lidas' => Notificacao::where('lida', 1)->count(),
                    'nao_lidas' => Notificacao::where('lida', 0)->count(),
                    'por_tipo' => Notificacao::select('tipo', DB::raw('count(*) as total'))
                        ->groupBy('tipo')
                        ->get()
                        ->pluck('total', 'tipo')
                        ->toArray(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $estatisticas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar estatísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Templates de notificações (COM CACHE)
     * GET /admin/notificacoes/templates
     */
    public function templates()
    {
        try {
            $templates = Cache::remember('admin_notificacoes_templates', self::CACHE_TIME, function () {
                return NotificationTemplate::where('ativo', true)
                    ->select(['evento as type', 'titulo as title_pt', 'mensagem as body_pt', 'tipo as icon', 'ativo'])
                    ->get()
                    ->map(function ($template) {
                        return [
                            'type' => $template->type,
                            'title_pt' => $template->title_pt,
                            'body_pt' => $template->body_pt,
                            'icon' => $this->getIconForType($template->type),
                            'color' => $this->getColorForType($template->type),
                            'channels' => ['database'],
                        ];
                    });
            });

            return response()->json([
                'success' => true,
                'data' => $templates
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar templates: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== MÉTODOS PRIVADOS ====================

    private function applyFilters($query, Request $request): void
    {
        $search = $request->input('search');
        $tipo = $request->input('tipo');
        $lida = $request->input('lida');
        $dataInicio = $request->input('data_inicio');
        $dataFim = $request->input('data_fim');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('titulo', 'like', "%{$search}%")
                  ->orWhere('mensagem', 'like', "%{$search}%");
            });
        }

        if ($tipo) {
            $query->where('tipo', $tipo);
        }

        if ($lida === 'sim') {
            $query->where('lida', 1);
        } elseif ($lida === 'nao') {
            $query->where('lida', 0);
        }

        if ($dataInicio) {
            $query->whereDate('created_at', '>=', $dataInicio);
        }

        if ($dataFim) {
            $query->whereDate('created_at', '<=', $dataFim);
        }
    }

    private function getUsersToNotify($validated)
    {
        if (isset($validated['user_id'])) {
            return User::where('id', $validated['user_id'])->get();
        }

        if (isset($validated['user_ids']) && !empty($validated['user_ids'])) {
            return User::whereIn('id', $validated['user_ids'])->get();
        }

        if (isset($validated['tipo_usuario'])) {
            if ($validated['tipo_usuario'] === 'todos') {
                return User::all();
            }
            return User::where('tipo', $validated['tipo_usuario'])->get();
        }

        return collect();
    }

    private function getCacheKey(Request $request): string
    {
        $params = [
            'per_page' => $request->input('per_page', self::PER_PAGE_DEFAULT),
            'page' => $request->input('page', 1),
            'search' => $request->input('search', ''),
            'tipo' => $request->input('tipo', ''),
            'lida' => $request->input('lida', ''),
            'data_inicio' => $request->input('data_inicio', ''),
            'data_fim' => $request->input('data_fim', ''),
        ];

        return 'admin_notificacoes_' . md5(json_encode($params));
    }

    private function invalidateCaches(): void
    {
        Cache::forget('admin_notificacoes_estatisticas');
        Cache::forget('admin_notificacoes_templates');

        $keys = Cache::get('admin_notificacoes_keys', []);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        Cache::forget('admin_notificacoes_keys');
    }

    private function getIconForType($type): string
    {
        $icons = [
            'pedido' => 'shopping_cart',
            'promocao' => 'local_offer',
            'sistema' => 'settings',
            'seguranca' => 'security',
            'avaliacao' => 'star',
            'pagamento' => 'payments',
        ];
        return $icons[$type] ?? 'notifications';
    }

    private function getColorForType($type): string
    {
        $colors = [
            'pedido' => 'primary',
            'promocao' => 'warning',
            'sistema' => 'info',
            'seguranca' => 'negative',
            'avaliacao' => 'positive',
            'pagamento' => 'teal',
        ];
        return $colors[$type] ?? 'grey';
    }
}
