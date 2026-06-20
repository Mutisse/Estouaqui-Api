<?php
// database/migrations/xxxx_xx_xx_add_status_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // 🔥 ADICIONAR CAMPO STATUS
            $table->enum('status', [
                'ativo',        // Utilizador ativo
                'desativado',   // Utilizador desativado pelo admin
                'bloqueado',    // Utilizador bloqueado
                'pendente',     // Aguardando aprovação
                'reprovado'     // Prestador reprovado
            ])->default('ativo')->after('verificado');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
