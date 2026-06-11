<?php
// app/Http/Controllers/Api/PrestadorDashboardController.php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Pedido;
use App\Models\Avaliacao;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;

class PrestadorDashboardController extends BaseController
{
    public function stats(Request $request)
    {
        $user = $request->user();

        $stats = [
            'pedidos_pendentes' => Pedido::where('prestador_id', $user->id)
                ->where('status', 'pendente')
                ->count(),
            'servicos_hoje' => Pedido::where('prestador_id', $user->id)
                ->whereDate('agendado_para', Carbon::today())
                ->whereIn('status', ['aceito', 'em_andamento'])
                ->count(),
            'avaliacao_media' => (float) $user->media_avaliacao ?? 0,
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    public function ganhos(Request $request)
    {
        $user = $request->user();

        $pedidosConcluidos = Pedido::where('prestador_id', $user->id)
            ->where('status', 'concluido')
            ->get();

        $total = $pedidosConcluidos->sum('valor');
        $mes = $pedidosConcluidos->filter(function($p) {
            return $p->created_at->month === Carbon::now()->month;
        })->sum('valor');
        $semana = $pedidosConcluidos->filter(function($p) {
            return $p->created_at->gte(Carbon::now()->startOfWeek());
        })->sum('valor');

        $pendente = Pedido::where('prestador_id', $user->id)
            ->where('status', 'aceito')
            ->sum('valor');

        return response()->json([
            'success' => true,
            'data' => [
                'total' => (float) $total,
                'mes' => (float) $mes,
                'semana' => (float) $semana,
                'pendente' => (float) $pendente,
            ]
        ]);
    }

    public function proximosServicos(Request $request)
    {
        $user = $request->user();
        $limite = $request->get('limite', 5);

        $servicos = Pedido::where('prestador_id', $user->id)
            ->whereIn('status', ['aceito', 'em_andamento'])
            ->where('agendado_para', '>=', Carbon::now())
            ->with(['cliente' => function($q) {
                $q->select('id', 'nome', 'foto');
            }])
            ->orderBy('agendado_para', 'asc')
            ->limit($limite)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $servicos
        ]);
    }

    public function avaliacoesRecentes(Request $request)
    {
        $user = $request->user();
        $limite = $request->get('limite', 5);

        $avaliacoes = Avaliacao::where('prestador_id', $user->id)
            ->with(['cliente' => function($q) {
                $q->select('id', 'nome', 'foto');
            }])
            ->orderBy('created_at', 'desc')
            ->limit($limite)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $avaliacoes
        ]);
    }
}
