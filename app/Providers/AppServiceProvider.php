<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Log de queries lentas (> 100ms)
        DB::listen(function ($query) {
            if ($query->time > 100) {
                Log::warning('🐌 QUERY LENTA DETECTADA', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time_ms' => $query->time,
                    'connection' => $query->connectionName,
                ]);
            }
        });

        // Log de todas as queries em desenvolvimento (opcional)
        if (app()->environment('local')) {
            DB::listen(function ($query) {
                Log::info('🔍 QUERY EXECUTADA', [
                    'sql' => $query->sql,
                    'time_ms' => $query->time,
                ]);
            });
        }
    }
}
