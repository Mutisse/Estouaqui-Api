<?php
// app/Http/Controllers/Api/PrestadorAgendaController.php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Agenda;
use App\Models\Pedido;
use App\Models\IntervaloAgenda;
use App\Models\Configuracoes;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrestadorAgendaController extends BaseController
{
    /**
     * GET /api/prestador/agenda
     * Listar agenda do prestador para uma semana
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            $semanaOffset = $request->get('semana', 0);
            $dataInicio = $request->get('data_inicio');
            $dataFim = $request->get('data_fim');

            if ($dataInicio && $dataFim) {
                $inicio = Carbon::parse($dataInicio);
                $fim = Carbon::parse($dataFim);
            } else {
                $hoje = Carbon::now();
                $inicio = $hoje->copy()->startOfWeek()->addWeeks($semanaOffset);
                $fim = $hoje->copy()->endOfWeek()->addWeeks($semanaOffset);
            }

            $agenda = Agenda::where('prestador_id', $user->id)
                ->whereBetween('data', [$inicio->format('Y-m-d'), $fim->format('Y-m-d')])
                ->get();

            // Adicionar horários ocupados por pedidos
            $pedidos = Pedido::where('prestador_id', $user->id)
                ->whereBetween('agendado_para', [$inicio->format('Y-m-d 00:00:00'), $fim->format('Y-m-d 23:59:59')])
                ->whereIn('status', ['aceito', 'em_andamento'])
                ->get();

            // Buscar horários disponíveis do prestador
            $horariosDisponiveis = $this->getHorariosPrestador($user->id);

            $resultado = [];

            // Gerar todos os horários do dia baseado nos horários disponíveis
            for ($i = 0; $i <= 6; $i++) {
                $data = $inicio->copy()->addDays($i)->format('Y-m-d');

                foreach ($horariosDisponiveis as $horario) {
                    $agendado = $agenda->firstWhere(function ($item) use ($data, $horario) {
                        return $item->data === $data && $item->horario_inicio === $horario;
                    });

                    $ocupado = $pedidos->firstWhere(function ($pedido) use ($data, $horario) {
                        return $pedido->agendado_para &&
                               Carbon::parse($pedido->agendado_para)->format('Y-m-d H:i') === $data . ' ' . $horario;
                    });

                    $resultado[] = [
                        'id' => $agendado?->id,
                        'data' => $data,
                        'horario_inicio' => $horario,
                        'horario_fim' => $horario,
                        'bloqueado' => $agendado?->bloqueado ?? false,
                        'ocupado' => !is_null($ocupado),
                        'motivo' => $agendado?->observacao,
                        'pedido_id' => $ocupado?->id,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $resultado
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar agenda: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar agenda: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/prestador/agenda/{data}
     * Buscar agenda de um dia específico
     */
    public function show(Request $request, $data)
    {
        try {
            $user = $request->user();

            $agenda = Agenda::where('prestador_id', $user->id)
                ->where('data', $data)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $agenda
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar agenda do dia: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar agenda: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/prestador/agenda/horarios
     * Buscar horários disponíveis para agenda
     */
    public function horarios(Request $request)
    {
        try {
            $user = $request->user();
            $horarios = $this->getHorariosPrestador($user->id);

            return response()->json([
                'success' => true,
                'data' => $horarios
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar horários: ' . $e->getMessage());

            $horarios = $this->getDefaultHorarios();

            return response()->json([
                'success' => true,
                'data' => $horarios,
                'source' => 'error_fallback'
            ]);
        }
    }

    /**
     * PUT /api/prestador/agenda/horarios
     * Atualizar horários do prestador
     */
    public function atualizarHorarios(Request $request)
    {
        try {
            $user = $request->user();

            $request->validate([
                'horarios' => 'required|array|min:1',
                'horarios.*' => 'string|regex:/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/',
            ]);

            $config = Configuracoes::updateOrCreate(
                [
                    'chave' => 'prestador_horarios_' . $user->id,
                ],
                [
                    'valor' => json_encode($request->horarios),
                    'grupo' => 'prestador',
                    'descricao' => 'Horários de agenda do prestador ' . $user->nome,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Horários atualizados com sucesso!',
                'data' => $request->horarios
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao atualizar horários: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar horários: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/prestador/agenda
     * Atualizar agenda (múltiplos bloqueios)
     */
    public function update(Request $request)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'bloqueios' => 'required|array',
                'bloqueios.*.data' => 'required|date',
                'bloqueios.*.horario_inicio' => 'required|string',
                'bloqueios.*.horario_fim' => 'required|string',
                'bloqueios.*.motivo' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $resultados = [];

            foreach ($request->bloqueios as $bloqueio) {
                $agenda = Agenda::updateOrCreate(
                    [
                        'prestador_id' => $user->id,
                        'data' => $bloqueio['data'],
                        'horario_inicio' => $bloqueio['horario_inicio'],
                        'horario_fim' => $bloqueio['horario_fim'],
                    ],
                    [
                        'bloqueado' => true,
                        'observacao' => $bloqueio['motivo'] ?? null,
                    ]
                );

                $resultados[] = $agenda;
            }

            return response()->json([
                'success' => true,
                'message' => count($resultados) . ' horário(s) atualizado(s)',
                'data' => $resultados
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao atualizar agenda: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar agenda: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/prestador/agenda/bloquear
     * Bloquear um horário específico
     */
    public function bloquearHorario(Request $request)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'data' => 'required|date',
                'horario_inicio' => 'required|string',
                'horario_fim' => 'required|string',
                'motivo' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $agenda = Agenda::updateOrCreate(
                [
                    'prestador_id' => $user->id,
                    'data' => $request->data,
                    'horario_inicio' => $request->horario_inicio,
                    'horario_fim' => $request->horario_fim,
                ],
                [
                    'bloqueado' => true,
                    'observacao' => $request->motivo,
                ]
            );

            NotificationService::send('agenda.dia_bloqueado', $user->id, [
                'data' => Carbon::parse($request->data)->format('d/m/Y'),
                'motivo' => $request->motivo ?? 'Não informado',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Horário bloqueado com sucesso',
                'data' => $agenda
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao bloquear horário: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao bloquear horário: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/prestador/agenda/bloquear/{id}
     * Desbloquear um horário
     */
    public function desbloquearHorario($id, Request $request)
    {
        try {
            $user = $request->user();

            $agenda = Agenda::where('id', $id)
                ->where('prestador_id', $user->id)
                ->first();

            if (!$agenda) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro não encontrado'
                ], 404);
            }

            $agenda->delete();

            NotificationService::send('agenda.dia_desbloqueado', $user->id, [
                'data' => Carbon::parse($agenda->data)->format('d/m/Y'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Horário desbloqueado com sucesso'
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao desbloquear horário: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao desbloquear horário: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/prestador/agenda/verificar-disponibilidade
     * Verificar disponibilidade em lote
     */
    public function verificarDisponibilidadeEmLote(Request $request)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'horarios' => 'required|array',
                'horarios.*.data' => 'required|date',
                'horarios.*.hora' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $resultados = [];

            foreach ($request->horarios as $horario) {
                $data = $horario['data'];
                $hora = $horario['hora'];

                $bloqueado = Agenda::where('prestador_id', $user->id)
                    ->where('data', $data)
                    ->where('horario_inicio', '<=', $hora)
                    ->where('horario_fim', '>=', $hora)
                    ->where('bloqueado', true)
                    ->exists();

                $ocupado = Pedido::where('prestador_id', $user->id)
                    ->where('agendado_para', $data . ' ' . $hora)
                    ->whereIn('status', ['aceito', 'em_andamento'])
                    ->exists();

                $resultados[] = [
                    'data' => $data,
                    'hora' => $hora,
                    'disponivel' => !$bloqueado && !$ocupado,
                    'bloqueado' => $bloqueado,
                    'ocupado' => $ocupado,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $resultados
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao verificar disponibilidade: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao verificar disponibilidade: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/prestador/agenda/intervalos
     * Listar intervalos recorrentes
     */
    public function intervalos(Request $request)
    {
        try {
            $user = $request->user();

            $intervalos = IntervaloAgenda::where('prestador_id', $user->id)
                ->where('ativo', true)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $intervalos
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar intervalos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar intervalos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/prestador/agenda/intervalos
     * Criar intervalo recorrente
     */
    public function storeIntervalo(Request $request)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'dias' => 'required|array',
                'dias.*' => 'string|in:segunda,terca,quarta,quinta,sexta,sabado,domingo',
                'inicio' => 'required|string',
                'fim' => 'required|string',
                'descricao' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $intervalo = IntervaloAgenda::create([
                'prestador_id' => $user->id,
                'dias' => json_encode($request->dias),
                'inicio' => $request->inicio,
                'fim' => $request->fim,
                'descricao' => $request->descricao,
                'ativo' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Intervalo criado com sucesso',
                'data' => $intervalo
            ], 201);

        } catch (\Exception $e) {
            Log::error('Erro ao criar intervalo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar intervalo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/prestador/agenda/intervalos/{id}
     * Atualizar intervalo recorrente
     */
    public function updateIntervalo($id, Request $request)
    {
        try {
            $user = $request->user();

            $intervalo = IntervaloAgenda::where('id', $id)
                ->where('prestador_id', $user->id)
                ->first();

            if (!$intervalo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Intervalo não encontrado'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'dias' => 'sometimes|array',
                'dias.*' => 'string|in:segunda,terca,quarta,quinta,sexta,sabado,domingo',
                'inicio' => 'sometimes|string',
                'fim' => 'sometimes|string',
                'descricao' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->only(['dias', 'inicio', 'fim', 'descricao']);
            if (isset($data['dias'])) {
                $data['dias'] = json_encode($data['dias']);
            }
            $intervalo->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Intervalo atualizado com sucesso',
                'data' => $intervalo
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao atualizar intervalo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar intervalo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/prestador/agenda/intervalos/{id}
     * Deletar intervalo recorrente
     */
    public function destroyIntervalo($id, Request $request)
    {
        try {
            $user = $request->user();

            $intervalo = IntervaloAgenda::where('id', $id)
                ->where('prestador_id', $user->id)
                ->first();

            if (!$intervalo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Intervalo não encontrado'
                ], 404);
            }

            $intervalo->delete();

            return response()->json([
                'success' => true,
                'message' => 'Intervalo removido com sucesso'
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao deletar intervalo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao deletar intervalo: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==========================================
    // MÉTODOS PRIVADOS AUXILIARES
    // ==========================================

    /**
     * Buscar horários do prestador (configuração ou padrão)
     */
    private function getHorariosPrestador($prestadorId): array
    {
        // Buscar configuração específica do prestador
        $prestadorConfig = Configuracoes::where('chave', 'prestador_horarios_' . $prestadorId)->first();

        if ($prestadorConfig && $prestadorConfig->valor) {
            $horarios = json_decode($prestadorConfig->valor, true);
            if (is_array($horarios) && count($horarios) > 0) {
                return $horarios;
            }
        }

        // Buscar configuração global
        $config = Configuracoes::where('chave', 'horarios_agenda')->first();

        if ($config && $config->valor) {
            $horarios = json_decode($config->valor, true);
            if (is_array($horarios) && count($horarios) > 0) {
                return $horarios;
            }
        }

        return $this->getDefaultHorarios();
    }

    /**
     * Horários padrão (fallback)
     */
    private function getDefaultHorarios(): array
    {
        $horarios = [];
        for ($h = 8; $h <= 19; $h++) {
            $horarios[] = sprintf('%02d:00', $h);
            $horarios[] = sprintf('%02d:30', $h);
        }
        return $horarios;
    }
}
