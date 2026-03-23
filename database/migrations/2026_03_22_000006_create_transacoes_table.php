<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transacoes', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('pedido_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('tipo', ['entrada', 'saida', 'comissao'])->default('entrada');
            $table->enum('status', ['pendente', 'processando', 'concluido', 'cancelado'])->default('pendente');
            $table->decimal('valor', 10, 2);
            $table->string('descricao')->nullable();
            $table->string('metodo', 50)->nullable(); // mpesa, conta_bancaria, etc
            $table->text('detalhes')->nullable();
            $table->timestamp('data_confirmacao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transacoes');
    }
};
