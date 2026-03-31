<?php
// database/migrations/2024_03_30_000004_create_horarios_padrao_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horarios_padrao', function (Blueprint $table) {
            $table->id();
            $table->time('horario');
            $table->string('label', 20);
            $table->integer('ordem');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horarios_padrao');
    }
};
