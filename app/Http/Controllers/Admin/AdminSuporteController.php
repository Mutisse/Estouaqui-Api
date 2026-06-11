<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\MensagemTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;  // ← ADICIONADO (importante!)
use Carbon\Carbon;

class AdminSuporteController extends Controller
{
    /**
     * Listar todos os tickets com paginação e filtros
     * GET /admin/suporte/tickets
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search');
            $status = $request->input('status');
            $prioridade = $request->input('prioridade');
            $categoria = $request->input('categoria');
            $dataInicio = $request->input('data_inicio');
            $dataFim = $request->input('data_fim');

            $query = Ticket::with(['cliente', 'prestador', 'admin']);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('titulo', 'like', "%{$search}%")
                      ->orWhere('descricao', 'like', "%{$search}%")
                      ->orWhere('numero', 'like', "%{$search}%")
                      ->orWhereHas('cliente', function ($q2) use ($search) {
                          $q2->where('nome', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            if ($status) {
                $query->where('status', $status);
            }

            if ($prioridade) {
                $query->where('prioridade', $prioridade);
            }

            if ($categoria) {
                $query->where('categoria', $categoria);
            }

            if ($dataInicio) {
                $query->whereDate('created_at', '>=', $dataInicio);
            }

            if ($dataFim) {
                $query->whereDate('created_at', '<=', $dataFim);
            }

            $tickets = $query->orderBy('created_at', 'desc')->paginate($perPage);

            $estatisticas = [
                'total' => Ticket::count(),
                'abertos' => Ticket::where('status', 'aberto')->count(),
                'em_andamento' => Ticket::where('status', 'em_andamento')->count(),
                'resolvidos' => Ticket::where('status', 'resolvido')->count(),
                'fechados' => Ticket::where('status', 'fechado')->count(),
                'urgentes' => Ticket::where('prioridade', 'urgente')->where('status', '!=', 'fechado')->count(),
                'tempo_medio_resposta' => $this->getTempoMedioResposta(),
                'tickets_por_categoria' => Ticket::select('categoria', DB::raw('count(*) as total'))
                    ->groupBy('categoria')
                    ->get()
                    ->pluck('total', 'categoria')
                    ->toArray(),
            ];

            return response()->json([
                'success' => true,
                'data' => $tickets->items(),
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
                'estatisticas' => $estatisticas,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar tickets: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar um ticket específico
     * GET /admin/suporte/tickets/{id}
     */
    public function show($id)
    {
        try {
            $ticket = Ticket::with(['cliente', 'prestador', 'admin'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $ticket
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket não encontrado'
            ], 404);
        }
    }

