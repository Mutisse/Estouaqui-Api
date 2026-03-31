<?php
// database/migrations/2024_03_30_000001_create_dias_semana_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dias_semana', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 50);
            $table->string('nome_curto', 10);
            $table->integer('ordem');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dias_semana');
    }
};
