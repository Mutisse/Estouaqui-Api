<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('propostas', function (Blueprint $table) {
            // Adicionar campos faltantes
            $table->foreignId('servico_id')->nullable()->after('prestador_id')->constrained()->nullOnDelete();
            $table->integer('duracao')->nullable()->after('valor')->comment('Duração em minutos');
            $table->string('endereco')->nullable()->after('duracao');
            $table->timestamp('expira_em')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('propostas', function (Blueprint $table) {
            $table->dropForeign(['servico_id']);
            $table->dropColumn(['servico_id', 'duracao', 'endereco', 'expira_em']);
        });
    }
};
