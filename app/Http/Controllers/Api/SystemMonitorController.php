<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class SystemMonitorController extends Controller
{
    /**
     * Health check - endpoint básico de saúde
     * GET /api/system/health
     */
    public function health()
    {
        $startTime = microtime(true);

        $status = [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'version' => '1.0.0',
            'environment' => app()->environment(),
            'checks' => [
                'app' => $this->checkApp(),
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'storage' => $this->checkStorage(),
            ],
            'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
        ];

        $httpCode = $status['status'] === 'healthy' ? 200 : 503;

        return response()->json($status, $httpCode);
    }

    /**
     * Métricas detalhadas do sistema (admin)
     * GET /api/system/metrics
     */
    public function metrics(Request $request)
    {
        $startTime = microtime(true);

        $metrics = [
            'timestamp' => now()->toIso8601String(),

            // CPU e Memória
            'system' => $this->getSystemMetrics(),

            // Banco de Dados
            'database' => $this->getDatabaseMetrics(),

            // Cache
            'cache' => $this->getCacheMetrics(),

            // Fila de Jobs
            'queue' => $this->getQueueMetrics(),

            // Armazenamento
            'storage' => $this->getStorageMetrics(),

            // Requisições
            'requests' => $this->getRequestMetrics(),

            // Response time
            'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
        ];

        return response()->json([
            'success' => true,
            'data' => $metrics
        ]);
    }

    /**
     * Performance do sistema
     * GET /api/system/performance
     */
    public function performance(Request $request)
    {
        $period = $request->get('period', 'hour'); // hour, day, week

        $metrics = Cache::remember("system_performance_{$period}", 60, function() use ($period) {
            return [
                'period' => $period,
                'avg_response_time' => $this->getAverageResponseTime($period),
                'requests_per_minute' => $this->getRequestsPerMinute($period),
                'error_rate' => $this->getErrorRate($period),
                'slow_queries' => $this->getSlowQueries($period),
                'cache_hit_rate' => $this->getCacheHitRate(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $metrics
        ]);
    }

    /**
     * Estatísticas do cache
     * GET /api/system/cache-stats
     */
    public function cacheStats()
    {
        $stats = [
            'default_driver' => config('cache.default'),
            'stores' => [],
        ];

        $stores = ['file', 'redis', 'database'];
        foreach ($stores as $store) {
            try {
                $stats['stores'][$store] = [
                    'available' => true,
                    'status' => 'healthy'
                ];
            } catch (\Exception $e) {
                $stats['stores'][$store] = [
                    'available' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        // Keys count aproximado
        $stats['keys_count'] = $this->getCacheKeysCount();
        $stats['memory_usage_mb'] = $this->getCacheMemoryUsage();

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Estatísticas do banco de dados
     * GET /api/system/database-stats
     */
    public function databaseStats()
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        $stats = [
            'connection' => $connection,
            'database' => $database,
            'status' => 'connected',
            'tables' => [],
        ];

        try {
            // Listar tabelas e contagens
            $tables = DB::select('SHOW TABLES');
            $tableKey = "Tables_in_{$database}";

            foreach ($tables as $table) {
                $tableName = $table->$tableKey;
                $count = DB::table($tableName)->count();
                $size = $this->getTableSize($tableName);

                $stats['tables'][] = [
                    'name' => $tableName,
                    'rows' => $count,
                    'size_mb' => round($size, 2),
                ];
            }

            $stats['total_rows'] = array_sum(array_column($stats['tables'], 'rows'));
            $stats['total_size_mb'] = round(array_sum(array_column($stats['tables'], 'size_mb')), 2);
            $stats['connection_time_ms'] = $this->getConnectionTime();

        } catch (\Exception $e) {
            $stats['status'] = 'error';
            $stats['error'] = $e->getMessage();
        }

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Estatísticas da fila
     * GET /api/system/queue-stats
     */
    public function queueStats()
    {
        $queues = ['default', 'high', 'low', 'emails'];
        $stats = [
            'connection' => config('queue.default'),
            'queues' => []
        ];

        foreach ($queues as $queue) {
            $stats['queues'][$queue] = [
                'pending_jobs' => $this->getQueueSize($queue),
                'failed_jobs' => $this->getFailedJobsCount($queue),
                'status' => 'active'
            ];
        }

        $stats['total_pending'] = array_sum(array_column($stats['queues'], 'pending_jobs'));
        $stats['total_failed'] = array_sum(array_column($stats['queues'], 'failed_jobs'));
        $stats['workers_active'] = $this->getActiveWorkers();

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Logs recentes
     * GET /api/system/logs/recent
     */
    public function recentLogs(Request $request)
    {
        $lines = $request->get('lines', 100);
        $level = $request->get('level', 'error');

        $logs = [];
        $logFile = storage_path('logs/laravel.log');

        if (file_exists($logFile)) {
            $content = file_get_contents($logFile);
            $lines_array = explode("\n", $content);
            $recent = array_slice($lines_array, -$lines);

            foreach ($recent as $line) {
                if (empty(trim($line))) continue;

                // Filtrar por nível se especificado
                if ($level !== 'all' && !str_contains(strtolower($line), $level)) {
                    continue;
                }

                // Parse da linha de log
                if (preg_match('/\[(.*?)\].*?\.(\w+):/', $line, $matches)) {
                    $logs[] = [
                        'timestamp' => $matches[1] ?? null,
                        'level' => $matches[2] ?? 'info',
                        'message' => trim($line),
                    ];
                } else {
                    $logs[] = [
                        'timestamp' => null,
                        'level' => 'info',
                        'message' => trim($line),
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total' => count($logs),
                'logs' => array_reverse($logs)
            ]
        ]);
    }

    /**
     * Alertas do sistema
     * GET /api/system/alerts
     */
    public function alerts()
    {
        $alerts = [];

        // Verificar espaço em disco
        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        $diskUsagePercent = (($diskTotal - $diskFree) / $diskTotal) * 100;

        if ($diskUsagePercent > 85) {
            $alerts[] = [
                'level' => 'warning',
                'type' => 'storage',
                'message' => "Espaço em disco baixo: " . round($diskUsagePercent, 1) . "% utilizado",
                'timestamp' => now()->toIso8601String()
            ];
        }

        // Verificar conexão com banco
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $alerts[] = [
                'level' => 'critical',
                'type' => 'database',
                'message' => 'Conexão com banco de dados falhou',
                'timestamp' => now()->toIso8601String()
            ];
        }

        // Verificar cache
        try {
            Cache::get('health_check');
        } catch (\Exception $e) {
            $alerts[] = [
                'level' => 'warning',
                'type' => 'cache',
                'message' => 'Cache system não está respondendo',
                'timestamp' => now()->toIso8601String()
            ];
        }

        // Verificar jobs falhados
        $failedJobs = $this->getFailedJobsCount('default');
        if ($failedJobs > 10) {
            $alerts[] = [
                'level' => 'warning',
                'type' => 'queue',
                'message' => "{$failedJobs} jobs falhados na fila",
                'timestamp' => now()->toIso8601String()
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_alerts' => count($alerts),
                'alerts' => $alerts
            ]
        ]);
    }

    // ==========================================
    // MÉTODOS AUXILIARES PRIVADOS
    // ==========================================

    private function checkApp(): array
    {
        return [
            'name' => config('app.name'),
            'environment' => app()->environment(),
            'debug' => config('app.debug'),
            'url' => config('app.url'),
            'timezone' => config('app.timezone'),
        ];
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $time = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'connected',
                'connection' => config('database.default'),
                'response_time_ms' => $time
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $start = microtime(true);
            Cache::put('health_check', true, 1);
            $value = Cache::get('health_check');
            Cache::forget('health_check');
            $time = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => $value === true ? 'healthy' : 'error',
                'driver' => config('cache.default'),
                'response_time_ms' => $time
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    private function checkStorage(): array
    {
        try {
            $testFile = 'health_check.txt';
            Storage::disk('local')->put($testFile, 'test');
            $exists = Storage::disk('local')->exists($testFile);
            Storage::disk('local')->delete($testFile);

            return [
                'status' => $exists ? 'healthy' : 'error',
                'disk' => config('filesystems.default'),
                'free_space_gb' => round(disk_free_space('/') / 1024 / 1024 / 1024, 2),
                'total_space_gb' => round(disk_total_space('/') / 1024 / 1024 / 1024, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    private function getSystemMetrics(): array
    {
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];

        return [
            'cpu' => [
                'load_1min' => round($load[0], 2),
                'load_5min' => round($load[1], 2),
                'load_15min' => round($load[2], 2),
            ],
            'memory' => [
                'total_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'limit_mb' => $this->getMemoryLimit(),
            ],
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        ];
    }

    private function getMemoryLimit(): float
    {
        $limit = ini_get('memory_limit');
        if ($limit == -1) return 0;

        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;

        switch ($unit) {
            case 'g': return $value * 1024;
            case 'm': return $value;
            case 'k': return $value / 1024;
            default: return $value;
        }
    }

    private function getDatabaseMetrics(): array
    {
        try {
            $connection = DB::connection();
            $pdo = $connection->getPdo();

            return [
                'connection' => config('database.default'),
                'status' => 'connected',
                'table_count' => count($connection->select('SHOW TABLES')),
                'query_log_enabled' => DB::logging(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    private function getCacheMetrics(): array
    {
        return [
            'driver' => config('cache.default'),
            'store' => config('cache.stores.' . config('cache.default') . '.driver'),
            'prefix' => config('cache.prefix'),
        ];
    }

    private function getQueueMetrics(): array
    {
        return [
            'connection' => config('queue.default'),
            'driver' => config('queue.connections.' . config('queue.default') . '.driver'),
        ];
    }

    private function getStorageMetrics(): array
    {
        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');

        return [
            'disk' => config('filesystems.default'),
            'free_gb' => round($diskFree / 1024 / 1024 / 1024, 2),
            'used_gb' => round(($diskTotal - $diskFree) / 1024 / 1024 / 1024, 2),
            'total_gb' => round($diskTotal / 1024 / 1024 / 1024, 2),
            'usage_percent' => round((($diskTotal - $diskFree) / $diskTotal) * 100, 2),
        ];
    }

    private function getRequestMetrics(): array
    {
        return [
            'total_requests_today' => $this->getTotalRequestsToday(),
            'average_per_second' => $this->getAverageRequestsPerSecond(),
        ];
    }

    private function getTotalRequestsToday(): int
    {
        // Implementar conforme sua necessidade
        return Cache::get('total_requests_today', 0);
    }

    private function getAverageRequestsPerSecond(): float
    {
        // Implementar conforme sua necessidade
        return 0;
    }

    private function getAverageResponseTime($period): float
    {
        // Implementar conforme sua necessidade
        return 150.5;
    }

    private function getRequestsPerMinute($period): int
    {
        // Implementar conforme sua necessidade
        return 45;
    }

    private function getErrorRate($period): float
    {
        // Implementar conforme sua necessidade
        return 2.5;
    }

    private function getSlowQueries($period): int
    {
        // Implementar conforme sua necessidade
        return 3;
    }

    private function getCacheHitRate(): float
    {
        // Implementar conforme sua necessidade
        return 85.5;
    }

    private function getCacheKeysCount(): int
    {
        try {
            if (config('cache.default') === 'redis') {
                return Redis::command('DBSIZE');
            }
        } catch (\Exception $e) {}

        return 0;
    }

    private function getCacheMemoryUsage(): float
    {
        try {
            if (config('cache.default') === 'redis') {
                $info = Redis::info('memory');
                return round($info['used_memory'] / 1024 / 1024, 2);
            }
        } catch (\Exception $e) {}

        return 0;
    }

    private function getTableSize($tableName): float
    {
        try {
            $result = DB::select("SHOW TABLE STATUS LIKE '{$tableName}'");
            if (!empty($result)) {
                return $result[0]->Data_length / 1024 / 1024;
            }
        } catch (\Exception $e) {}

        return 0;
    }

    private function getConnectionTime(): float
    {
        $start = microtime(true);
        DB::connection()->getPdo();
        return round((microtime(true) - $start) * 1000, 2);
    }

    private function getQueueSize($queue): int
    {
        // Implementar conforme seu sistema de filas
        return 0;
    }

    private function getFailedJobsCount($queue): int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getActiveWorkers(): int
    {
        // Implementar conforme seu sistema
        return 1;
    }
}
