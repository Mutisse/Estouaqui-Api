<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->foreignId('cliente_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('prestador_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('servico_id')->nullable()->constrained()->onDelete('set null');
            $table->dateTime('data');
            $table->string('endereco');
            $table->text('observacoes')->nullable();
            $table->enum('status', [
                'pendente',      // Aguardando resposta
                'aceito',        // Prestador aceitou
                'em_andamento',  // Em execução
                'concluido',     // Finalizado
                'cancelado'      // Cancelado
            ])->default('pendente');
            $table->decimal('valor', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
