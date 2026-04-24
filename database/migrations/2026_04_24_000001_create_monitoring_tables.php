<?php
// database/migrations/2026_04_24_000001_create_monitoring_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Tabela de requisições para métricas de API
        Schema::create('api_requests', function (Blueprint $table) {
            $table->id();
            $table->string('method', 10);
            $table->string('path', 255);
            $table->integer('status_code');
            $table->integer('response_time_ms');
            $table->ipAddress('ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['path', 'created_at']);
            $table->index(['status_code', 'created_at']);
        });

        // Tabela de métricas diárias para histórico
        Schema::create('daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->integer('total_requests');
            $table->float('avg_response_time');
            $table->float('error_rate');
            $table->integer('total_users');
            $table->integer('new_users');
            $table->integer('total_services');
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->integer('active_prestadores');
            $table->float('avg_rating')->default(0);
            $table->json('additional_metrics')->nullable();
            $table->timestamps();

            $table->unique('date');
        });

        // Tabela de alertas
        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('level'); // critical, warning, info
            $table->string('type'); // storage, database, cache, queue, security, business
            $table->string('title');
            $table->text('message');
            $table->json('context')->nullable();
            $table->boolean('resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['level', 'resolved']);
            $table->index('created_at');
        });

        // Tabela de cache stats histórico
        Schema::create('cache_stats_history', function (Blueprint $table) {
            $table->id();
            $table->timestamp('recorded_at');
            $table->string('driver');
            $table->integer('keys_count')->default(0);
            $table->float('memory_mb')->default(0);
            $table->float('hit_rate')->default(0);
            $table->timestamps();

            $table->index('recorded_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('api_requests');
        Schema::dropIfExists('daily_metrics');
        Schema::dropIfExists('system_alerts');
        Schema::dropIfExists('cache_stats_history');
    }
};
