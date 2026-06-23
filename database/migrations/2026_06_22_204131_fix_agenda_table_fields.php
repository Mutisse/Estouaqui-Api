<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('agenda', function (Blueprint $table) {
            // Corrigir tipos dos campos
            $table->time('horario_inicio')->change();
            $table->time('horario_fim')->change();

            // Adicionar índices
            $table->index(['prestador_id', 'data', 'horario_inicio']);
            $table->index(['prestador_id', 'bloqueado']);
        });
    }

    public function down()
    {
        Schema::table('agenda', function (Blueprint $table) {
            $table->dropIndex(['prestador_id', 'data', 'horario_inicio']);
            $table->dropIndex(['prestador_id', 'bloqueado']);
        });
    }
};
