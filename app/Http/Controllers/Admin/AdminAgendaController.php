<?php
// app/Http/Controllers/Admin/AdminAgendaController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Agenda;
use App\Models\Pedido;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AdminAgendaController extends Controller
{
    /**
     * GET /admin/agenda/prestador/{prestadorId}
     * Visualizar agenda de um prestador (para admin)
     */
    public function showPrestadorAgenda($prestadorId, Request $request)
    {
        try {
            $prestador = User::prestadores()->find($prestadorId);

            if (!$prestador) {
                return response()->json([
                    'success' => false,
                    'message' => 'Prestador não encontrado'
                ], 404);
            }

            $dataInicio = $request->get('data_inicio', Carbon::now()->startOfWeek()->format('Y-m-d'));
            $dataFim = $request->get('data_fim', Carbon::now()->endOfWeek()->format('Y-m-d'));

            $agenda = Agenda::where('prestador_id', $prestadorId)
                ->whereBetween('data', [$dataInicio, $dataFim])
                ->get();

            $pedidos = Pedido::where('prestador_id', $prestadorId)
                ->whereBetween('agendado_para', [$dataInicio . ' 00:00:00', $dataFim . ' 23:59:59'])
                ->whereIn('status', ['aceito', 'em_andamento'])
                ->get();

            $horarios = [];
            $inicio = Carbon::parse($dataInicio);
            $fim = Carbon::parse($dataFim);

            for ($data = $inicio->copy(); $data <= $fim; $data->addDay()) {
                $dataStr = $data->format('Y-m-d');

                for ($h = 8; $h <= 20; $h++) {
                    $hora = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';

                    $agendado = $agenda->firstWhere(function ($item) use ($dataStr, $hora) {
                        return $item->data === $dataStr && $item->horario_inicio === $hora;
                    });

                    $ocupado = $pedidos->firstWhere(function ($pedido) use ($dataStr, $hora) {
                        return $pedido->agendado_para &&
                               Carbon::parse($pedido->agendado_para)->format('Y-m-d H:i') === $dataStr . ' ' . $hora;
                    });

                    $horarios[] = [
                        'data' => $dataStr,
                        'horario' => $hora,
                        'bloqueado' => $agendado?->bloqueado ?? false,
                        'ocupado' => !is_null($ocupado),
                        'motivo' => $agendado?->observacao,
                        'pedido_id' => $ocupado?->id,
                        'cliente_nome' => $ocupado?->cliente->nome ?? null,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'prestador' => [
                        'id' => $prestador->id,
                        'nome' => $prestador->nome,
                        'email' => $prestador->email,
                        'telefone' => $prestador->telefone,
                    ],
                    'agenda' => $horarios,
                    'resumo' => [
                        'total_bloqueios' => $agenda->where('bloqueado', true)->count(),
                        'total_ocupados' => $pedidos->count(),
                        'periodo' => [
                            'inicio' => $dataInicio,
                            'fim' => $dataFim,
                        ]
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar agenda do prestador: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar agenda: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /admin/agenda/prestador/{prestadorId}/bloquear
     * Bloquear horário do prestador (admin)
     */
    public function bloquearHorarioAdmin($prestadorId, Request $request)
    {
        try {
            $prestador = User::prestadores()->find($prestadorId);

            if (!$prestador) {
                return response()->json([
                    'success' => false,
                    'message' => 'Prestador não encontrado'
                ], 404);
            }

            $request->validate([
                'data' => 'required|date',
                'horario_inicio' => 'required|string',
                'horario_fim' => 'required|string',
                'motivo' => 'nullable|string|max:500',
            ]);

            $agenda = Agenda::updateOrCreate(
                [
                    'prestador_id' => $prestadorId,
                    'data' => $request->data,
                    'horario_inicio' => $request->horario_inicio,
                    'horario_fim' => $request->horario_fim,
                ],
                [
                    'bloqueado' => true,
                    'observacao' => $request->motivo ?? 'Bloqueado pelo administrador',
                ]
            );

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
     * DELETE /admin/agenda/{id}
     * Desbloquear horário do prestador (admin)
     */
    public function desbloquearHorarioAdmin($id)
    {
        try {
            $agenda = Agenda::find($id);

            if (!$agenda) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro não encontrado'
                ], 404);
            }

            $agenda->delete();

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
     * GET /admin/agenda/prestador/{prestadorId}/estatisticas
     * Estatísticas de agenda do prestador
     */
    public function estatisticas($prestadorId, Request $request)
    {
        try {
            $prestador = User::prestadores()->find($prestadorId);

            if (!$prestador) {
                return response()->json([
                    'success' => false,
                    'message' => 'Prestador não encontrado'
                ], 404);
            }

            $mes = $request->get('mes', Carbon::now()->month);
            $ano = $request->get('ano', Carbon::now()->year);

            $inicio = Carbon::create($ano, $mes, 1)->startOfMonth();
            $fim = Carbon::create($ano, $mes, 1)->endOfMonth();

            $bloqueios = Agenda::where('prestador_id', $prestadorId)
                ->whereBetween('data', [$inicio->format('Y-m-d'), $fim->format('Y-m-d')])
                ->where('bloqueado', true)
                ->count();

            $ocupados = Pedido::where('prestador_id', $prestadorId)
                ->whereBetween('agendado_para', [$inicio->format('Y-m-d 00:00:00'), $fim->format('Y-m-d 23:59:59')])
                ->whereIn('status', ['aceito', 'em_andamento'])
                ->count();

            $totalHorarios = 0;
            for ($data = $inicio->copy(); $data <= $fim; $data->addDay()) {
                $totalHorarios += 13; // 08:00 às 20:00
            }

            $disponiveis = $totalHorarios - ($bloqueios + $ocupados);

            return response()->json([
                'success' => true,
                'data' => [
                    'prestador' => [
                        'id' => $prestador->id,
                        'nome' => $prestador->nome,
                    ],
                    'periodo' => [
                        'mes' => $mes,
                        'ano' => $ano,
                        'inicio' => $inicio->format('Y-m-d'),
                        'fim' => $fim->format('Y-m-d'),
                    ],
                    'estatisticas' => [
                        'total_horarios' => $totalHorarios,
                        'bloqueados' => $bloqueios,
                        'ocupados' => $ocupados,
                        'disponiveis' => max(0, $disponiveis),
                        'taxa_ocupacao' => $totalHorarios > 0 ? round(($ocupados + $bloqueios) / $totalHorarios * 100, 1) : 0,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar estatísticas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar estatísticas: ' . $e->getMessage()
            ], 500);
        }
    }
}
