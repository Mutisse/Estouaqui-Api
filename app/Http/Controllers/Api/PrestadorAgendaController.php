<?php
// app/Http/Controllers/Api/PrestadorAgendaController.php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Agenda;
use App\Models\Pedido;
use App\Models\IntervaloAgenda;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PrestadorAgendaController extends BaseController
{
    /**
     * GET /api/prestador/agenda
     * Listar agenda do prestador para uma semana
     */
    public function index(Request $request)
    {
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

        $resultado = [];

        // Gerar todos os horários do dia (08:00 às 20:00)
        $horarios = [];
        for ($h = 8; $h <= 20; $h++) {
            $horario = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
            $horarios[] = $horario;
        }

        for ($i = 0; $i <= 6; $i++) {
            $data = $inicio->copy()->addDays($i)->format('Y-m-d');

            foreach ($horarios as $horario) {
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
    }

    /**
     * GET /api/prestador/agenda/{data}
     * Buscar agenda de um dia específico
     */
    public function show(Request $request, $data)
    {
        $user = $request->user();

        $agenda = Agenda::where('prestador_id', $user->id)
            ->where('data', $data)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $agenda
        ]);
    }

    /**
     * PUT /api/prestador/agenda
     * Atualizar agenda (múltiplos bloqueios)
     */
    public function update(Request $request)
    {
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
    }

    /**
     * POST /api/prestador/agenda/bloquear
     * Bloquear um horário específico
     */
    public function bloquearHorario(Request $request)
    {
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

        // 🔔 NOTIFICAÇÃO: Dia bloqueado
        NotificationService::send('agenda.dia_bloqueado', $user->id, [
            'data' => Carbon::parse($request->data)->format('d/m/Y'),
            'motivo' => $request->motivo ?? 'Não informado',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Horário bloqueado com sucesso',
            'data' => $agenda
        ]);
    }

    /**
     * DELETE /api/prestador/agenda/bloquear/{id}
     * Desbloquear um horário
     */
    public function desbloquearHorario($id, Request $request)
    {
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

        // 🔔 NOTIFICAÇÃO: Dia desbloqueado
        NotificationService::send('agenda.dia_desbloqueado', $user->id, [
            'data' => Carbon::parse($agenda->data)->format('d/m/Y'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Horário desbloqueado com sucesso'
        ]);
    }

    /**
     * GET /api/prestador/agenda/intervalos
     * Listar intervalos recorrentes
     */
    public function intervalos(Request $request)
    {
        $user = $request->user();

        $intervalos = IntervaloAgenda::where('prestador_id', $user->id)
            ->where('ativo', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $intervalos
        ]);
    }

    /**
     * POST /api/prestador/agenda/intervalos
     * Criar intervalo recorrente
     */
    public function storeIntervalo(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'dias' => 'required|array',
            'dias.*' => 'string',
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
            'dias' => $request->dias,
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
    }

    /**
     * PUT /api/prestador/agenda/intervalos/{id}
     * Atualizar intervalo recorrente
     */
    public function updateIntervalo($id, Request $request)
    {
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
            'dias.*' => 'string',
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

        $intervalo->update($request->only(['dias', 'inicio', 'fim', 'descricao']));

        return response()->json([
            'success' => true,
            'message' => 'Intervalo atualizado com sucesso',
            'data' => $intervalo
        ]);
    }

    /**
     * DELETE /api/prestador/agenda/intervalos/{id}
     * Deletar intervalo recorrente
     */
    public function destroyIntervalo($id, Request $request)
    {
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
    }
}
