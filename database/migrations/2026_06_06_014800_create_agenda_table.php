<?php
// database/migrations/2026_06_06_000015_create_agenda_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('agenda')) {
            Schema::create('agenda', function (Blueprint $table) {
                $table->id();
                $table->foreignId('prestador_id')->constrained('users')->onDelete('cascade');
                $table->date('data');
                $table->string('horario_inicio');
                $table->string('horario_fim');
                $table->boolean('bloqueado')->default(false);
                $table->text('observacao')->nullable();
                $table->timestamps();

                // Evitar duplicação de bloqueio no mesmo horário
                $table->unique(['prestador_id', 'data', 'horario_inicio'], 'unique_agenda_bloqueio');

                // Índices para performance
                $table->index('prestador_id');
                $table->index('data');
                $table->index('bloqueado');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda');
    }
};
