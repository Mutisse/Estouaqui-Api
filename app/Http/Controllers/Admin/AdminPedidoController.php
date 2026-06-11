<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\Proposta;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class AdminPedidoController extends Controller
{
    /**
     * Cache time (5 minutes)
     */
    private const CACHE_TIME = 300;
    private const PER_PAGE_DEFAULT = 15;

    /**
     * Listar todos os pedidos com paginação e filtros (OTIMIZADO - SEM CACHE PROBLEMÁTICO)
     * GET /admin/pedidos
     */
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->input('per_page', self::PER_PAGE_DEFAULT);

            $query = Pedido::with(['cliente:id,nome,email', 'prestador:id,nome,email', 'categoria:id,nome'])
                ->select(['id', 'numero', 'descricao', 'valor', 'status', 'cliente_id', 'prestador_id', 'categoria_id', 'created_at', 'concluido_em']);

            // Aplicar filtros
            $this->applyFilters($query, $request);

            $pedidos = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Buscar total de propostas em uma única query
            $pedidoIds = $pedidos->pluck('id')->toArray();
            $totalPropostas = Proposta::whereIn('pedido_id', $pedidoIds)
                ->select('pedido_id', DB::raw('count(*) as total'))
                ->groupBy('pedido_id')
                ->get()
                ->keyBy('pedido_id');

            // Converter para array para evitar problemas de serialização
            $items = [];
            foreach ($pedidos as $pedido) {
                $item = $pedido->toArray();
                $item['total_propostas'] = $totalPropostas[$pedido->id]->total ?? 0;

                // Garantir que relacionamentos existem
                $item['cliente'] = $pedido->cliente ? $pedido->cliente->toArray() : null;
                $item['prestador'] = $pedido->prestador ? $pedido->prestador->toArray() : null;
                $item['categoria'] = $pedido->categoria ? $pedido->categoria->toArray() : null;

                $items[] = $item;
            }

            // Estatísticas (cache separado com array)
            $estatisticas = $this->getEstatisticas();

            return response()->json([
                'success' => true,
                'data' => $items,
                'current_page' => $pedidos->currentPage(),
                'last_page' => $pedidos->lastPage(),
                'per_page' => $pedidos->perPage(),
                'total' => $pedidos->total(),
                'contadores' => $estatisticas
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar pedidos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar um pedido específico
     * GET /admin/pedidos/{id}
     */
    public function show($id)
    {
        try {
            $pedido = Pedido::with(['cliente:id,nome,email,telefone', 'prestador:id,nome,email,telefone', 'categoria:id,nome'])
                ->findOrFail($id);

            $pedido->total_propostas = $pedido->propostas()->count();

            // Carregar propostas com prestador
            $propostas = $pedido->propostas()->with('prestador:id,nome,email,telefone')->get();

            $result = $pedido->toArray();
            $result['propostas'] = $propostas->toArray();
            $result['total_propostas'] = $pedido->total_propostas;
            $result['cliente'] = $pedido->cliente ? $pedido->cliente->toArray() : null;
            $result['prestador'] = $pedido->prestador ? $pedido->prestador->toArray() : null;
            $result['categoria'] = $pedido->categoria ? $pedido->categoria->toArray() : null;

            return response()->json(['success' => true, 'data' => $result]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não encontrado'
            ], 404);
        }
    }

    /**
     * Buscar propostas de um pedido
     * GET /admin/pedidos/{id}/propostas
     */
    public function propostas($id)
    {
        try {
            $pedido = Pedido::findOrFail($id);
            $propostas = $pedido->propostas()->with('prestador:id,nome,email,telefone')->get();

            return response()->json(['success' => true, 'data' => $propostas->toArray()]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar propostas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aceitar uma proposta (COM NOTIFICAÇÃO)
     * POST /admin/propostas/{id}/aceitar
     */
    public function aceitarProposta($id)
    {
        try {
            $proposta = Proposta::with(['pedido', 'prestador', 'pedido.cliente'])->findOrFail($id);
            $pedido = $proposta->pedido;

            if ($pedido->status !== 'pendente') {
                return response()->json([
                    'success' => false,
                    'message' => 'Este pedido já não está pendente'
                ], 422);
            }

            DB::beginTransaction();

            // Atualizar proposta
            $proposta->status = 'aceita';
            $proposta->save();

            // Atualizar pedido
            $pedido->prestador_id = $proposta->prestador_id;
            $pedido->status = 'aceito';
            $pedido->valor = $proposta->valor;
            $pedido->save();

            // Recusar outras propostas
            Proposta::where('pedido_id', $pedido->id)
                ->where('id', '!=', $id)
                ->update(['status' => 'recusada']);

            DB::commit();

            // 🔔 NOTIFICAÇÕES
            if ($pedido->cliente_id) {
                NotificationService::send(
                    'pedido.aceito',
                    $pedido->cliente_id,
                    [
                        'numero' => $pedido->numero,
                        'prestador_nome' => $proposta->prestador->nome ?? 'Prestador',
                        'valor' => $proposta->valor
                    ]
                );
            }

            if ($proposta->prestador_id) {
                NotificationService::send(
                    'pedido.aceito_prestador',
                    $proposta->prestador_id,
                    [
                        'numero' => $pedido->numero,
                        'cliente_nome' => $pedido->cliente->nome ?? 'Cliente'
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Proposta aceita com sucesso'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erro ao aceitar proposta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recusar uma proposta (COM NOTIFICAÇÃO)
     * POST /admin/propostas/{id}/recusar
     */
    public function recusarProposta($id)
    {
        try {
            $proposta = Proposta::with(['pedido', 'prestador'])->findOrFail($id);
            $proposta->status = 'recusada';
            $proposta->save();

            if ($proposta->prestador_id) {
                NotificationService::send(
                    'proposta.recusada',
                    $proposta->prestador_id,
                    [
                        'pedido_numero' => $proposta->pedido->numero ?? '#',
                        'motivo' => 'Selecionaram outra proposta'
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Proposta recusada com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao recusar proposta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar status do pedido (COM NOTIFICAÇÃO)
     * PUT /admin/pedidos/{id}/status
     */
    public function atualizarStatus(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:pendente,aceito,em_andamento,concluido,cancelado'
            ]);

            $pedido = Pedido::with(['cliente', 'prestador'])->findOrFail($id);
            $statusAnterior = $pedido->status;
            $pedido->status = $validated['status'];

            if ($validated['status'] === 'concluido') {
                $pedido->concluido_em = now();
            }

            $pedido->save();

            // 🔔 NOTIFICAÇÕES baseadas no novo status
            $this->sendStatusNotifications($pedido, $statusAnterior);

            return response()->json([
                'success' => true,
                'data' => $pedido,
                'message' => 'Status atualizado com sucesso'
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
                'message' => 'Erro ao atualizar status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancelar pedido (COM NOTIFICAÇÃO)
     * POST /admin/pedidos/{id}/cancelar
     */
    public function cancelar(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'motivo' => 'nullable|string|max:255'
            ]);

            $pedido = Pedido::with(['cliente', 'prestador'])->findOrFail($id);
            $motivo = $validated['motivo'] ?? 'Cancelado pelo administrador';

            $pedido->status = 'cancelado';
            $pedido->save();

            if ($pedido->cliente_id) {
                NotificationService::send(
                    'pedido.cancelado',
                    $pedido->cliente_id,
                    [
                        'numero' => $pedido->numero,
                        'motivo' => $motivo
                    ]
                );
            }

            if ($pedido->prestador_id) {
                NotificationService::send(
                    'pedido.cancelado_prestador',
                    $pedido->prestador_id,
                    [
                        'numero' => $pedido->numero,
                        'motivo' => $motivo
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'data' => $pedido,
                'message' => 'Pedido cancelado com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao cancelar pedido: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar pedido
     * DELETE /admin/pedidos/{id}
     */
    public function destroy($id)
    {
        try {
            $pedido = Pedido::findOrFail($id);

            if ($pedido->status !== 'pendente') {
                return response()->json([
                    'success' => false,
                    'message' => 'Apenas pedidos pendentes podem ser excluídos'
                ], 422);
            }

            $pedido->delete();

            return response()->json([
                'success' => true,
                'message' => 'Pedido excluído com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir pedido: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estatísticas de pedidos (COM CACHE)
     * GET /admin/pedidos/estatisticas
     */
    public function estatisticas()
    {
        try {
            $estatisticas = $this->getEstatisticas();

            return response()->json(['success' => true, 'data' => $estatisticas]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar estatísticas: ' . $e->getMessage()
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
        $status = $request->input('status');
        $dataInicio = $request->input('data_inicio');
        $dataFim = $request->input('data_fim');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhere('descricao', 'like', "%{$search}%");
            });
        }

        if ($status && in_array($status, ['pendente', 'aceito', 'em_andamento', 'concluido', 'cancelado'])) {
            $query->where('status', $status);
        }

        if ($dataInicio) {
            $query->whereDate('created_at', '>=', $dataInicio);
        }

        if ($dataFim) {
            $query->whereDate('created_at', '<=', $dataFim);
        }
    }

    /**
     * Busca estatísticas (com cache em array)
     */
    private function getEstatisticas(): array
    {
        // Usar cache simples com array para evitar problemas
        $cacheKey = 'pedidos_estatisticas_array';

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $estatisticas = [
            'total' => Pedido::count(),
            'pendentes' => Pedido::where('status', 'pendente')->count(),
            'aceitos' => Pedido::where('status', 'aceito')->count(),
            'em_andamento' => Pedido::where('status', 'em_andamento')->count(),
            'concluidos' => Pedido::where('status', 'concluido')->count(),
            'cancelados' => Pedido::where('status', 'cancelado')->count(),
            'valor_total' => (float) Pedido::where('status', 'concluido')->sum('valor'),
            'valor_medio' => (float) Pedido::where('status', 'concluido')->avg('valor') ?? 0,
        ];

        Cache::put($cacheKey, $estatisticas, self::CACHE_TIME);

        return $estatisticas;
    }

    /**
     * Envia notificações baseadas na mudança de status
     */
    private function sendStatusNotifications($pedido, string $statusAnterior): void
    {
        $novoStatus = $pedido->status;

        if ($statusAnterior === $novoStatus) {
            return;
        }

        switch ($novoStatus) {
            case 'em_andamento':
                if ($pedido->cliente_id) {
                    NotificationService::send(
                        'pedido.em_andamento',
                        $pedido->cliente_id,
                        [
                            'numero' => $pedido->numero,
                            'prestador_nome' => $pedido->prestador->nome ?? 'Prestador'
                        ]
                    );
                }
                break;

            case 'concluido':
                if ($pedido->cliente_id) {
                    NotificationService::send(
                        'pedido.concluido',
                        $pedido->cliente_id,
                        ['numero' => $pedido->numero]
                    );
                }
                if ($pedido->prestador_id) {
                    NotificationService::send(
                        'pedido.concluido_prestador',
                        $pedido->prestador_id,
                        [
                            'numero' => $pedido->numero,
                            'cliente_nome' => $pedido->cliente->nome ?? 'Cliente'
                        ]
                    );
                }
                break;
        }
    }
}
