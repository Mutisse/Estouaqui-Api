<?php
// app/Http/Controllers/Api/PrestadorPedidoController.php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Pedido;
use App\Models\Avaliacao;
use App\Models\Proposta;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;


class PrestadorPedidoController extends BaseController
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Pedido::where('prestador_id', $user->id)
            ->with(['cliente' => function ($q) {
                $q->select('id', 'nome', 'foto', 'telefone');
            }, 'categoria']);

        if ($request->has('status') && $request->status && $request->status !== 'todas') {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 20);
        $pedidos = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $pedidos->getCollection()->each(function ($pedido) {
            if ($pedido->cliente && $pedido->cliente->foto) {
                $pedido->cliente->foto = asset('storage/' . $pedido->cliente->foto);
            }
            $pedido->servico = $pedido->categoria;
            $pedido->data = $pedido->agendado_para;
            $pedido->valor = (float) $pedido->valor;
        });

        $contadores = [
            'pendentes' => Pedido::where('prestador_id', $user->id)->where('status', 'pendente')->count(),
            'aceitos' => Pedido::where('prestador_id', $user->id)->where('status', 'aceito')->count(),
            'confirmados' => Pedido::where('prestador_id', $user->id)->where('status', 'aceito')->count(),
            'concluidos' => Pedido::where('prestador_id', $user->id)->where('status', 'concluido')->count(),
            'cancelados' => Pedido::where('prestador_id', $user->id)->where('status', 'cancelado')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $pedidos->items(),
            'contadores' => $contadores,
            'pagination' => [
                'current_page' => $pedidos->currentPage(),
                'last_page' => $pedidos->lastPage(),
                'per_page' => $pedidos->perPage(),
                'total' => $pedidos->total(),
            ]
        ]);
    }

    public function show($id, Request $request)
    {
        $user = $request->user();

        $pedido = Pedido::where('prestador_id', $user->id)
            ->with(['cliente', 'categoria'])
            ->find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não encontrado'
            ], 404);
        }

        if ($pedido->cliente && $pedido->cliente->foto) {
            $pedido->cliente->foto = asset('storage/' . $pedido->cliente->foto);
        }

        $pedido->servico = $pedido->categoria;
        $pedido->data = $pedido->agendado_para;
        $pedido->valor = (float) $pedido->valor;

        return response()->json([
            'success' => true,
            'data' => $pedido
        ]);
    }

    public function aceitar($id, Request $request)
    {
        $user = $request->user();

        $pedido = Pedido::where('prestador_id', $user->id)
            ->where('status', 'pendente')
            ->find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não encontrado ou já processado'
            ], 404);
        }

        $pedido->status = 'aceito';
        $pedido->save();

        // 🔔 NOTIFICAÇÃO: Pedido aceito (para o cliente)
        NotificationService::send('pedido.aceito', $pedido->cliente_id, [
            'numero' => $pedido->numero,
            'pedido_id' => $pedido->id,
            'prestador_nome' => $user->nome,
            'data' => $pedido->agendado_para,
        ]);

        // 🔔 NOTIFICAÇÃO: Pedido aceito (para o prestador)
        NotificationService::send('pedido.aceito_prestador', $user->id, [
            'numero' => $pedido->numero,
            'cliente_nome' => $pedido->cliente->nome ?? 'Cliente',
            'endereco' => $pedido->endereco,
            'pedido_id' => $pedido->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pedido aceito com sucesso',
            'data' => $pedido
        ]);
    }

    public function recusar($id, Request $request)
    {
        $user = $request->user();

        $pedido = Pedido::where('prestador_id', $user->id)
            ->where('status', 'pendente')
            ->find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não encontrado'
            ], 404);
        }

        $pedido->status = 'cancelado';
        $pedido->save();

        // 🔔 NOTIFICAÇÃO: Pedido recusado (para o cliente)
        NotificationService::send('pedido.cancelado', $pedido->cliente_id, [
            'numero' => $pedido->numero,
            'pedido_id' => $pedido->id,
            'prestador_nome' => $user->nome,
            'motivo' => 'Recusado pelo prestador',
        ]);

        // 🔔 NOTIFICAÇÃO: Pedido recusado (para o prestador)
        NotificationService::send('pedido.cancelado_prestador', $user->id, [
            'numero' => $pedido->numero,
            'pedido_id' => $pedido->id,
            'motivo' => 'Você recusou o pedido',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pedido recusado',
            'data' => $pedido
        ]);
    }

    public function iniciarServico($id, Request $request)
    {
        $user = $request->user();

        $pedido = Pedido::where('prestador_id', $user->id)
            ->where('status', 'aceito')
            ->find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não encontrado ou não está aceito'
            ], 404);
        }

        $pedido->status = 'em_andamento';
        $pedido->save();

        // 🔔 NOTIFICAÇÃO: Serviço em andamento (para o cliente)
        NotificationService::send('pedido.em_andamento', $pedido->cliente_id, [
            'numero' => $pedido->numero,
            'pedido_id' => $pedido->id,
            'prestador_nome' => $user->nome,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Serviço iniciado',
            'data' => $pedido
        ]);
    }

    public function concluirServico($id, Request $request)
    {
        $user = $request->user();

        $pedido = Pedido::where('prestador_id', $user->id)
            ->where('status', 'em_andamento')
            ->find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não encontrado ou não está em andamento'
            ], 404);
        }

        $pedido->status = 'concluido';
        $pedido->concluido_em = Carbon::now();
        $pedido->save();

        // 🔔 NOTIFICAÇÃO: Pedido concluído (para o cliente)
        NotificationService::send('pedido.concluido', $pedido->cliente_id, [
            'numero' => $pedido->numero,
            'pedido_id' => $pedido->id,
            'prestador_nome' => $user->nome,
        ]);

        // 🔔 NOTIFICAÇÃO: Pedido concluído (para o prestador)
        NotificationService::send('pedido.concluido_prestador', $user->id, [
            'numero' => $pedido->numero,
            'pedido_id' => $pedido->id,
            'cliente_nome' => $pedido->cliente->nome ?? 'Cliente',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Serviço concluído',
            'data' => $pedido
        ]);
    }

    public function cancelar($id, Request $request)
    {
        $user = $request->user();

        $pedido = Pedido::where('prestador_id', $user->id)
            ->whereIn('status', ['pendente', 'aceito'])
            ->find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não encontrado ou não pode ser cancelado'
            ], 404);
        }

        $pedido->status = 'cancelado';
        $pedido->save();

        // 🔔 NOTIFICAÇÃO: Pedido cancelado (para o cliente)
        NotificationService::send('pedido.cancelado', $pedido->cliente_id, [
            'numero' => $pedido->numero,
            'pedido_id' => $pedido->id,
            'prestador_nome' => $user->nome,
            'motivo' => $request->input('motivo', 'Cancelado pelo prestador'),
        ]);

        // 🔔 NOTIFICAÇÃO: Pedido cancelado (para o prestador)
        NotificationService::send('pedido.cancelado_prestador', $user->id, [
            'numero' => $pedido->numero,
            'pedido_id' => $pedido->id,
            'motivo' => $request->input('motivo', 'Você cancelou o pedido'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pedido cancelado',
            'data' => $pedido
        ]);
    }

    // ==========================================
    // ✅ NOVOS MÉTODOS ADICIONADOS
    // ==========================================

    /**
     * GET /prestador/pedidos-disponiveis
     * Lista pedidos disponíveis para o prestador (pendentes, sem prestador)
     */
    public function pedidosDisponiveis(Request $request)
    {
        $user = $request->user();

        // Obter coordenadas do prestador
        $latitude = $user->prestadorProfile?->latitude;
        $longitude = $user->prestadorProfile?->longitude;

        $query = Pedido::where('status', 'pendente')
            ->whereNull('prestador_id')
            ->with(['cliente' => function ($q) {
                $q->select('id', 'nome', 'foto');
            }, 'categoria']);

        // Calcular distância se tiver coordenadas
        if ($latitude && $longitude) {
            $query->select('pedidos.*')
                ->selectRaw("
                    (6371 * acos(
                        cos(radians(?)) * cos(radians(latitude)) *
                        cos(radians(longitude) - radians(?)) +
                        sin(radians(?)) * sin(radians(latitude))
                    )) AS distancia_km
                ", [$latitude, $longitude, $latitude]);
        }

        $pedidos = $query->orderBy('created_at', 'desc')->get();

        $pedidos->each(function ($pedido) {
            if ($pedido->cliente && $pedido->cliente->foto) {
                $pedido->cliente->foto = asset('storage/' . $pedido->cliente->foto);
            }
            $pedido->distancia_km = round($pedido->distancia_km ?? 0, 1);
        });

        return response()->json([
            'success' => true,
            'data' => $pedidos
        ]);
    }

    /**
     * POST /prestador/propostas
     * Enviar proposta para um pedido
     */
    public function enviarProposta(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'pedido_id' => 'required|exists:pedidos,id',
            'valor' => 'required|numeric|min:0',
            'mensagem' => 'nullable|string|max:500',
        ]);

        $pedido = Pedido::where('id', $request->pedido_id)
            ->where('status', 'pendente')
            ->whereNull('prestador_id')
            ->first();

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não encontrado ou já foi aceito'
            ], 404);
        }

        // Verificar se já existe proposta
        $propostaExistente = Proposta::where('pedido_id', $pedido->id)
            ->where('prestador_id', $user->id)
            ->first();

        if ($propostaExistente) {
            return response()->json([
                'success' => false,
                'message' => 'Você já enviou uma proposta para este pedido'
            ], 400);
        }

        $proposta = Proposta::create([
            'pedido_id' => $pedido->id,
            'prestador_id' => $user->id,
            'valor' => $request->valor,
            'mensagem' => $request->mensagem,
            'status' => 'pendente',
        ]);

        // 🔔 NOTIFICAÇÃO: Nova proposta para o cliente
        NotificationService::send('proposta.nova', $pedido->cliente_id, [
            'pedido_id' => $pedido->id,
            'prestador_nome' => $user->nome,
            'valor' => $request->valor,
            'mensagem' => $request->mensagem,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Proposta enviada com sucesso',
            'data' => [
                'id' => $proposta->id,
                'pedido_id' => $proposta->pedido_id,
                'valor' => $proposta->valor,
                'mensagem' => $proposta->mensagem,
                'status' => $proposta->status,
                'created_at' => $proposta->created_at,
            ]
        ]);
    }

    /**
     * GET /prestador/propostas
     * Listar propostas enviadas pelo prestador
     */
    public function minhasPropostas(Request $request)
    {
        $user = $request->user();

        $propostas = Proposta::where('prestador_id', $user->id)
            ->with(['pedido' => function ($q) {
                $q->with('cliente:id,nome,foto');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $propostas
        ]);
    }

    // app/Http/Controllers/Api/PrestadorPedidoController.php

    /**
     * GET /prestador/pedidos
     * Histórico de pedidos concluídos do prestador
     */
    public function historico(Request $request)
    {
        $user = $request->user();

        $pedidos = Pedido::where('prestador_id', $user->id)
            ->where('status', 'concluido')
            ->with(['cliente' => function ($q) {
                $q->select('id', 'nome', 'foto');
            }, 'categoria'])
            ->orderBy('concluido_em', 'desc')
            ->get();

        $pedidos->each(function ($pedido) {
            if ($pedido->cliente && $pedido->cliente->foto) {
                $pedido->cliente->foto = asset('storage/' . $pedido->cliente->foto);
            }
            $pedido->servico = $pedido->categoria;
            $pedido->data = $pedido->concluido_em ?? $pedido->created_at;
            $pedido->valor = (float) $pedido->valor;
        });

        return response()->json([
            'success' => true,
            'data' => $pedidos
        ]);
    }

    /**
     * GET /prestador/avaliacoes
     * Avaliações recebidas pelo prestador
     */
    public function avaliacoes(Request $request)
    {
        $user = $request->user();

        $avaliacoes = Avaliacao::where('prestador_id', $user->id)
            ->where('status', 'aprovada')
            ->with(['cliente' => function ($q) {
                $q->select('id', 'nome', 'foto');
            }, 'pedido'])
            ->orderBy('created_at', 'desc')
            ->get();

        $avaliacoes->each(function ($avaliacao) {
            if ($avaliacao->cliente && $avaliacao->cliente->foto) {
                $avaliacao->cliente->foto = asset('storage/' . $avaliacao->cliente->foto);
            }
        });

        return response()->json([
            'success' => true,
            'data' => $avaliacoes
        ]);
    }
}
