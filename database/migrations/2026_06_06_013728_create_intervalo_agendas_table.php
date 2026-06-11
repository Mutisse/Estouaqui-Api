<?php
// database/migrations/2026_06_06_000010_create_intervalos_agenda_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intervalos_agenda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestador_id')->constrained('users')->onDelete('cascade');
            $table->json('dias'); // ["SEGUNDA", "TERCA", ...]
            $table->string('inicio'); // "08:00"
            $table->string('fim'); // "17:00"
            $table->string('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            // Índices para performance
            $table->index('prestador_id');
            $table->index('ativo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intervalos_agenda');
    }
};
