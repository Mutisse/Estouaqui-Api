<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propostas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained()->onDelete('cascade');
            $table->foreignId('prestador_id')->constrained('users')->onDelete('cascade');
            $table->decimal('valor', 10, 2);
            $table->text('mensagem')->nullable();
            $table->enum('status', ['pendente', 'aceita', 'recusada'])->default('pendente');
            $table->timestamps();

            // Índices para pesquisa rápida
            $table->index(['pedido_id', 'status']);
            $table->index(['prestador_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propostas');
    }
};
