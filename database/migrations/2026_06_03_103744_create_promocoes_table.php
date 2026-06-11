<?php
// database/migrations/2026_06_03_103744_create_promocoes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promocoes', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('descricao');
            $table->string('codigo')->unique();
            $table->enum('tipo_desconto', ['percentual', 'fixo'])->default('percentual');
            $table->decimal('valor_desconto', 10, 2);
            $table->decimal('valor_minimo', 10, 2)->default(0);
            $table->integer('uso_maximo')->nullable();
            $table->integer('uso_atual')->default(0);
            $table->date('validade_inicio');
            $table->date('validade_fim');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            // Índices para performance
            $table->index('codigo');
            $table->index('ativo');
            $table->index(['validade_inicio', 'validade_fim']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promocoes');
    }
};
