<?php
// database/migrations/2024_03_28_000001_create_mensagens_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('remetente_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('destinatario_id')->constrained('users')->onDelete('cascade');
            $table->text('mensagem');
            $table->boolean('lida')->default(false);
            $table->timestamp('lida_em')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // Índices para melhor performance
            $table->index(['remetente_id', 'destinatario_id']);
            $table->index(['destinatario_id', 'lida']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensagens');
    }
};
