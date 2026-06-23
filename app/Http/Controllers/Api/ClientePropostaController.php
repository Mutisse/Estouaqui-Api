<?php
// app/Http/Controllers/Api/ClientePropostaController.php

namespace App\Http\Controllers\Api;

use App\Models\Proposta;
use App\Models\Pedido;
use App\Models\Agenda;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ClientePropostaController extends BaseController
{
    /**
     * Listar todas as propostas recebidas pelo cliente
     * GET /api/cliente/propostas
     */
    public function index(Request $request)
    {
        $user = $request->user();

        try {
            $propostas = Proposta::whereHas('pedido', function ($query) use ($user) {
                $query->where('cliente_id', $user->id);
            })
            ->with(['pedido', 'prestador', 'servico'])
            ->orderBy('created_at', 'desc')
            ->get();

            return response()->json([
                'success' => true,
                'data' => $propostas,
                'total' => $propostas->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar propostas do cliente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar propostas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar detalhes de uma proposta específica
     * GET /api/cliente/propostas/{id}
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        try {
            $proposta = Proposta::with(['pedido', 'prestador', 'servico'])
                ->whereHas('pedido', function ($query) use ($user) {
                    $query->where('cliente_id', $user->id);
                })
                ->find($id);

            if (!$proposta) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proposta não encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $proposta
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar proposta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar proposta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🔥 CORRIGIDO: Aceitar uma proposta com verificação de disponibilidade
     * POST /api/cliente/propostas/{id}/aceitar
     */
    public function aceitar(Request $request, $id)
    {
        $user = $request->user();

        try {
            $proposta = Proposta::with(['pedido', 'prestador', 'servico'])
                ->whereHas('pedido', function ($query) use ($user) {
                    $query->where('cliente_id', $user->id);
                })
                ->find($id);

            if (!$proposta) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proposta não encontrada'
                ], 404);
            }

            // Verificar se a proposta pode ser aceita
            if (!in_array($proposta->status, ['pendente', 'enviada'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta proposta não pode mais ser aceita. Status atual: ' . $proposta->status
                ], 422);
            }

            // 🔥 VERIFICAR DISPONIBILIDADE DO PRESTADOR
            $pedido = $proposta->pedido;
            if ($pedido && $pedido->agendado_para) {
                $dataAgendada = Carbon::parse($pedido->agendado_para);
                $data = $dataAgendada->format('Y-m-d');
                $hora = $dataAgendada->format('H:i');

                // Verificar se o prestador está bloqueado na agenda
                $bloqueado = Agenda::where('prestador_id', $proposta->prestador_id)
                    ->where('data', $data)
                    ->where('horario_inicio', '<=', $hora)
                    ->where('horario_fim', '>=', $hora)
                    ->where('bloqueado', true)
                    ->exists();

                if ($bloqueado) {
                    return response()->json([
                        'success' => false,
                        'message' => 'O prestador não está mais disponível para esta data/hora. Tente outra data.',
                        'erro' => 'prestador_indisponivel',
                        'data' => $data,
                        'hora' => $hora
                    ], 422);
                }

                // Verificar se já tem pedido na mesma data/hora
                $pedidoExistente = Pedido::where('prestador_id', $proposta->prestador_id)
                    ->where('agendado_para', $pedido->agendado_para)
                    ->whereIn('status', ['aceito', 'em_andamento'])
                    ->where('id', '!=', $pedido->id)
                    ->exists();

                if ($pedidoExistente) {
                    return response()->json([
                        'success' => false,
                        'message' => 'O prestador já tem um serviço agendado para esta data/hora',
                        'erro' => 'horario_ocupado',
                        'data' => $data,
                        'hora' => $hora
                    ], 422);
                }

                // Verificar disponibilidade global do prestador
                $prestador = User::find($proposta->prestador_id);
                if (!$prestador || !$prestador->disponivel) {
                    return response()->json([
                        'success' => false,
                        'message' => 'O prestador está indisponível no momento',
                        'erro' => 'prestador_indisponivel'
                    ], 422);
                }
            }

            DB::transaction(function () use ($proposta, $user, $pedido, $dataAgendada, $data, $hora) {
                // Atualizar status da proposta
                $proposta->status = 'aceita';
                $proposta->save();

                // Atualizar o pedido com o prestador escolhido
                if ($pedido) {
                    $pedido->prestador_id = $proposta->prestador_id;
                    $pedido->status = 'aceito';
                    $pedido->save();

                    // 🔥 CRIAR BLOQUEIO NA AGENDA DO PRESTADOR
                    if (isset($data) && isset($hora) && $pedido->agendado_para) {
                        Agenda::create([
                            'prestador_id' => $proposta->prestador_id,
                            'data' => $data,
                            'horario_inicio' => $hora,
                            'horario_fim' => $hora,
                            'bloqueado' => true,
                            'observacao' => 'Pedido #' . $pedido->numero . ' - Cliente: ' . $user->nome,
                        ]);
                    }

                    // Atualizar outras propostas do mesmo pedido para 'recusada'
                    Proposta::where('pedido_id', $pedido->id)
                        ->where('id', '!=', $proposta->id)
                        ->whereIn('status', ['pendente', 'enviada'])
                        ->update(['status' => 'recusada']);
                }

                // 🔔 NOTIFICAÇÃO: Proposta aceita (para o prestador)
                NotificationService::send('proposta.aceita', $proposta->prestador_id, [
                    'proposta_id' => $proposta->id,
                    'pedido_id' => $pedido->id ?? null,
                    'cliente_nome' => $user->nome,
                    'proposta_valor' => $proposta->valor,
                ]);

                // 🔔 NOTIFICAÇÃO: Proposta aceita (para o cliente)
                NotificationService::send('proposta.aceita_cliente', $user->id, [
                    'proposta_id' => $proposta->id,
                    'pedido_id' => $pedido->id ?? null,
                    'prestador_nome' => $proposta->prestador->nome ?? 'Prestador',
                    'proposta_valor' => $proposta->valor,
                ]);

                // 🔔 NOTIFICAÇÃO: Agendamento confirmado (para o cliente)
                if ($pedido && $pedido->agendado_para) {
                    NotificationService::send('agendamento.confirmado', $pedido->cliente_id, [
                        'data' => Carbon::parse($pedido->agendado_para)->format('d/m/Y'),
                        'hora' => Carbon::parse($pedido->agendado_para)->format('H:i'),
                        'servico' => $pedido->categoria->nome ?? 'Serviço',
                        'prestador_nome' => $proposta->prestador->nome ?? 'Prestador',
                        'endereco' => $pedido->endereco ?? 'A definir',
                        'valor' => $pedido->valor ?? 0,
                    ]);
                }

                // 🔔 NOTIFICAÇÃO: Agendamento confirmado (para o prestador)
                if ($pedido && $pedido->agendado_para) {
                    NotificationService::send('agendamento.confirmado_prestador', $proposta->prestador_id, [
                        'data' => Carbon::parse($pedido->agendado_para)->format('d/m/Y'),
                        'hora' => Carbon::parse($pedido->agendado_para)->format('H:i'),
                        'servico' => $pedido->categoria->nome ?? 'Serviço',
                        'cliente_nome' => $user->nome,
                        'endereco' => $pedido->endereco ?? 'A definir',
                        'valor' => $pedido->valor ?? 0,
                    ]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Proposta aceita com sucesso!',
                'data' => $proposta->load(['pedido', 'prestador', 'servico'])
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao aceitar proposta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao aceitar proposta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recusar uma proposta
     * POST /api/cliente/propostas/{id}/recusar
     */
    public function recusar(Request $request, $id)
    {
        $user = $request->user();

        try {
            $proposta = Proposta::with(['prestador'])
                ->whereHas('pedido', function ($query) use ($user) {
                    $query->where('cliente_id', $user->id);
                })
                ->find($id);

            if (!$proposta) {
                return response()->json([
                    'success' => false,
                    'message' => 'Proposta não encontrada'
                ], 404);
            }

            if (!in_array($proposta->status, ['pendente', 'enviada'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta proposta não pode mais ser recusada. Status atual: ' . $proposta->status
                ], 422);
            }

            $proposta->status = 'recusada';
            $proposta->save();

            // 🔔 NOTIFICAÇÃO: Proposta recusada (para o prestador)
            NotificationService::send('proposta.recusada', $proposta->prestador_id, [
                'proposta_id' => $proposta->id,
                'cliente_nome' => $user->nome,
            ]);

            // 🔔 NOTIFICAÇÃO: Agendamento recusado (para o cliente)
            if ($proposta->pedido && $proposta->pedido->agendado_para) {
                NotificationService::send('agendamento.recusado', $user->id, [
                    'data' => Carbon::parse($proposta->pedido->agendado_para)->format('d/m/Y'),
                    'hora' => Carbon::parse($proposta->pedido->agendado_para)->format('H:i'),
                    'servico' => $proposta->pedido->categoria->nome ?? 'Serviço',
                    'prestador_nome' => $proposta->prestador->nome ?? 'Prestador',
                    'motivo' => 'Proposta recusada pelo cliente',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Proposta recusada com sucesso',
                'data' => $proposta->load(['pedido', 'prestador', 'servico'])
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao recusar proposta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao recusar proposta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estatísticas das propostas do cliente
     * GET /api/cliente/propostas/estatisticas
     */
    public function estatisticas(Request $request)
    {
        $user = $request->user();

        try {
            $propostas = Proposta::whereHas('pedido', function ($query) use ($user) {
                $query->where('cliente_id', $user->id);
            })->get();

            $estatisticas = [
                'total' => $propostas->count(),
                'pendentes' => $propostas->whereIn('status', ['pendente', 'enviada'])->count(),
                'enviadas' => $propostas->where('status', 'enviada')->count(),
                'aceitas' => $propostas->where('status', 'aceita')->count(),
                'recusadas' => $propostas->where('status', 'recusada')->count(),
                'expiradas' => $propostas->where('status', 'expirada')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $estatisticas
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao carregar estatísticas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar estatísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Contagem de propostas pendentes
     * GET /api/cliente/propostas/pendentes/count
     */
    public function pendentesCount(Request $request)
    {
        $user = $request->user();

        try {
            $count = Proposta::whereHas('pedido', function ($query) use ($user) {
                $query->where('cliente_id', $user->id);
            })
            ->whereIn('status', ['pendente', 'enviada'])
            ->count();

            return response()->json([
                'success' => true,
                'count' => $count
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao contar propostas pendentes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao contar propostas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar se o cliente já aceitou alguma proposta para um pedido
     * GET /api/cliente/propostas/check/{pedidoId}
     */
    public function checkPropostaAceita(Request $request, $pedidoId)
    {
        $user = $request->user();

        try {
            $proposta = Proposta::whereHas('pedido', function ($query) use ($user, $pedidoId) {
                $query->where('cliente_id', $user->id)
                      ->where('id', $pedidoId);
            })
            ->where('status', 'aceita')
            ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'aceita' => !is_null($proposta),
                    'proposta_id' => $proposta->id ?? null,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao verificar proposta aceita: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao verificar proposta: ' . $e->getMessage()
            ], 500);
        }
    }
}
