<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestador_intervalos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestador_id')->constrained('users')->onDelete('cascade');
            $table->json('dias');
            $table->time('inicio');
            $table->time('fim');
            $table->string('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            // Índices para melhor performance
            $table->index('prestador_id');
            $table->index('ativo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestador_intervalos');
    }
};
