<?php
// database/migrations/2026_04_16_000001_add_indexes_to_prestadores.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Verificar e adicionar índices na tabela users
        Schema::table('users', function (Blueprint $table) {
            // Verificar se o índice já existe antes de criar
            if (!$this->hasIndex('users', 'idx_users_tipo_ativo')) {
                $table->index(['tipo', 'ativo'], 'idx_users_tipo_ativo');
            }
            if (!$this->hasIndex('users', 'idx_users_media_avaliacao')) {
                $table->index('media_avaliacao', 'idx_users_media_avaliacao');
            }
        });

        // Verificar e adicionar índices na tabela pedidos
        Schema::table('pedidos', function (Blueprint $table) {
            if (!$this->hasIndex('pedidos', 'idx_pedidos_prestador_id')) {
                $table->index('prestador_id', 'idx_pedidos_prestador_id');
            }
            if (!$this->hasIndex('pedidos', 'idx_pedidos_status')) {
                $table->index('status', 'idx_pedidos_status');
            }
            if (!$this->hasIndex('pedidos', 'idx_pedidos_prestador_status')) {
                $table->index(['prestador_id', 'status'], 'idx_pedidos_prestador_status');
            }
            if (!$this->hasIndex('pedidos', 'idx_pedidos_data')) {
                $table->index('data', 'idx_pedidos_data');
            }
        });

        // Verificar e adicionar índices na tabela servicos
        Schema::table('servicos', function (Blueprint $table) {
            if (!$this->hasIndex('servicos', 'idx_servicos_prestador_id')) {
                $table->index('prestador_id', 'idx_servicos_prestador_id');
            }
            if (!$this->hasIndex('servicos', 'idx_servicos_prestador_ativo')) {
                $table->index(['prestador_id', 'ativo'], 'idx_servicos_prestador_ativo');
            }
        });

        // Verificar e adicionar índices na tabela avaliacoes
        Schema::table('avaliacoes', function (Blueprint $table) {
            if (!$this->hasIndex('avaliacoes', 'idx_avaliacoes_prestador_id')) {
                $table->index('prestador_id', 'idx_avaliacoes_prestador_id');
            }
            if (!$this->hasIndex('avaliacoes', 'idx_avaliacoes_created_at')) {
                $table->index('created_at', 'idx_avaliacoes_created_at');
            }
        });
    }

    /**
     * Verificar se um índice existe na tabela
     */
    private function hasIndex($table, $indexName)
    {
        try {
            // Para MySQL/MariaDB
            $result = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            return count($result) > 0;
        } catch (\Exception $e) {
            // Para TiDB ou outros bancos
            return false;
        }
    }

    public function down()
    {
        // Remover índices se existirem
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_users_tipo_ativo');
            $table->dropIndexIfExists('idx_users_media_avaliacao');
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_pedidos_prestador_id');
            $table->dropIndexIfExists('idx_pedidos_status');
            $table->dropIndexIfExists('idx_pedidos_prestador_status');
            $table->dropIndexIfExists('idx_pedidos_data');
        });

        Schema::table('servicos', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_servicos_prestador_id');
            $table->dropIndexIfExists('idx_servicos_prestador_ativo');
        });

        Schema::table('avaliacoes', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_avaliacoes_prestador_id');
            $table->dropIndexIfExists('idx_avaliacoes_created_at');
        });
    }
};
