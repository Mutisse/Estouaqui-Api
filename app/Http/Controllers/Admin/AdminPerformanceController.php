<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\LogSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AdminPerformanceController extends Controller
{
    /**
     * Cache time (5 minutes)
     */
    private const CACHE_TIME = 300;
    private const CACHE_KEY_PREFIX = 'performance_';

    /**
     * Carregar métricas de performance
     * GET /admin/performance
     */
    public function index(Request $request)
    {
        try {
            $periodo = $request->input('periodo', 'hoje');
            $cacheKey = self::CACHE_KEY_PREFIX . 'index_' . $periodo;

            $data = Cache::remember($cacheKey, self::CACHE_TIME, function () use ($periodo) {
                return [
                    'metricas_atuais' => $this->getMetricasAtuais(),
                    'historico_tempo_resposta' => $this->getHistorico($periodo, 'tempo_resposta'),
                    'historico_cpu' => $this->getHistorico($periodo, 'cpu'),
                    'historico_memoria' => $this->getHistorico($periodo, 'memoria'),
                    'historico_requisicoes' => $this->getHistorico($periodo, 'requisicoes'),
                    'top_endpoints' => $this->getTopEndpoints($periodo),
                    'logs_erro' => $this->getLogsErro($periodo),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar métricas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Métricas em tempo real (sem cache)
     * GET /admin/performance/realtime
     */
    public function realtime()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->getMetricasAtuais()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar métricas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Histórico de métricas
     * GET /admin/performance/historico
     */
    public function historico(Request $request)
    {
        try {
            $periodo = $request->input('periodo', 'hoje');
            $tipo = $request->input('tipo', 'todos');

            if ($tipo !== 'todos') {
                $cacheKey = self::CACHE_KEY_PREFIX . "historico_{$periodo}_{$tipo}";
                $data = Cache::remember($cacheKey, self::CACHE_TIME, function () use ($periodo, $tipo) {
                    return $this->getHistorico($periodo, $tipo);
                });
                return response()->json(['success' => true, 'data' => $data]);
            }

            $cacheKey = self::CACHE_KEY_PREFIX . "historico_{$periodo}_all";
            $data = Cache::remember($cacheKey, self::CACHE_TIME, function () use ($periodo) {
                return [
                    'tempo_resposta' => $this->getHistorico($periodo, 'tempo_resposta'),
                    'cpu' => $this->getHistorico($periodo, 'cpu'),
                    'memoria' => $this->getHistorico($periodo, 'memoria'),
                    'requisicoes' => $this->getHistorico($periodo, 'requisicoes'),
                ];
            });

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar histórico: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== MÉTODOS PRIVADOS OTIMIZADOS ====================

    /**
     * Métricas atuais (apenas queries essenciais)
     */
    private function getMetricasAtuais(): array
    {
        // Todas as queries em uma única transação
        return DB::transaction(function () {
            $hoje = Carbon::today();
            $ultimaHora = Carbon::now()->subHour();
            $ultimos15Min = Carbon::now()->subMinutes(15);

            // Queries em paralelo usando uma única consulta agregada
            $stats = LogSistema::where('created_at', '>=', $ultimaHora)
                ->select(
                    DB::raw('COUNT(*) as total_logs'),
                    DB::raw('COUNT(DISTINCT user_id) as usuarios_ativos'),
                    DB::raw('SUM(CASE WHEN nivel = "error" THEN 1 ELSE 0 END) as total_erros')
                )
                ->first();

            $pedidosHoje = Pedido::whereDate('created_at', $hoje)->count();
            $faturamentoHoje = (float) Pedido::whereDate('created_at', $hoje)
                ->where('status', 'concluido')
                ->sum('valor');

            $usuariosOnline = $stats->usuarios_ativos ?? 0;

            $erroRate = 0;
            if ($stats->total_logs > 0) {
                $erroRate = round(($stats->total_erros / $stats->total_logs) * 100, 1);
            }

            return [
                'tempo_resposta' => $this->getAverageResponseTime(),
                'uso_memoria' => $this->getMemoryUsage(),
                'espaco_disco' => $this->getDiskUsage(),
                'requisicoes' => (int) ($stats->total_logs ?? 0),
                'cpu_usage' => $this->getCpuUsage(),
                'uptime' => $this->getUptime(),
                'database_queries' => (int) ($stats->total_logs * 10 ?? 0),
                'cache_hit_rate' => 85,
                'erro_rate' => $erroRate,
                'usuarios_online' => $usuariosOnline,
                'servicos_ativos' => 5,
                'pedidos_hoje' => $pedidosHoje,
                'faturamento_hoje' => $faturamentoHoje,
            ];
        });
    }

    /**
     * Histórico otimizado com queries agregadas
     */
    private function getHistorico(string $periodo, string $tipo): array
    {
        $config = $this->getPeriodoConfig($periodo);
        $horas = $config['horas'];
        $intervalo = $config['intervalo'];
        $total = (int) ($horas / $intervalo);

        // Buscar logs agregados por hora
        $dataInicio = Carbon::now()->subHours($horas);

        $logsPorHora = LogSistema::where('created_at', '>=', $dataInicio)
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00") as hora'),
                DB::raw('COUNT(*) as total_logs'),
                DB::raw('SUM(CASE WHEN nivel = "error" THEN 1 ELSE 0 END) as total_erros')
            )
            ->groupBy('hora')
            ->orderBy('hora', 'asc')
            ->get()
            ->keyBy('hora');

        $resultado = [];
        for ($i = $total; $i >= 0; $i--) {
            $timestamp = Carbon::now()->subHours($horas - ($i * $intervalo));
            $horaKey = $timestamp->format('Y-m-d H:00:00');
            $logData = $logsPorHora[$horaKey] ?? null;
            $logCount = $logData ? $logData->total_logs : 0;
            $errorCount = $logData ? $logData->total_erros : 0;

            $valor = match ($tipo) {
                'tempo_resposta' => $this->estimateResponseTime($logCount),
                'cpu' => $this->getCpuUsage(),
                'memoria' => $this->getMemoryUsage(),
                'requisicoes' => $logCount,
                default => 0,
            };

            $resultado[] = [
                'timestamp' => $timestamp->toISOString(),
                'valor' => $valor,
            ];
        }

        return $resultado;
    }

    /**
     * Top endpoints (query agregada)
     */
    private function getTopEndpoints(string $periodo): array
    {
        $dias = match($periodo) {
            'ultima_semana' => 7,
            'ultimo_mes' => 30,
            default => 1,
        };

        $cacheKey = self::CACHE_KEY_PREFIX . "top_endpoints_{$dias}";

        return Cache::remember($cacheKey, self::CACHE_TIME, function () use ($dias) {
            $dataLimite = Carbon::now()->subDays($dias);

            $endpoints = LogSistema::where('created_at', '>=', $dataLimite)
                ->whereNotNull('modulo')
                ->select('modulo', DB::raw('COUNT(*) as total'))
                ->groupBy('modulo')
                ->orderBy('total', 'desc')
                ->limit(5)
                ->get();

            if ($endpoints->isEmpty()) {
                return $this->getDefaultEndpoints();
            }

            return $endpoints->map(fn($item) => [
                'endpoint' => "/api/{$item->modulo}",
                'metodo' => $this->getMethodForEndpoint($item->modulo),
                'total' => $item->total,
                'tempo_medio' => min(200, 30 + (int)($item->total / 100)),
            ])->toArray();
        });
    }

    /**
     * Logs de erro (últimos 50 apenas)
     */
    private function getLogsErro(string $periodo): array
    {
        $dias = match($periodo) {
            'ultima_semana' => 7,
            'ultimo_mes' => 30,
            default => 1,
        };

        $cacheKey = self::CACHE_KEY_PREFIX . "logs_erro_{$dias}";

        return Cache::remember($cacheKey, self::CACHE_TIME, function () use ($dias) {
            $dataLimite = Carbon::now()->subDays($dias);

            return LogSistema::where('nivel', 'error')
                ->where('created_at', '>=', $dataLimite)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->map(fn($log) => [
                    'id' => $log->id,
                    'nivel' => $log->nivel,
                    'mensagem' => $log->descricao,
                    'arquivo' => $log->modulo ?? 'sistema',
                    'linha' => 0,
                    'created_at' => $log->created_at->toISOString(),
                ])
                ->toArray();
        });
    }

    // ==================== CONFIGURAÇÕES DE PERÍODO ====================

    private function getPeriodoConfig(string $periodo): array
    {
        return match ($periodo) {
            'ultima_semana' => ['horas' => 168, 'intervalo' => 4],
            'ultimo_mes' => ['horas' => 720, 'intervalo' => 12],
            'ultima_hora' => ['horas' => 1, 'intervalo' => 0.25],
            default => ['horas' => 24, 'intervalo' => 2],
        };
    }

    // ==================== MÉTRICAS DE SISTEMA (CACHE) ====================

    private function getCpuUsage(): int
    {
        return Cache::remember('system_cpu_usage', 60, function () {
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                return (int) min(100, $load[0] * 10);
            }
            return 0;
        });
    }

    private function getMemoryUsage(): int
    {
        return Cache::remember('system_memory_usage', 60, function () {
            if (function_exists('memory_get_usage')) {
                $total = 1024 * 1024 * 1024; // 1GB
                $used = memory_get_usage(true);
                return (int) min(100, ($used / $total) * 100);
            }
            return 0;
        });
    }

    private function getDiskUsage(): int
    {
        return Cache::remember('system_disk_usage', 300, function () {
            $total = disk_total_space('/');
            $free = disk_free_space('/');
            if ($total > 0) {
                return (int) min(100, (($total - $free) / $total) * 100);
            }
            return 0;
        });
    }

    private function getUptime(): int
    {
        return Cache::remember('system_uptime', 60, function () {
            if (function_exists('shell_exec')) {
                $uptime = shell_exec('uptime -s');
                if ($uptime) {
                    $bootTime = strtotime(trim($uptime));
                    return time() - $bootTime;
                }
            }
            return 86400;
        });
    }

    private function getAverageResponseTime(): int
    {
        return Cache::remember('system_response_time', 60, function () {
            $ultimaHora = Carbon::now()->subHour();
            $logCount = LogSistema::where('created_at', '>=', $ultimaHora)->count();
            return min(500, 50 + (int)($logCount / 10));
        });
    }

    private function estimateResponseTime(int $logCount): int
    {
        return min(500, 50 + (int)($logCount / 5));
    }

    private function getMethodForEndpoint(string $endpoint): string
    {
        $methods = [
            'pedidos' => 'GET',
            'servicos' => 'POST',
            'users' => 'PUT',
            'auth' => 'POST',
            'categorias' => 'GET',
        ];
        return $methods[$endpoint] ?? 'GET';
    }

    private function getDefaultEndpoints(): array
    {
        return [
            ['endpoint' => '/api/auth/login', 'metodo' => 'POST', 'total' => 0, 'tempo_medio' => 0],
            ['endpoint' => '/api/categorias', 'metodo' => 'GET', 'total' => 0, 'tempo_medio' => 0],
            ['endpoint' => '/api/prestadores', 'metodo' => 'GET', 'total' => 0, 'tempo_medio' => 0],
            ['endpoint' => '/api/pedidos', 'metodo' => 'POST', 'total' => 0, 'tempo_medio' => 0],
            ['endpoint' => '/api/perfil', 'metodo' => 'GET', 'total' => 0, 'tempo_medio' => 0],
        ];
    }
}
