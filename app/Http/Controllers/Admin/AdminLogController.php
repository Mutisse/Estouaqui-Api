<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class AdminLogController extends Controller
{
    /**
     * Listar todos os logs com paginação e filtros
     * GET /admin/logs
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search');
            $nivel = $request->input('nivel');
            $acao = $request->input('acao');
            $modulo = $request->input('modulo');
            $userId = $request->input('user_id');
            $dataInicio = $request->input('data_inicio');
            $dataFim = $request->input('data_fim');
            $ip = $request->input('ip');

            $query = LogSistema::query();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('descricao', 'like', "%{$search}%")
                      ->orWhere('user_nome', 'like', "%{$search}%")
                      ->orWhere('user_email', 'like', "%{$search}%");
                });
            }

            if ($nivel) {
                $query->where('nivel', $nivel);
            }

            if ($acao) {
                $query->where('acao', $acao);
            }

            if ($modulo) {
                $query->where('modulo', $modulo);
            }

            if ($userId) {
                $query->where('user_id', $userId);
            }

            if ($dataInicio) {
                $query->whereDate('created_at', '>=', $dataInicio);
            }

            if ($dataFim) {
                $query->whereDate('created_at', '<=', $dataFim);
            }

            if ($ip) {
                $query->where('ip', $ip);
            }

            $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $logs->items(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estatísticas de logs
     * GET /admin/logs/estatisticas
     */
    public function estatisticas(Request $request)
    {
        try {
            $dataInicio = $request->input('data_inicio');
            $dataFim = $request->input('data_fim');

            $query = LogSistema::query();

            if ($dataInicio) {
                $query->whereDate('created_at', '>=', $dataInicio);
            }

            if ($dataFim) {
                $query->whereDate('created_at', '<=', $dataFim);
            }

            $estatisticas = [
                'total' => $query->count(),
                'por_nivel' => [
                    'info' => (clone $query)->where('nivel', 'info')->count(),
                    'warning' => (clone $query)->where('nivel', 'warning')->count(),
                    'error' => (clone $query)->where('nivel', 'error')->count(),
                    'debug' => (clone $query)->where('nivel', 'debug')->count(),
                ],
                'por_acao' => (clone $query)->select('acao', DB::raw('count(*) as total'))
                    ->groupBy('acao')
                    ->get()
                    ->pluck('total', 'acao')
                    ->toArray(),
                'por_modulo' => (clone $query)->select('modulo', DB::raw('count(*) as total'))
                    ->groupBy('modulo')
                    ->get()
                    ->pluck('total', 'modulo')
                    ->toArray(),
                'logs_por_dia' => (clone $query)->select(DB::raw('DATE(created_at) as data'), DB::raw('count(*) as total'))
                    ->groupBy('data')
                    ->orderBy('data', 'desc')
                    ->limit(30)
                    ->get()
                    ->map(function ($item) {
                        return [
                            'data' => $item->data,
                            'total' => $item->total,
                        ];
                    }),
                'usuarios_ativos' => (clone $query)->whereNotNull('user_id')
                    ->select('user_id', 'user_nome', DB::raw('count(*) as total'))
                    ->groupBy('user_id', 'user_nome')
                    ->orderBy('total', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function ($item) {
                        return [
                            'user_id' => $item->user_id,
                            'user_nome' => $item->user_nome,
                            'total' => $item->total,
                        ];
                    }),
                'ips_mais_frequentes' => (clone $query)->select('ip', DB::raw('count(*) as total'))
                    ->groupBy('ip')
                    ->orderBy('total', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function ($item) {
                        return [
                            'ip' => $item->ip,
                            'total' => $item->total,
                        ];
                    }),
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
     * Limpar logs antigos
     * DELETE /admin/logs/limpar
     */
    public function limpar(Request $request)
    {
        try {
            $diasManter = $request->input('dias', 30);

            $deleted = LogSistema::where('created_at', '<', Carbon::now()->subDays($diasManter))->delete();

            return response()->json([
                'success' => true,
                'message' => "{$deleted} logs antigos removidos com sucesso"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao limpar logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar logs
     * GET /admin/logs/exportar
     */
    public function exportar(Request $request)
    {
        try {
            $formato = $request->input('formato', 'csv');
            $dataInicio = $request->input('data_inicio');
            $dataFim = $request->input('data_fim');
            $search = $request->input('search');
            $nivel = $request->input('nivel');
            $acao = $request->input('acao');

            $query = LogSistema::query();

            if ($dataInicio) {
                $query->whereDate('created_at', '>=', $dataInicio);
            }

            if ($dataFim) {
                $query->whereDate('created_at', '<=', $dataFim);
            }

            if ($search) {
                $query->where('descricao', 'like', "%{$search}%");
            }

            if ($nivel) {
                $query->where('nivel', $nivel);
            }

            if ($acao) {
                $query->where('acao', $acao);
            }

            $logs = $query->orderBy('created_at', 'desc')->get();

            if ($formato === 'csv') {
                $content = $this->gerarCSV($logs);
                return response($content, 200, [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => "attachment; filename=logs_" . date('Y-m-d') . ".csv",
                ]);
            } elseif ($formato === 'json') {
                return response()->json($logs);
            } elseif ($formato === 'excel') {
                // Para Excel, vamos gerar um CSV também
                $content = $this->gerarCSV($logs);
                return response($content, 200, [
                    'Content-Type' => 'application/vnd.ms-excel',
                    'Content-Disposition' => "attachment; filename=logs_" . date('Y-m-d') . ".xls",
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Formato não suportado'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao exportar logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gerar CSV a partir dos logs
     */
    private function gerarCSV($logs): string
    {
        $csv = "ID,Nível,Ação,Usuário,Email,Descrição,IP,Módulo,Data\n";

        foreach ($logs as $log) {
            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $log->id,
                $log->nivel,
                $log->acao,
                $log->user_nome ?? 'Sistema',
                $log->user_email ?? '',
                str_replace(',', ';', $log->descricao),
                $log->ip,
                $log->modulo,
                $log->created_at
            );
        }

        return $csv;
    }
}
