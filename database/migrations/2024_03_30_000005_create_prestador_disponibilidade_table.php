<?php
// database/migrations/2024_03_30_000005_create_prestador_disponibilidade_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestador_disponibilidade', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestador_id')->constrained('users')->onDelete('cascade');
            $table->json('configuracoes'); // configurações gerais
            $table->json('horarios_padrao'); // horários padrão
            $table->json('intervalos_padrao'); // intervalos padrão (almoço, etc)
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestador_disponibilidade');
    }
};
