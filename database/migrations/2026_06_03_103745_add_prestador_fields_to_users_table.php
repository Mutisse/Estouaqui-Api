<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Só adiciona se a coluna NÃO existir
            if (!Schema::hasColumn('users', 'profissao')) {
                $table->string('profissao')->nullable();
            }
            if (!Schema::hasColumn('users', 'sobre')) {
                $table->text('sobre')->nullable();
            }
            if (!Schema::hasColumn('users', 'media_avaliacao')) {
                $table->decimal('media_avaliacao', 3, 2)->default(0);
            }
            if (!Schema::hasColumn('users', 'total_avaliacoes')) {
                $table->integer('total_avaliacoes')->default(0);
            }
            if (!Schema::hasColumn('users', 'disponivel')) {
                $table->boolean('disponivel')->default(true);
            }
            if (!Schema::hasColumn('users', 'verificado')) {
                $table->boolean('verificado')->default(false);
            }
            if (!Schema::hasColumn('users', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable();
            }
            if (!Schema::hasColumn('users', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable();
            }
            if (!Schema::hasColumn('users', 'raio_atendimento')) {
                $table->integer('raio_atendimento')->default(10);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'profissao',
                'sobre',
                'media_avaliacao',
                'total_avaliacoes',
                'disponivel',
                'verificado',
                'latitude',
                'longitude',
                'raio_atendimento'
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
