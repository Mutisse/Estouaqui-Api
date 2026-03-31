<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            // Verificar se a coluna 'icone' não existe antes de adicionar
            if (!Schema::hasColumn('categorias', 'icone')) {
                $table->string('icone', 100)->default('handyman')->after('nome');
            }

            // Verificar se a coluna 'cor' não existe antes de adicionar
            if (!Schema::hasColumn('categorias', 'cor')) {
                $table->string('cor', 50)->default('primary')->after('icone');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            if (Schema::hasColumn('categorias', 'icone')) {
                $table->dropColumn('icone');
            }
            if (Schema::hasColumn('categorias', 'cor')) {
                $table->dropColumn('cor');
            }
        });
    }
};
