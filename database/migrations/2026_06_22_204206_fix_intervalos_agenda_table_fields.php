<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('intervalos_agenda', function (Blueprint $table) {
            $table->time('inicio')->change();
            $table->time('fim')->change();
            $table->index(['prestador_id', 'ativo']);
        });
    }

    public function down()
    {
        Schema::table('intervalos_agenda', function (Blueprint $table) {
            $table->dropIndex(['prestador_id', 'ativo']);
        });
    }
};
