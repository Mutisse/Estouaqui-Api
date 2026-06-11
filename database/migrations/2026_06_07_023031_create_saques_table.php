<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // migration para saques
        Schema::create('saques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestador_id')->constrained('users')->onDelete('cascade');
            $table->decimal('valor', 10, 2);
            $table->enum('metodo', ['mbway', 'transferencia_bancaria', 'mpesa']);
            $table->json('dados_pagamento');
            $table->enum('status', ['pendente', 'aprovado', 'rejeitado', 'concluido'])->default('pendente');
            $table->timestamp('solicitado_em')->nullable();
            $table->timestamp('processado_em')->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index('prestador_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saques');
    }
};
