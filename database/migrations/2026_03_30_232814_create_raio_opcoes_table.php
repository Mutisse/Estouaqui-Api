<?php
// database/migrations/2026_03_31_000004_create_raio_opcoes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raio_opcoes', function (Blueprint $table) {
            $table->id();
            $table->integer('valor'); // valor em km
            $table->string('label', 20);
            $table->integer('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raio_opcoes');
    }
};
