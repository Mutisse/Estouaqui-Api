<?php
// database/migrations/2026_04_24_000002_add_monitoring_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;  // ✅ ADICIONAR ESTA LINHA

return new class extends Migration
{
    public function up()
    {
        // Tabela de logs de segurança
        Schema::create('security_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event'); // login_failed, brute_force, ip_blocked, file_changed
            $table->string('level'); // info, warning, critical
            $table->string('ip')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('details')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['event', 'created_at']);
            $table->index('ip');
        });

        // Tabela de dependências externas
        Schema::create('external_services', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // payment_gateway, sms_service, email_service, maps_api
            $table->string('status'); // healthy, degraded, down
            $table->integer('response_time_ms')->default(0);
            $table->float('error_rate')->default(0);
            $table->timestamp('last_check_at');
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();

            $table->unique('name');
        });

        // Tabela de alertas avançados
        Schema::create('alert_channels', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // slack, telegram, email, webhook
            $table->string('name');
            $table->json('config'); // webhook_url, chat_id, email, etc
            $table->boolean('active')->default(true);
            $table->json('triggers')->nullable(); // quais eventos enviar
            $table->timestamps();
        });

        // Tabela de slow queries detalhadas
        Schema::create('slow_queries_log', function (Blueprint $table) {
            $table->id();
            $table->text('sql_query');
            $table->json('bindings')->nullable();
            $table->integer('time_ms');
            $table->string('connection');
            $table->string('path')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['time_ms', 'occurred_at']);
        });

        // Tabela de métricas de frontend
        Schema::create('frontend_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->float('lcp'); // Largest Contentful Paint
            $table->float('fid'); // First Input Delay
            $table->float('cls'); // Cumulative Layout Shift
            $table->float('ttfb'); // Time To First Byte
            $table->float('fcp'); // First Contentful Paint
            $table->string('device'); // mobile, desktop
            $table->string('browser');
            $table->timestamps();

            $table->index(['created_at', 'device']);
        });

        // ✅ Usar DB facade (já importado no topo)
        // Inserir serviços externos padrão
        DB::table('external_services')->insert([
            ['name' => 'payment_gateway', 'status' => 'unknown', 'last_check_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'sms_service', 'status' => 'unknown', 'last_check_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'email_service', 'status' => 'unknown', 'last_check_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'maps_api', 'status' => 'unknown', 'last_check_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Inserir canais de alerta padrão
        DB::table('alert_channels')->insert([
            [
                'type' => 'slack',
                'name' => 'Slack Admin Channel',
                'config' => json_encode(['webhook_url' => 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL']),
                'active' => false,
                'triggers' => json_encode(['critical', 'warning']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'email',
                'name' => 'Email Admin',
                'config' => json_encode(['email' => 'admin@estouaqui.com']),
                'active' => true,
                'triggers' => json_encode(['critical']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('security_logs');
        Schema::dropIfExists('external_services');
        Schema::dropIfExists('alert_channels');
        Schema::dropIfExists('slow_queries_log');
        Schema::dropIfExists('frontend_metrics');
    }
};