    /**
     * Mensagens de um ticket
     * GET /admin/suporte/tickets/{id}/mensagens
     */
    public function mensagens($id)
    {
        try {
            $mensagens = MensagemTicket::where('ticket_id', $id)
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $mensagens
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar mensagens: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar mensagem em um ticket - CORRIGIDO
     * POST /admin/suporte/tickets/{id}/mensagens
     */
    public function enviarMensagem(Request $request, $id)
    {
        try {
            // ✅ CORREÇÃO: Usar Auth::id() em vez de auth()->id()
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
            }

            $validated = $request->validate([
                'mensagem' => 'required|string',
                'anexos' => 'nullable|array',
            ]);

            // Buscar o nome do usuário
            $user = User::find($userId);
            $userName = $user ? ($user->name ?? $user->nome ?? 'Admin') : 'Admin';

            $mensagem = MensagemTicket::create([
                'ticket_id' => $id,
                'remetente_id' => $userId,
                'remetente_tipo' => 'admin',
                'remetente_nome' => $userName,
                'mensagem' => $validated['mensagem'],
                'lida' => false,
            ]);

            Ticket::where('id', $id)->update(['updated_at' => now()]);

            return response()->json([
                'success' => true,
                'data' => $mensagem,
                'message' => 'Mensagem enviada com sucesso'
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
                'message' => 'Erro ao enviar mensagem: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar status do ticket
     * PUT /admin/suporte/tickets/{id}/status
     */
    public function atualizarStatus(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:aberto,em_andamento,resolvido,fechado'
            ]);

            $ticket = Ticket::findOrFail($id);
            $ticket->status = $validated['status'];

            if ($validated['status'] === 'resolvido') {
                $ticket->resolvido_em = now();
            }

            $ticket->save();

            return response()->json([
                'success' => true,
                'data' => $ticket,
                'message' => 'Status atualizado com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar prioridade do ticket
     * PUT /admin/suporte/tickets/{id}/prioridade
     */
    public function atualizarPrioridade(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'prioridade' => 'required|in:baixa,media,alta,urgente'
            ]);

            $ticket = Ticket::findOrFail($id);
            $ticket->prioridade = $validated['prioridade'];
            $ticket->save();

            return response()->json([
                'success' => true,
                'data' => $ticket,
                'message' => 'Prioridade atualizada com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar prioridade: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atribuir admin ao ticket
     * PUT /admin/suporte/tickets/{id}/atribuir
     */
    public function atribuirAdmin(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'admin_id' => 'required|exists:users,id'
            ]);

            $ticket = Ticket::findOrFail($id);
            $ticket->admin_id = $validated['admin_id'];
            $ticket->save();

            return response()->json([
                'success' => true,
                'message' => 'Ticket atribuído com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atribuir ticket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar um ticket
     * DELETE /admin/suporte/tickets/{id}
     */
    public function destroy($id)
    {
        try {
            $ticket = Ticket::findOrFail($id);
            MensagemTicket::where('ticket_id', $id)->delete();
            $ticket->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ticket excluído com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir ticket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estatísticas de suporte
     * GET /admin/suporte/estatisticas
     */
    public function estatisticas()
    {
        try {
            $estatisticas = [
                'total' => Ticket::count(),
                'abertos' => Ticket::where('status', 'aberto')->count(),
                'em_andamento' => Ticket::where('status', 'em_andamento')->count(),
                'resolvidos' => Ticket::where('status', 'resolvido')->count(),
                'fechados' => Ticket::where('status', 'fechado')->count(),
                'urgentes' => Ticket::where('prioridade', 'urgente')->where('status', '!=', 'fechado')->count(),
                'tempo_medio_resposta' => $this->getTempoMedioResposta(),
                'tickets_por_categoria' => Ticket::select('categoria', DB::raw('count(*) as total'))
                    ->groupBy('categoria')
                    ->get()
                    ->pluck('total', 'categoria')
                    ->toArray(),
            ];

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
     * Chat - Listar tickets ativos para chat
     * GET /admin/suporte/chat/tickets
     */
    public function chatTickets()
    {
        try {
            $tickets = Ticket::whereIn('status', ['aberto', 'em_andamento'])
                ->with('cliente')
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function ($ticket) {
                    $ultimaMensagem = MensagemTicket::where('ticket_id', $ticket->id)
                        ->orderBy('created_at', 'desc')
                        ->first();

                    $naoLidas = MensagemTicket::where('ticket_id', $ticket->id)
                        ->where('remetente_tipo', 'cliente')
                        ->where('lida', false)
                        ->count();

                    return [
                        'id' => $ticket->id,
                        'numero' => $ticket->numero,
                        'titulo' => $ticket->titulo,
                        'cliente_nome' => $ticket->cliente->nome ?? '—',
                        'cliente_foto' => $ticket->cliente->foto ?? null,
                        'ultima_mensagem' => $ultimaMensagem->mensagem ?? '',
                        'ultima_mensagem_data' => $ultimaMensagem->created_at ?? $ticket->created_at,
                        'nao_lidas' => $naoLidas,
                        'status' => $ticket->status,
                        'prioridade' => $ticket->prioridade,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $tickets
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar chat tickets: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Chat - Mensagens de um ticket
     * GET /admin/suporte/chat/tickets/{id}/mensagens
     */
    public function chatMensagens($id)
    {
        try {
            $mensagens = MensagemTicket::where('ticket_id', $id)
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $mensagens
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar chat mensagens: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Chat - Enviar mensagem - CORRIGIDO
     * POST /admin/suporte/chat/tickets/{id}/enviar
     */
    public function enviarChatMensagem(Request $request, $id)
    {
        try {
            // ✅ CORREÇÃO: Usar Auth::id() em vez de auth()->id()
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não autenticado'
                ], 401);
            }

            $validated = $request->validate([
                'mensagem' => 'required|string'
            ]);

            $user = User::find($userId);
            $userName = $user ? ($user->name ?? $user->nome ?? 'Admin') : 'Admin';

            $mensagem = MensagemTicket::create([
                'ticket_id' => $id,
                'remetente_id' => $userId,
                'remetente_tipo' => 'admin',
                'remetente_nome' => $userName,
                'mensagem' => $validated['mensagem'],
                'lida' => false,
            ]);

            Ticket::where('id', $id)->update(['updated_at' => now()]);

            return response()->json([
                'success' => true,
                'data' => $mensagem,
                'message' => 'Mensagem enviada com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao enviar mensagem: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Chat - Novas mensagens (polling)
     * GET /admin/suporte/chat/tickets/{id}/novas
     */
    public function novasMensagensChat(Request $request, $id)
    {
        try {
            $ultimoId = $request->input('ultimo_id', 0);

            $mensagens = MensagemTicket::where('ticket_id', $id)
                ->where('id', '>', $ultimoId)
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $mensagens
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar novas mensagens: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Chat - Marcar mensagens como lidas
     * PUT /admin/suporte/chat/tickets/{id}/marcar-lidas
     */
    public function marcarChatLidas($id)
    {
        try {
            MensagemTicket::where('ticket_id', $id)
                ->where('remetente_tipo', 'cliente')
                ->where('lida', false)
                ->update(['lida' => true, 'lida_em' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Mensagens marcadas como lidas'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao marcar mensagens: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dashboard - Tickets por período
     * GET /admin/suporte/dashboard/tickets-periodo
     */
    public function ticketsPorPeriodo(Request $request)
    {
        try {
            $dias = $request->input('dias', 30);
            $startDate = Carbon::now()->subDays($dias);

            $tickets = Ticket::select(
                    DB::raw('DATE(created_at) as data'),
                    DB::raw('count(*) as total')
                )
                ->where('created_at', '>=', $startDate)
                ->groupBy('data')
                ->orderBy('data', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $tickets
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar dados: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== MÉTODOS PRIVADOS ====================

    private function getTempoMedioResposta(): float
    {
        $resolvidos = Ticket::whereIn('status', ['resolvido', 'fechado'])
            ->whereNotNull('resolvido_em')
            ->get();

        if ($resolvidos->isEmpty()) {
            return 0;
        }

        $total = 0;
        foreach ($resolvidos as $ticket) {
            $criado = Carbon::parse($ticket->created_at);
            $resolvido = Carbon::parse($ticket->resolvido_em);
            $total += $criado->diffInHours($resolvido);
        }

        return round($total / $resolvidos->count(), 2);
    }
}
