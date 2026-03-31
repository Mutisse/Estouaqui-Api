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
        Schema::table('users', function (Blueprint $table) {
            // Adicionar coluna raio (em quilômetros) para prestadores
            if (!Schema::hasColumn('users', 'raio')) {
                $table->integer('raio')->nullable()->default(10)->after('endereco');
            }

            // Adicionar coluna de latitude
            if (!Schema::hasColumn('users', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable()->after('raio');
            }

            // Adicionar coluna de longitude
            if (!Schema::hasColumn('users', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            }

            // ✅ REMOVIDO: ponto geográfico (não suportado no TiDB)
            // O campo 'location' foi removido pois o TiDB não suporta o tipo point
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'raio')) {
                $table->dropColumn('raio');
            }
            if (Schema::hasColumn('users', 'latitude')) {
                $table->dropColumn('latitude');
            }
            if (Schema::hasColumn('users', 'longitude')) {
                $table->dropColumn('longitude');
            }
        });
    }
};
