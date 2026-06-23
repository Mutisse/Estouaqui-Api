<?php
// app/Http/Controllers/Api/PedidoController.php

namespace App\Http\Controllers\Api;

use App\Models\Pedido;
use App\Models\User;
use App\Models\Agenda;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PedidoController extends BaseController
{
    /**
     * Listar todos os pedidos do cliente
     * GET /api/cliente/pedidos
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $pedidos = Pedido::where('cliente_id', $user->id)
            ->with(['categoria', 'prestador'])
            ->orderBy('created_at', 'desc')
            ->get();

        $pedidos->each(function ($pedido) {
            if ($pedido->foto) {
                $pedido->foto = asset('storage/' . $pedido->foto);
            }
            $pedido->valor = (float) $pedido->valor;
        });

        return response()->json([
            'success' => true,
            'data' => $pedidos
        ]);
    }

    /**
     * Mostrar detalhes de um pedido específico
     * GET /api/cliente/pedidos/{id}
     */
    public function show($id)
    {
        $pedido = Pedido::with(['cliente', 'prestador', 'categoria'])
            ->find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não encontrado'
            ], 404);
        }

        if ($pedido->foto) {
            $pedido->foto = asset('storage/' . $pedido->foto);
        }

        if ($pedido->cliente && $pedido->cliente->foto) {
            $pedido->cliente->foto = asset('storage/' . $pedido->cliente->foto);
        }

        $pedido->valor = (float) $pedido->valor;

        return response()->json([
            'success' => true,
            'data' => $pedido
        ]);
    }

    /**
     * 🔥 CORRIGIDO: Criar um novo pedido com verificação de disponibilidade
     * POST /api/cliente/pedidos
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'categoria_id' => 'required|exists:categorias,id',
                'descricao' => 'required|string|min:10',
                'endereco' => 'required|string',
                'foto' => 'nullable|image|max:5120',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'agendado_para' => 'required|date|after:now',
                'valor' => 'nullable|numeric|min:0',
            ]);

            $user = $request->user();

            // 🔥 VERIFICAR DISPONIBILIDADE
            $agendadoPara = Carbon::parse($request->agendado_para);
            $data = $agendadoPara->format('Y-m-d');
            $hora = $agendadoPara->format('H:i');

            // Buscar prestadores disponíveis nesta data/hora
            $prestadoresDisponiveis = User::prestadores()
                ->where('verificado', true)
                ->where('disponivel', true)
                ->whereHas('categorias', function ($query) use ($request) {
                    $query->where('categoria_id', $request->categoria_id);
                })
                ->whereDoesntHave('agenda', function ($query) use ($data, $hora) {
                    $query->where('data', $data)
                        ->where('horario_inicio', '<=', $hora)
                        ->where('horario_fim', '>=', $hora)
                        ->where('bloqueado', true);
                })
                ->whereDoesntHave('pedidos', function ($query) use ($data, $hora) {
                    $query->where('agendado_para', $data . ' ' . $hora)
                        ->whereIn('status', ['aceito', 'em_andamento']);
                })
                ->count();

            if ($prestadoresDisponiveis == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não há prestadores disponíveis para esta data e hora. Tente outro horário.',
                    'erro' => 'sem_prestadores_disponiveis',
                    'data' => $data,
                    'hora' => $hora
                ], 422);
            }

            $dados = [
                'cliente_id' => $user->id,
                'categoria_id' => $request->categoria_id,
                'descricao' => $request->descricao,
                'endereco' => $request->endereco,
                'status' => 'pendente',
                'agendado_para' => $request->agendado_para,
                'valor' => $request->valor ?? 0,
                'numero' => $this->gerarNumeroPedido(),
            ];

            if ($request->has('latitude') && $request->latitude !== null) {
                $dados['latitude'] = $request->latitude;
            }
            if ($request->has('longitude') && $request->longitude !== null) {
                $dados['longitude'] = $request->longitude;
            }

            if ($request->hasFile('foto')) {
                $path = $request->file('foto')->store('pedidos', 'public');
                $dados['foto'] = $path;
            }

            $pedido = Pedido::create($dados);

            Log::info('Pedido criado', [
                'pedido_id' => $pedido->id,
                'latitude' => $pedido->latitude,
                'longitude' => $pedido->longitude,
                'agendado_para' => $pedido->agendado_para,
            ]);

            // 🔔 NOTIFICAÇÃO: Pedido criado para o cliente
            NotificationService::send('pedido.criado', $user->id, [
                'numero' => $pedido->numero,
                'pedido_id' => $pedido->id,
                'categoria' => $pedido->categoria->nome ?? 'serviço'
            ]);

            // 🔔 NOTIFICAR PRESTADORES DISPONÍVEIS
            $this->notificarPrestadoresDisponiveis($pedido);

            return response()->json([
                'success' => true,
                'message' => 'Pedido criado com sucesso! Aguarde propostas dos prestadores.',
                'data' => $pedido
            ], 201);

        } catch (\Exception $e) {
            Log::error('Erro ao criar pedido: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar pedido: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🔥 NOTIFICAR PRESTADORES DISPONÍVEIS
     */
    private function notificarPrestadoresDisponiveis($pedido)
    {
        try {
            $data = Carbon::parse($pedido->agendado_para)->format('Y-m-d');
            $hora = Carbon::parse($pedido->agendado_para)->format('H:i');

            $prestadores = User::prestadores()
                ->where('verificado', true)
                ->where('disponivel', true)
                ->whereHas('categorias', function ($query) use ($pedido) {
                    $query->where('categoria_id', $pedido->categoria_id);
                })
                ->whereDoesntHave('agenda', function ($query) use ($data, $hora) {
                    $query->where('data', $data)
                        ->where('horario_inicio', '<=', $hora)
                        ->where('horario_fim', '>=', $hora)
                        ->where('bloqueado', true);
                })
                ->whereDoesntHave('pedidos', function ($query) use ($data, $hora) {
                    $query->where('agendado_para', $data . ' ' . $hora)
                        ->whereIn('status', ['aceito', 'em_andamento']);
                })
                ->get();

            if ($prestadores->count() > 0) {
                NotificationService::sendToMany(
                    'pedido.novo_para_prestador',
                    $prestadores->pluck('id')->toArray(),
                    [
                        'numero' => $pedido->numero,
                        'pedido_id' => $pedido->id,
                        'categoria' => $pedido->categoria->nome ?? 'serviço',
                        'descricao' => substr($pedido->descricao, 0, 50),
                        'latitude' => $pedido->latitude,
                        'longitude' => $pedido->longitude,
                        'agendado_para' => $pedido->agendado_para,
                        'valor' => $pedido->valor,
                    ]
                );

                Log::info('Prestadores notificados para pedido #' . $pedido->numero, [
                    'prestadores_count' => $prestadores->count()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Erro ao notificar prestadores: ' . $e->getMessage());
        }
    }

    /**
     * Gerar número único do pedido
     */
    private function gerarNumeroPedido()
    {
        $ano = date('Y');
        $mes = date('m');
        $ultimo = Pedido::whereYear('created_at', $ano)
            ->whereMonth('created_at', $mes)
            ->max('numero');

        if ($ultimo) {
            $sequencia = intval(substr($ultimo, -4)) + 1;
        } else {
            $sequencia = 1;
        }

        return 'PED-' . $ano . $mes . str_pad($sequencia, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 🔥 CORRIGIDO: Atualizar status do pedido com verificação de agenda
     * PATCH /api/cliente/pedidos/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:pendente,aceito,em_andamento,concluido,cancelado',
                'prestador_id' => 'required_if:status,aceito|exists:users,id',
                'motivo' => 'required_if:status,cancelado|string|min:3'
            ]);

            $pedido = Pedido::with(['cliente', 'prestador', 'categoria'])->find($id);

            if (!$pedido) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pedido não encontrado'
                ], 404);
            }

            // 🔥 VERIFICAR DISPONIBILIDADE AO ACEITAR
            if ($request->status === 'aceito' && $request->has('prestador_id')) {
                $prestadorId = $request->prestador_id;
                $data = Carbon::parse($pedido->agendado_para)->format('Y-m-d');
                $hora = Carbon::parse($pedido->agendado_para)->format('H:i');

                // Verificar se o prestador está bloqueado na agenda
                $bloqueado = Agenda::where('prestador_id', $prestadorId)
                    ->where('data', $data)
                    ->where('horario_inicio', '<=', $hora)
                    ->where('horario_fim', '>=', $hora)
                    ->where('bloqueado', true)
                    ->exists();

                if ($bloqueado) {
                    return response()->json([
                        'success' => false,
                        'message' => 'O prestador não está disponível para esta data/hora',
                        'erro' => 'prestador_indisponivel',
                        'data' => $data,
                        'hora' => $hora
                    ], 422);
                }

                // Verificar se já tem pedido na mesma data/hora
                $pedidoExistente = Pedido::where('prestador_id', $prestadorId)
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
                $prestador = User::find($prestadorId);
                if (!$prestador || !$prestador->disponivel) {
                    return response()->json([
                        'success' => false,
                        'message' => 'O prestador está indisponível no momento',
                        'erro' => 'prestador_indisponivel'
                    ], 422);
                }

                $pedido->prestador_id = $request->prestador_id;

                // 🔥 CRIAR BLOQUEIO NA AGENDA
                Agenda::create([
                    'prestador_id' => $prestadorId,
                    'data' => $data,
                    'horario_inicio' => $hora,
                    'horario_fim' => $hora,
                    'bloqueado' => true,
                    'observacao' => 'Pedido #' . $pedido->numero . ' - Cliente: ' . ($pedido->cliente->nome ?? 'Cliente'),
                ]);

                // 🔔 NOTIFICAÇÃO: Agendamento confirmado (cliente)
                NotificationService::send('agendamento.confirmado', $pedido->cliente_id, [
                    'data' => Carbon::parse($pedido->agendado_para)->format('d/m/Y'),
                    'hora' => Carbon::parse($pedido->agendado_para)->format('H:i'),
                    'servico' => $pedido->categoria->nome ?? 'Serviço',
                    'prestador_nome' => $prestador->nome ?? 'Prestador',
                    'endereco' => $pedido->endereco ?? 'A definir',
                    'valor' => $pedido->valor ?? 0,
                ]);

                // 🔔 NOTIFICAÇÃO: Agendamento confirmado (prestador)
                NotificationService::send('agendamento.confirmado_prestador', $prestadorId, [
                    'data' => Carbon::parse($pedido->agendado_para)->format('d/m/Y'),
                    'hora' => Carbon::parse($pedido->agendado_para)->format('H:i'),
                    'servico' => $pedido->categoria->nome ?? 'Serviço',
                    'cliente_nome' => $pedido->cliente->nome ?? 'Cliente',
                    'endereco' => $pedido->endereco ?? 'A definir',
                    'valor' => $pedido->valor ?? 0,
                ]);
            }

            $pedido->status = $request->status;

            if ($request->status === 'concluido') {
                $pedido->concluido_em = now();

                // 🔥 REMOVER BLOQUEIO DA AGENDA AO CONCLUIR
                if ($pedido->prestador_id) {
                    Agenda::where('prestador_id', $pedido->prestador_id)
                        ->where('data', Carbon::parse($pedido->agendado_para)->format('Y-m-d'))
                        ->where('horario_inicio', Carbon::parse($pedido->agendado_para)->format('H:i'))
                        ->where('observacao', 'like', 'Pedido #' . $pedido->numero . '%')
                        ->delete();
                }

                // 🔔 NOTIFICAÇÃO: Agendamento concluído
                NotificationService::send('agendamento.concluido', $pedido->cliente_id, [
                    'data' => Carbon::parse($pedido->agendado_para)->format('d/m/Y'),
                    'hora' => Carbon::parse($pedido->agendado_para)->format('H:i'),
                    'servico' => $pedido->categoria->nome ?? 'Serviço',
                    'prestador_nome' => $pedido->prestador->nome ?? 'Prestador',
                ]);
            }

            if ($request->status === 'cancelado' && $pedido->prestador_id) {
                // 🔥 REMOVER BLOQUEIO DA AGENDA AO CANCELAR
                Agenda::where('prestador_id', $pedido->prestador_id)
                    ->where('data', Carbon::parse($pedido->agendado_para)->format('Y-m-d'))
                    ->where('horario_inicio', Carbon::parse($pedido->agendado_para)->format('H:i'))
                    ->where('observacao', 'like', 'Pedido #' . $pedido->numero . '%')
                    ->delete();

                // 🔔 NOTIFICAÇÃO: Agendamento cancelado
                NotificationService::send('agendamento.cancelado', $pedido->cliente_id, [
                    'data' => Carbon::parse($pedido->agendado_para)->format('d/m/Y'),
                    'hora' => Carbon::parse($pedido->agendado_para)->format('H:i'),
                    'servico' => $pedido->categoria->nome ?? 'Serviço',
                    'prestador_nome' => $pedido->prestador->nome ?? 'Prestador',
                    'motivo' => $request->input('motivo', 'Cancelado pelo cliente'),
                ]);
            }

            $pedido->save();

            // ===== NOTIFICAÇÕES DE STATUS =====
            $this->enviarNotificacoesStatus($pedido, $request);

            return response()->json([
                'success' => true,
                'message' => 'Status atualizado com sucesso!',
                'data' => $pedido
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao atualizar status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar notificações de status
     */
    private function enviarNotificacoesStatus($pedido, $request)
    {
        $eventoMap = [
            'aceito' => 'pedido.aceito',
            'em_andamento' => 'pedido.em_andamento',
            'concluido' => 'pedido.concluido',
            'cancelado' => 'pedido.cancelado',
        ];

        if (isset($eventoMap[$request->status])) {
            $dadosNotificacao = [
                'numero' => $pedido->numero,
                'pedido_id' => $pedido->id,
            ];

            if ($pedido->prestador) {
                $dadosNotificacao['prestador_nome'] = $pedido->prestador->nome;
            }

            if ($request->status === 'cancelado') {
                $dadosNotificacao['motivo'] = $request->input('motivo', 'não especificado');
            }

            NotificationService::send($eventoMap[$request->status], $pedido->cliente_id, $dadosNotificacao);
        }

        if ($request->status === 'aceito' && $pedido->prestador_id) {
            NotificationService::send('pedido.aceito_prestador', $pedido->prestador_id, [
                'numero' => $pedido->numero,
                'cliente_nome' => $pedido->cliente->nome,
                'endereco' => $pedido->endereco,
                'agendado_para' => $pedido->agendado_para,
            ]);
        }

        if ($request->status === 'em_andamento' && $pedido->prestador_id) {
            NotificationService::send('pedido.em_andamento_prestador', $pedido->prestador_id, [
                'numero' => $pedido->numero,
                'cliente_nome' => $pedido->cliente->nome
            ]);
        }

        if ($request->status === 'concluido' && $pedido->prestador_id) {
            NotificationService::send('pedido.concluido_prestador', $pedido->prestador_id, [
                'numero' => $pedido->numero,
                'cliente_nome' => $pedido->cliente->nome
            ]);
        }

        if ($request->status === 'cancelado' && $pedido->prestador_id) {
            NotificationService::send('pedido.cancelado_prestador', $pedido->prestador_id, [
                'numero' => $pedido->numero,
                'motivo' => $request->input('motivo', 'Cliente cancelou o pedido')
            ]);
        }
    }

    /**
     * Deletar um pedido (apenas pendentes)
     * DELETE /api/cliente/pedidos/{id}
     */
    public function destroy(Request $request, $id)
    {
        $pedido = Pedido::where('cliente_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não encontrado'
            ], 404);
        }

        if ($pedido->status !== 'pendente') {
            return response()->json([
                'success' => false,
                'message' => 'Apenas pedidos pendentes podem ser removidos'
            ], 422);
        }

        if ($pedido->foto) {
            Storage::disk('public')->delete($pedido->foto);
        }

        $pedido->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pedido removido com sucesso'
        ]);
    }

    /**
     * Listar pedidos de um prestador (para prestadores)
     * GET /api/prestador/pedidos
     */
    public function pedidosPrestador(Request $request)
    {
        $user = $request->user();

        if (!$user->isPrestador()) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso apenas para prestadores'
            ], 403);
        }

        $pedidos = Pedido::where('prestador_id', $user->id)
            ->with(['cliente', 'categoria'])
            ->orderBy('created_at', 'desc')
            ->get();

        $pedidos->each(function ($pedido) {
            if ($pedido->foto) {
                $pedido->foto = asset('storage/' . $pedido->foto);
            }
            $pedido->valor = (float) $pedido->valor;
        });

        return response()->json([
            'success' => true,
            'data' => $pedidos
        ]);
    }

    /**
     * 🔥 CORRIGIDO: Listar pedidos disponíveis com verificação de agenda
     * GET /api/prestador/pedidos/disponiveis
     */
    public function pedidosDisponiveis(Request $request)
    {
        $user = $request->user();

        if (!$user->isPrestador()) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso apenas para prestadores'
            ], 403);
        }

        $categoriasPrestador = $user->categorias()->pluck('categorias.id');

        // 🔥 FILTRAR PEDIDOS COM VERIFICAÇÃO DE DISPONIBILIDADE
        $pedidos = Pedido::where('status', 'pendente')
            ->whereNull('prestador_id')
            ->whereIn('categoria_id', $categoriasPrestador)
            ->with(['cliente', 'categoria'])
            ->orderBy('created_at', 'desc')
            ->get();

        // 🔥 FILTRAR MANUALMENTE POR DISPONIBILIDADE
        $pedidosFiltrados = $pedidos->filter(function ($pedido) use ($user) {
            $data = Carbon::parse($pedido->agendado_para)->format('Y-m-d');
            $hora = Carbon::parse($pedido->agendado_para)->format('H:i');

            // Verificar se o prestador está bloqueado na agenda
            $bloqueado = Agenda::where('prestador_id', $user->id)
                ->where('data', $data)
                ->where('horario_inicio', '<=', $hora)
                ->where('horario_fim', '>=', $hora)
                ->where('bloqueado', true)
                ->exists();

            if ($bloqueado) {
                return false;
            }

            // Verificar se já tem pedido na mesma data/hora
            $pedidoExistente = Pedido::where('prestador_id', $user->id)
                ->where('agendado_para', $pedido->agendado_para)
                ->whereIn('status', ['aceito', 'em_andamento'])
                ->exists();

            if ($pedidoExistente) {
                return false;
            }

            return true;
        });

        $pedidosFiltrados->each(function ($pedido) {
            if ($pedido->foto) {
                $pedido->foto = asset('storage/' . $pedido->foto);
            }
            $pedido->valor = (float) $pedido->valor;
        });

        return response()->json([
            'success' => true,
            'data' => $pedidosFiltrados->values(),
            'total' => $pedidosFiltrados->count()
        ]);
    }

    /**
     * 🔥 CORRIGIDO: Aceitar um pedido com verificação de disponibilidade
     * POST /api/prestador/pedidos/{id}/aceitar
     */
    public function aceitarPedido(Request $request, $id)
    {
        try {
            $user = $request->user();

            if (!$user->isPrestador()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Apenas prestadores podem aceitar pedidos'
                ], 403);
            }

            $pedido = Pedido::with(['cliente', 'categoria'])->find($id);

            if (!$pedido) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pedido não encontrado'
                ], 404);
            }

            if ($pedido->status !== 'pendente') {
                return response()->json([
                    'success' => false,
                    'message' => 'Este pedido não está mais disponível'
                ], 422);
            }

            $temCategoria = $user->categorias()->where('categoria_id', $pedido->categoria_id)->exists();

            if (!$temCategoria) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não está habilitado para esta categoria'
                ], 403);
            }

            // 🔥 VERIFICAR DISPONIBILIDADE
            $data = Carbon::parse($pedido->agendado_para)->format('Y-m-d');
            $hora = Carbon::parse($pedido->agendado_para)->format('H:i');

            $bloqueado = Agenda::where('prestador_id', $user->id)
                ->where('data', $data)
                ->where('horario_inicio', '<=', $hora)
                ->where('horario_fim', '>=', $hora)
                ->where('bloqueado', true)
                ->exists();

            if ($bloqueado) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não está disponível para esta data/hora. Verifique sua agenda.',
                    'erro' => 'agenda_bloqueada',
                    'data' => $data,
                    'hora' => $hora
                ], 422);
            }

            $pedidoExistente = Pedido::where('prestador_id', $user->id)
                ->where('agendado_para', $pedido->agendado_para)
                ->whereIn('status', ['aceito', 'em_andamento'])
                ->exists();

            if ($pedidoExistente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você já tem um serviço agendado para esta data/hora',
                    'erro' => 'horario_ocupado',
                    'data' => $data,
                    'hora' => $hora
                ], 422);
            }

            DB::transaction(function () use ($pedido, $user, $data, $hora) {
                $pedido->prestador_id = $user->id;
                $pedido->status = 'aceito';
                $pedido->save();

                // 🔥 CRIAR BLOQUEIO NA AGENDA
                Agenda::create([
                    'prestador_id' => $user->id,
                    'data' => $data,
                    'horario_inicio' => $hora,
                    'horario_fim' => $hora,
                    'bloqueado' => true,
                    'observacao' => 'Pedido #' . $pedido->numero . ' - Cliente: ' . ($pedido->cliente->nome ?? 'Cliente'),
                ]);

                // 🔔 NOTIFICAÇÃO: Pedido aceito (cliente)
                NotificationService::send('pedido.aceito', $pedido->cliente_id, [
                    'numero' => $pedido->numero,
                    'pedido_id' => $pedido->id,
                    'prestador_nome' => $user->nome
                ]);

                // 🔔 NOTIFICAÇÃO: Pedido aceito (prestador)
                NotificationService::send('pedido.aceito_prestador', $user->id, [
                    'numero' => $pedido->numero,
                    'cliente_nome' => $pedido->cliente->nome,
                    'endereco' => $pedido->endereco,
                    'agendado_para' => $pedido->agendado_para,
                ]);

                // 🔔 NOTIFICAÇÃO: Agendamento confirmado (cliente)
                NotificationService::send('agendamento.confirmado', $pedido->cliente_id, [
                    'data' => Carbon::parse($pedido->agendado_para)->format('d/m/Y'),
                    'hora' => Carbon::parse($pedido->agendado_para)->format('H:i'),
                    'servico' => $pedido->categoria->nome ?? 'Serviço',
                    'prestador_nome' => $user->nome,
                    'endereco' => $pedido->endereco ?? 'A definir',
                    'valor' => $pedido->valor ?? 0,
                ]);

                // 🔔 NOTIFICAÇÃO: Agendamento confirmado (prestador)
                NotificationService::send('agendamento.confirmado_prestador', $user->id, [
                    'data' => Carbon::parse($pedido->agendado_para)->format('d/m/Y'),
                    'hora' => Carbon::parse($pedido->agendado_para)->format('H:i'),
                    'servico' => $pedido->categoria->nome ?? 'Serviço',
                    'cliente_nome' => $pedido->cliente->nome ?? 'Cliente',
                    'endereco' => $pedido->endereco ?? 'A definir',
                    'valor' => $pedido->valor ?? 0,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Pedido aceito com sucesso!',
                'data' => $pedido
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao aceitar pedido: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao aceitar pedido: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rejeitar um pedido (prestador recusa)
     * POST /api/prestador/pedidos/{id}/rejeitar
     */
    public function rejeitarPedido(Request $request, $id)
    {
        $user = $request->user();

        if (!$user->isPrestador()) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas prestadores podem rejeitar pedidos'
            ], 403);
        }

        $pedido = Pedido::find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pedido rejeitado'
        ]);
    }

    /**
     * Buscar o cliente que fez o pedido
     * GET /api/pedidos/{id}/cliente
     */
    public function getClienteDoPedido($id)
    {
        $pedido = Pedido::find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não encontrado'
            ], 404);
        }

        $cliente = $pedido->cliente;

        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente não encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $cliente->id,
                'nome' => $cliente->nome,
                'email' => $cliente->email,
                'telefone' => $cliente->telefone,
                'foto' => $cliente->foto ? asset('storage/' . $cliente->foto) : null,
            ]
        ]);
    }

    /**
     * Buscar histórico de status do pedido
     * GET /api/pedidos/{id}/historico
     */
    public function historico($id)
    {
        $pedido = Pedido::find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não encontrado'
            ], 404);
        }

        $historico = [
            [
                'id' => 1,
                'pedido_id' => $pedido->id,
                'status' => $pedido->status,
                'status_label' => $this->getStatusLabel($pedido->status),
                'observacao' => 'Status atual',
                'created_at' => $pedido->updated_at ?? $pedido->created_at,
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $historico
        ]);
    }

    /**
     * Retorna o label do status
     */
    private function getStatusLabel($status)
    {
        $labels = [
            'pendente' => 'Pendente',
            'aceito' => 'Aceito',
            'em_andamento' => 'Em Andamento',
            'concluido' => 'Concluído',
            'cancelado' => 'Cancelado',
        ];
        return $labels[$status] ?? $status;
    }

    /**
     * 🔥 NOVO: Verificar disponibilidade antes de criar pedido
     * GET /api/cliente/pedidos/verificar-disponibilidade
     */
    public function verificarDisponibilidade(Request $request)
    {
        try {
            $request->validate([
                'categoria_id' => 'required|exists:categorias,id',
                'data' => 'required|date|after:now',
                'hora' => 'required|string',
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
                'raio' => 'nullable|numeric|min:1|max:50',
            ]);

            $data = $request->data;
            $hora = $request->hora;
            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $raio = $request->get('raio', 20);

            $prestadores = User::prestadores()
                ->where('verificado', true)
                ->where('disponivel', true)
                ->whereHas('categorias', function ($query) use ($request) {
                    $query->where('categoria_id', $request->categoria_id);
                })
                ->whereDoesntHave('agenda', function ($query) use ($data, $hora) {
                    $query->where('data', $data)
                        ->where('horario_inicio', '<=', $hora)
                        ->where('horario_fim', '>=', $hora)
                        ->where('bloqueado', true);
                })
                ->whereDoesntHave('pedidos', function ($query) use ($data, $hora) {
                    $query->where('agendado_para', $data . ' ' . $hora)
                        ->whereIn('status', ['aceito', 'em_andamento']);
                })
                ->selectRaw("*, ( 6371 * acos( cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) ) ) AS distance", [$latitude, $longitude, $latitude])
                ->having('distance', '<', $raio)
                ->orderBy('distance', 'asc')
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $prestadores->count(),
                    'prestadores' => $prestadores->map(function ($p) {
                        return [
                            'id' => $p->id,
                            'nome' => $p->nome,
                            'foto' => $p->foto ? asset('storage/' . $p->foto) : null,
                            'media_avaliacao' => (float) $p->media_avaliacao,
                            'distance' => round($p->distance ?? 0, 1),
                            'profissao' => $p->profissao,
                        ];
                    }),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao verificar disponibilidade: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao verificar disponibilidade: ' . $e->getMessage()
            ], 500);
        }
    }
}
