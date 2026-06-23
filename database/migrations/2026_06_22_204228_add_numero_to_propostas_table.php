<?php
// database/migrations/2026_06_22_204228_add_numero_to_propostas_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 🔥 ADICIONAR CAMPO numero (se não existir)
        if (!Schema::hasColumn('propostas', 'numero')) {
            Schema::table('propostas', function (Blueprint $table) {
                $table->string('numero')->nullable()->after('id');
            });
        }

        // 🔥 ADICIONAR ÍNDICES (verificando se não existem)
        try {
            // Verificar se o índice composto já existe
            $result = DB::select("SHOW INDEX FROM propostas WHERE Key_name = 'propostas_pedido_prestador_idx'");
            if (empty($result)) {
                Schema::table('propostas', function (Blueprint $table) {
                    $table->index(['pedido_id', 'prestador_id'], 'propostas_pedido_prestador_idx');
                });
            }
        } catch (\Exception $e) {
            // Índice já existe ou erro
        }

        try {
            // Verificar se o índice composto já existe
            $result = DB::select("SHOW INDEX FROM propostas WHERE Key_name = 'propostas_status_created_idx'");
            if (empty($result)) {
                Schema::table('propostas', function (Blueprint $table) {
                    $table->index(['status', 'created_at'], 'propostas_status_created_idx');
                });
            }
        } catch (\Exception $e) {
            // Índice já existe ou erro
        }
    }

    public function down()
    {
        Schema::table('propostas', function (Blueprint $table) {
            if (Schema::hasColumn('propostas', 'numero')) {
                $table->dropColumn('numero');
            }

            try {
                $table->dropIndex('propostas_pedido_prestador_idx');
            } catch (\Exception $e) {
                // Índice não existe
            }

            try {
                $table->dropIndex('propostas_status_created_idx');
            } catch (\Exception $e) {
                // Índice não existe
            }
        });
    }
};
