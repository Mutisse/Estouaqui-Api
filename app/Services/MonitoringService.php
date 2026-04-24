<?php
// app/Services/MonitoringService.php

namespace App\Services;

use App\Models\User;
use App\Models\Pedido;
use App\Models\Servico;
use App\Models\Avaliacao;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MonitoringService
{
    // ==========================================
    // 1. MÉTRICAS DE NEGÓCIO AVANÇADAS
    // ==========================================

    public function getAdvancedBusinessMetrics(): array
    {
        $today = now()->startOfDay();
        $thisWeek = now()->startOfWeek();
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        // Usuários ativos (quem fez requisição)
        $activeUsers = DB::table('api_requests')
            ->whereDate('created_at', $today)
            ->distinct('user_id')
            ->count('user_id');

        // Taxa de conversão (visitante → cadastro)
        $uniqueVisitors = DB::table('api_requests')
            ->whereDate('created_at', $today)
            ->distinct('ip')
            ->count('ip');
        $newUsersToday = User::whereDate('created_at', $today)->count();
        $conversionRate = $uniqueVisitors > 0 ? round(($newUsersToday / $uniqueVisitors) * 100, 2) : 0;

        // Churn rate (cancelamentos de conta)
        $usersStartMonth = User::where('created_at', '<', $thisMonth)->count();
        $usersEndMonth = User::count();
        $churnRate = $usersStartMonth > 0
            ? round((($usersStartMonth - $usersEndMonth + $newUsersToday) / $usersStartMonth) * 100, 2)
            : 0;

        // Lifetime Value (LTV) - Receita média por cliente
        $totalRevenue = Pedido::where('status', 'concluido')->sum('valor');
        $totalUniqueClients = Pedido::where('status', 'concluido')->distinct('cliente_id')->count('cliente_id');
        $ltv = $totalUniqueClients > 0 ? round($totalRevenue / $totalUniqueClients, 2) : 0;

        // Categorias mais procuradas
        $topCategories = DB::table('servicos')
            ->join('categorias', 'servicos.categoria_id', '=', 'categorias.id')
            ->select('categorias.nome', DB::raw('COUNT(*) as total'))
            ->groupBy('categorias.id', 'categorias.nome')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->get()
            ->toArray();

        // Sazonalidade (serviços por hora do dia)
        $hourlyDistribution = DB::table('pedidos')
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as total'))
            ->where('status', 'concluido')
            ->whereMonth('created_at', now()->month)
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->toArray();

        return [
            'active_users_today' => $activeUsers,
            'conversion_rate' => $conversionRate,
            'churn_rate' => $churnRate,
            'customer_ltv' => $ltv,
            'top_categories' => $topCategories,
            'hourly_distribution' => $hourlyDistribution,
            'growth' => [
                'users_growth' => $this->calculateGrowth(User::class, 'users'),
                'revenue_growth' => $this->calculateRevenueGrowth(),
                'orders_growth' => $this->calculateGrowth(Pedido::class, 'orders'),
            ],
        ];
    }

    // ==========================================
    // 2. MONITORAMENTO DE DEPENDÊNCIAS EXTERNAS
    // ==========================================

    public function checkExternalServices(): array
    {
        $services = DB::table('external_services')->get();
        $results = [];

        foreach ($services as $service) {
            $status = $this->checkService($service->name);

            DB::table('external_services')
                ->where('id', $service->id)
                ->update([
                    'status' => $status['status'],
                    'response_time_ms' => $status['response_time_ms'],
                    'error_rate' => $status['error_rate'],
                    'last_check_at' => now(),
                    'last_error_at' => $status['status'] === 'down' ? now() : $service->last_error_at,
                    'last_error_message' => $status['error_message'],
                ]);

            $results[$service->name] = $status;

            // Gerar alerta se serviço estiver down
            if ($status['status'] === 'down') {
                $this->sendAlert(
                    'critical',
                    'external_service',
                    "Serviço externo DOWN: {$service->name}",
                    "O serviço {$service->name} está fora do ar. Erro: {$status['error_message']}",
                    ['service' => $service->name, 'error' => $status['error_message']]
                );
            }
        }

        return $results;
    }

    private function checkService(string $serviceName): array
    {
        $startTime = microtime(true);
        $status = 'healthy';
        $errorMessage = null;
        $errorRate = 0;

        try {
            switch ($serviceName) {
                case 'payment_gateway':
                    // Verificar gateway de pagamento (exemplo)
                    $response = Http::timeout(5)->get(config('services.payment.health_url', 'https://api.payment.com/health'));
                    $status = $response->successful() ? 'healthy' : 'degraded';
                    break;

                case 'sms_service':
                    // Verificar serviço de SMS
                    $response = Http::timeout(5)->get(config('services.sms.health_url', 'https://api.sms.com/health'));
                    $status = $response->successful() ? 'healthy' : 'degraded';
                    break;

                case 'email_service':
                    // Verificar serviço de email
                    $response = Http::timeout(5)->get(config('services.email.health_url', 'https://api.email.com/health'));
                    $status = $response->successful() ? 'healthy' : 'degraded';
                    break;

                case 'maps_api':
                    // Verificar API de mapas
                    $response = Http::timeout(5)->get('https://maps.googleapis.com/maps/api/geocode/json', [
                        'address' => 'test',
                        'key' => config('services.google.maps_key')
                    ]);
                    $status = $response->successful() ? 'healthy' : 'degraded';
                    break;

                default:
                    $status = 'unknown';
            }
        } catch (\Exception $e) {
            $status = 'down';
            $errorMessage = $e->getMessage();
            $errorRate = 100;
        }

        $responseTime = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'status' => $status,
            'response_time_ms' => $responseTime,
            'error_rate' => $errorRate,
            'error_message' => $errorMessage,
        ];
    }

    // ==========================================
    // 3. MONITORAMENTO DE SEGURANÇA
    // ==========================================

    public function logSecurityEvent(string $event, string $level, ?string $ip = null, ?int $userId = null, ?string $details = null, array $context = [])
    {
        DB::table('security_logs')->insert([
            'event' => $event,
            'level' => $level,
            'ip' => $ip,
            'user_id' => $userId,
            'details' => $details,
            'context' => json_encode($context),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Detectar brute force
        if ($event === 'login_failed') {
            $attempts = DB::table('security_logs')
                ->where('event', 'login_failed')
                ->where('ip', $ip)
                ->where('created_at', '>=', now()->subMinutes(15))
                ->count();

            if ($attempts >= 5) {
                $this->logSecurityEvent('brute_force_detected', 'critical', $ip, null, "Possível ataque de brute force: {$attempts} tentativas em 15 minutos");
                $this->sendAlert('critical', 'security', 'Ataque de Brute Force Detectado', "IP {$ip} fez {$attempts} tentativas de login em 15 minutos", ['ip' => $ip, 'attempts' => $attempts]);
            }
        }

        // Alertar para evento crítico
        if ($level === 'critical') {
            $this->sendAlert('critical', 'security', "Evento de Segurança: {$event}", $details ?? "Evento crítico detectado", $context);
        }
    }

    public function getSecurityMetrics(): array
    {
        $lastHour = now()->subHour();
        $lastDay = now()->subDay();

        return [
            'failed_logins_last_hour' => DB::table('security_logs')
                ->where('event', 'login_failed')
                ->where('created_at', '>=', $lastHour)
                ->count(),
            'failed_logins_last_day' => DB::table('security_logs')
                ->where('event', 'login_failed')
                ->where('created_at', '>=', $lastDay)
                ->count(),
            'blocked_ips' => DB::table('security_logs')
                ->where('event', 'ip_blocked')
                ->where('created_at', '>=', $lastDay)
                ->distinct('ip')
                ->count('ip'),
            'critical_events_last_day' => DB::table('security_logs')
                ->where('level', 'critical')
                ->where('created_at', '>=', $lastDay)
                ->count(),
            'top_offending_ips' => DB::table('security_logs')
                ->where('event', 'login_failed')
                ->where('created_at', '>=', $lastDay)
                ->select('ip', DB::raw('COUNT(*) as attempts'))
                ->groupBy('ip')
                ->orderBy('attempts', 'desc')
                ->limit(5)
                ->get()
                ->toArray(),
        ];
    }

    // ==========================================
    // 4. MONITORAMENTO DE PERFORMANCE AVANÇADO
    // ==========================================

    public function logSlowQuery(string $sql, array $bindings, int $timeMs, string $connection, ?string $path = null, ?string $ip = null, ?int $userId = null)
    {
        DB::table('slow_queries_log')->insert([
            'sql_query' => $sql,
            'bindings' => json_encode($bindings),
            'time_ms' => $timeMs,
            'connection' => $connection,
            'path' => $path,
            'ip' => $ip,
            'user_id' => $userId,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Alertar para queries muito lentas (> 1000ms)
        if ($timeMs > 1000) {
            $this->sendAlert(
                'warning',
                'performance',
                "Query extremamente lenta detectada",
                "Query levou {$timeMs}ms para executar: " . substr($sql, 0, 200),
                ['sql' => $sql, 'time_ms' => $timeMs, 'path' => $path]
            );
        }
    }

    public function getSlowQueriesAnalytics(int $hours = 24): array
    {
        $since = now()->subHours($hours);

        $queries = DB::table('slow_queries_log')
            ->where('occurred_at', '>=', $since)
            ->orderBy('time_ms', 'desc')
            ->limit(20)
            ->get()
            ->toArray();

        $averageTime = DB::table('slow_queries_log')
            ->where('occurred_at', '>=', $since)
            ->avg('time_ms') ?? 0;

        $totalSlowQueries = DB::table('slow_queries_log')
            ->where('occurred_at', '>=', $since)
            ->count();

        // Agrupar por tipo de query (SELECT, INSERT, UPDATE, DELETE)
        $queryTypes = [
            'SELECT' => DB::table('slow_queries_log')
                ->where('occurred_at', '>=', $since)
                ->where('sql_query', 'LIKE', 'SELECT%')
                ->count(),
            'INSERT' => DB::table('slow_queries_log')
                ->where('occurred_at', '>=', $since)
                ->where('sql_query', 'LIKE', 'INSERT%')
                ->count(),
            'UPDATE' => DB::table('slow_queries_log')
                ->where('occurred_at', '>=', $since)
                ->where('sql_query', 'LIKE', 'UPDATE%')
                ->count(),
            'DELETE' => DB::table('slow_queries_log')
                ->where('occurred_at', '>=', $since)
                ->where('sql_query', 'LIKE', 'DELETE%')
                ->count(),
        ];

        return [
            'total_slow_queries' => $totalSlowQueries,
            'average_time_ms' => round($averageTime, 2),
            'slowest_queries' => $queries,
            'query_types' => $queryTypes,
        ];
    }

    // ==========================================
    // 5. MÉTRICAS DE FRONTEND
    // ==========================================

    public function logFrontendMetrics(array $data)
    {
        DB::table('frontend_metrics')->insert([
            'url' => $data['url'] ?? '/',
            'lcp' => $data['lcp'] ?? 0,
            'fid' => $data['fid'] ?? 0,
            'cls' => $data['cls'] ?? 0,
            'ttfb' => $data['ttfb'] ?? 0,
            'fcp' => $data['fcp'] ?? 0,
            'device' => $data['device'] ?? 'desktop',
            'browser' => $data['browser'] ?? 'unknown',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Alertar para Core Web Vitals ruins
        if (($data['lcp'] ?? 0) > 2500) {
            $this->sendAlert('warning', 'frontend', 'LCP elevado', "LCP de {$data['lcp']}ms na página {$data['url']}", $data);
        }
    }

    public function getFrontendMetrics(): array
    {
        $lastDay = now()->subDay();

        $averages = DB::table('frontend_metrics')
            ->where('created_at', '>=', $lastDay)
            ->select([
                DB::raw('AVG(lcp) as avg_lcp'),
                DB::raw('AVG(fid) as avg_fid'),
                DB::raw('AVG(cls) as avg_cls'),
                DB::raw('AVG(ttfb) as avg_ttfb'),
                DB::raw('AVG(fcp) as avg_fcp'),
            ])
            ->first();

        $mobileMetrics = DB::table('frontend_metrics')
            ->where('created_at', '>=', $lastDay)
            ->where('device', 'mobile')
            ->select([
                DB::raw('AVG(lcp) as avg_lcp'),
                DB::raw('AVG(fid) as avg_fid'),
                DB::raw('AVG(cls) as avg_cls'),
            ])
            ->first();

        $desktopMetrics = DB::table('frontend_metrics')
            ->where('created_at', '>=', $lastDay)
            ->where('device', 'desktop')
            ->select([
                DB::raw('AVG(lcp) as avg_lcp'),
                DB::raw('AVG(fid) as avg_fid'),
                DB::raw('AVG(cls) as avg_cls'),
            ])
            ->first();

        return [
            'core_web_vitals' => [
                'lcp' => round($averages->avg_lcp ?? 0, 2),
                'fid' => round($averages->avg_fid ?? 0, 2),
                'cls' => round($averages->avg_cls ?? 0, 2),
                'ttfb' => round($averages->avg_ttfb ?? 0, 2),
                'fcp' => round($averages->avg_fcp ?? 0, 2),
            ],
            'by_device' => [
                'mobile' => [
                    'lcp' => round($mobileMetrics->avg_lcp ?? 0, 2),
                    'fid' => round($mobileMetrics->avg_fid ?? 0, 2),
                    'cls' => round($mobileMetrics->avg_cls ?? 0, 2),
                ],
                'desktop' => [
                    'lcp' => round($desktopMetrics->avg_lcp ?? 0, 2),
                    'fid' => round($desktopMetrics->avg_fid ?? 0, 2),
                    'cls' => round($desktopMetrics->avg_cls ?? 0, 2),
                ],
            ],
            'total_samples' => DB::table('frontend_metrics')->where('created_at', '>=', $lastDay)->count(),
        ];
    }

    // ==========================================
    // 6. ALERTAS AVANÇADOS (Slack, Telegram, Email)
    // ==========================================

    public function sendAlert(string $level, string $type, string $title, string $message, array $context = [])
    {
        // Salvar alerta no banco
        $alertId = DB::table('system_alerts')->insertGetId([
            'level' => $level,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'context' => json_encode($context),
            'resolved' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Enviar para canais configurados
        $channels = DB::table('alert_channels')
            ->where('active', true)
            ->get();

        foreach ($channels as $channel) {
            $triggers = json_decode($channel->triggers ?? '[]', true);

            // Verificar se deve enviar para este nível
            if (in_array($level, $triggers) || in_array('all', $triggers)) {
                $this->sendToChannel($channel, $level, $title, $message, $context);
            }
        }

        return $alertId;
    }

    private function sendToChannel(object $channel, string $level, string $title, string $message, array $context)
    {
        $config = json_decode($channel->config, true);
        $emoji = $level === 'critical' ? '🔴' : ($level === 'warning' ? '🟡' : '🔵');

        try {
            switch ($channel->type) {
                case 'slack':
                    Http::post($config['webhook_url'], [
                        'text' => "{$emoji} *[{$level}] {$title}*\n{$message}\nAmbiente: " . app()->environment(),
                    ]);
                    break;

                case 'telegram':
                    Http::post("https://api.telegram.org/bot{$config['bot_token']}/sendMessage", [
                        'chat_id' => $config['chat_id'],
                        'text' => "{$emoji} *{$level}* - {$title}\n\n{$message}\n\nAmbiente: " . app()->environment(),
                        'parse_mode' => 'Markdown',
                    ]);
                    break;

                case 'email':
                    Mail::raw("Nível: {$level}\nTítulo: {$title}\nMensagem: {$message}\nAmbiente: " . app()->environment(), function ($mail) use ($config) {
                        $mail->to($config['email'])
                            ->subject("[Monitoramento] Alerta do Sistema");
                    });
                    break;

                case 'webhook':
                    Http::post($config['webhook_url'], [
                        'level' => $level,
                        'title' => $title,
                        'message' => $message,
                        'context' => $context,
                        'environment' => app()->environment(),
                        'timestamp' => now()->toIso8601String(),
                    ]);
                    break;
            }
        } catch (\Exception $e) {
            Log::error("Erro ao enviar alerta para {$channel->type}: " . $e->getMessage());
        }
    }

    // ==========================================
    // 7. PREVISÃO E TENDÊNCIAS
    // ==========================================

    public function getPredictions(): array
    {
        // Previsão de usuários para os próximos 30 dias
        $userGrowth = $this->calculateGrowthRate(User::class);
        $predictedUsers = User::count() * (1 + ($userGrowth / 100));

        // Previsão de receita
        $currentMonthRevenue = Pedido::where('status', 'concluido')
            ->whereMonth('created_at', now()->month)
            ->sum('valor');
        $revenueGrowth = $this->calculateRevenueGrowth();
        $predictedRevenue = $currentMonthRevenue * (1 + ($revenueGrowth / 100));

        // Previsão de quando vai atingir limites
        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        $diskUsageDailyIncrease = $this->getDiskUsageDailyIncrease();

        $daysUntilFull = $diskUsageDailyIncrease > 0
            ? round($diskFree / $diskUsageDailyIncrease)
            : 999;

        return [
            'users' => [
                'current' => User::count(),
                'predicted_30d' => round($predictedUsers),
                'growth_rate_percent' => round($userGrowth, 2),
            ],
            'revenue' => [
                'current_month' => $currentMonthRevenue,
                'predicted_next_month' => round($predictedRevenue),
                'growth_rate_percent' => round($revenueGrowth, 2),
            ],
            'storage' => [
                'days_until_full' => $daysUntilFull,
                'estimated_date' => $daysUntilFull < 365 ? now()->addDays($daysUntilFull)->toDateString() : '> 1 ano',
            ],
            'alerts_forecast' => [
                'expected_alerts_next_week' => $this->forecastAlerts(),
            ],
        ];
    }

    private function calculateGrowth(string $model, string $type): float
    {
        $lastMonth = now()->subMonth()->startOfMonth();
        $thisMonth = now()->startOfMonth();

        if ($type === 'users') {
            $lastMonthCount = User::where('created_at', '<', $thisMonth)->count();
            $thisMonthCount = User::count();
        } elseif ($type === 'orders') {
            $lastMonthCount = Pedido::where('created_at', '<', $thisMonth)->count();
            $thisMonthCount = Pedido::count();
        } else {
            return 0;
        }

        return $lastMonthCount > 0 ? round((($thisMonthCount - $lastMonthCount) / $lastMonthCount) * 100, 2) : 0;
    }

    private function calculateRevenueGrowth(): float
    {
        $lastMonth = now()->subMonth();
        $lastMonthRevenue = Pedido::where('status', 'concluido')
            ->whereYear('created_at', $lastMonth->year)
            ->whereMonth('created_at', $lastMonth->month)
            ->sum('valor');

        $thisMonthRevenue = Pedido::where('status', 'concluido')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('valor');

        return $lastMonthRevenue > 0 ? round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 2) : 0;
    }

    private function calculateGrowthRate(string $model): float
    {
        $lastMonth = now()->subMonth()->startOfMonth();
        $previousMonth = now()->subMonths(2)->startOfMonth();

        $lastMonthCount = User::whereBetween('created_at', [$lastMonth, now()])->count();
        $previousMonthCount = User::whereBetween('created_at', [$previousMonth, $lastMonth])->count();

        return $previousMonthCount > 0 ? round((($lastMonthCount - $previousMonthCount) / $previousMonthCount) * 100, 2) : 0;
    }

    private function getDiskUsageDailyIncrease(): float
    {
        $yesterday = DB::table('daily_metrics')
            ->where('date', now()->subDay()->toDateString())
            ->value('total_requests');

        $today = DB::table('daily_metrics')
            ->where('date', now()->toDateString())
            ->value('total_requests');

        // Estimativa: cada request usa aproximadamente 1KB de logs
        $dailyRequestIncrease = ($today ?? 0) - ($yesterday ?? 0);

        return max(0, $dailyRequestIncrease * 1024); // bytes por dia
    }

    private function forecastAlerts(): int
    {
        $lastWeekAlerts = DB::table('system_alerts')
            ->where('created_at', '>=', now()->subWeek())
            ->count();

        $weekBefore = DB::table('system_alerts')
            ->whereBetween('created_at', [now()->subWeeks(2), now()->subWeek()])
            ->count();

        $trend = $lastWeekAlerts - $weekBefore;

        return max(0, $lastWeekAlerts + $trend);
    }

    // ==========================================
    // 8. DASHBOARD COMPLETO
    // ==========================================

    public function getCompleteDashboard(): array
    {
        return [
            'health' => $this->getHealthStatus(),
            'performance' => $this->getPerformanceMetrics('hour'),
            'infrastructure' => $this->getInfrastructureMetrics(),
            'business' => $this->getAdvancedBusinessMetrics(),
            'security' => $this->getSecurityMetrics(),
            'external_services' => $this->checkExternalServices(),
            'slow_queries' => $this->getSlowQueriesAnalytics(24),
            'frontend' => $this->getFrontendMetrics(),
            'predictions' => $this->getPredictions(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    private function getHealthStatus(): array
    {
        $startTime = microtime(true);

        $checks = [
            'database' => $this->checkDatabaseConnection(),
            'cache' => $this->checkCacheConnection(),
            'storage' => $this->checkStorage(),
        ];

        $status = 'healthy';
        foreach ($checks as $check) {
            if ($check['status'] !== 'healthy') {
                $status = 'degraded';
                break;
            }
        }

        return [
            'status' => $status,
            'checks' => $checks,
            'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
        ];
    }

    private function checkDatabaseConnection(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $time = round((microtime(true) - $start) * 1000, 2);

            return ['status' => 'healthy', 'response_time_ms' => $time];
        } catch (\Exception $e) {
            return ['status' => 'down', 'error' => $e->getMessage()];
        }
    }

    private function checkCacheConnection(): array
    {
        try {
            $start = microtime(true);
            Cache::put('health_check', true, 1);
            $value = Cache::get('health_check');
            Cache::forget('health_check');
            $time = round((microtime(true) - $start) * 1000, 2);

            return ['status' => $value === true ? 'healthy' : 'degraded', 'response_time_ms' => $time];
        } catch (\Exception $e) {
            return ['status' => 'down', 'error' => $e->getMessage()];
        }
    }

    private function checkStorage(): array
    {
        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        $usagePercent = (($diskTotal - $diskFree) / $diskTotal) * 100;

        $status = 'healthy';
        if ($usagePercent > 90) {
            $status = 'critical';
        } elseif ($usagePercent > 80) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'free_gb' => round($diskFree / 1024 / 1024 / 1024, 2),
            'used_gb' => round(($diskTotal - $diskFree) / 1024 / 1024 / 1024, 2),
            'total_gb' => round($diskTotal / 1024 / 1024 / 1024, 2),
            'usage_percent' => round($usagePercent, 2),
        ];
    }

    private function getPerformanceMetrics(string $period): array
    {
        $minutes = match ($period) {
            'hour' => 60,
            'day' => 1440,
            'week' => 10080,
            default => 60,
        };

        $since = now()->subMinutes($minutes);

        $stats = DB::table('api_requests')
            ->where('created_at', '>=', $since)
            ->select([
                DB::raw('AVG(response_time_ms) as avg_response_time'),
                DB::raw('COUNT(*) as total_requests'),
                DB::raw('SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as error_count'),
            ])
            ->first();

        return [
            'period' => $period,
            'avg_response_time' => round($stats->avg_response_time ?? 0, 2),
            'requests_per_minute' => round(($stats->total_requests ?? 0) / ($minutes / 60), 2),
            'error_rate' => round((($stats->error_count ?? 0) / max(1, $stats->total_requests ?? 1)) * 100, 2),
        ];
    }

    private function getInfrastructureMetrics(): array
    {
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];

        return [
            'cpu' => [
                'load_1min' => round($load[0], 2),
                'load_5min' => round($load[1], 2),
                'load_15min' => round($load[2], 2),
            ],
            'memory' => [
                'php_used_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'php_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'php_limit_mb' => $this->getMemoryLimit(),
            ],
        ];
    }

    private function getMemoryLimit(): float
    {
        $limit = ini_get('memory_limit');
        if ($limit == -1) return 0;

        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;

        switch ($unit) {
            case 'g':
                return $value * 1024;
            case 'm':
                return $value;
            case 'k':
                return $value / 1024;
            default:
                return $value;
        }
    }
}
