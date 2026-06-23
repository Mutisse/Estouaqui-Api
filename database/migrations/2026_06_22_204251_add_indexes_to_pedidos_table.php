<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->index(['prestador_id', 'status']);
            $table->index(['cliente_id', 'status']);
            $table->index(['agendado_para']);
        });
    }

    public function down()
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex(['prestador_id', 'status']);
            $table->dropIndex(['cliente_id', 'status']);
            $table->dropIndex(['agendado_para']);
        });
    }
};
