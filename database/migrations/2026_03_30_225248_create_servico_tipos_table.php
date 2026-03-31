<?php
// database/migrations/2024_03_31_000001_create_servico_tipos_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servico_tipos', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100);
            $table->string('slug', 100)->unique();
            $table->string('icone', 50)->default('handyman');
            $table->string('cor', 20)->default('primary');
            $table->string('descricao')->nullable();
            $table->integer('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servico_tipos');
    }
};
