<?php
// database/migrations/xxxx_create_pedidos_table.php

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
            $table->foreignId('prestador_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('categoria_id')->constrained('categorias');
            $table->text('descricao');
            $table->string('endereco');
            $table->string('foto')->nullable();
            $table->enum('status', [
                'pendente', 'aceito', 'em_andamento', 'concluido', 'cancelado'
            ])->default('pendente');
            $table->decimal('valor', 10, 2)->nullable();
            $table->timestamp('agendado_para')->nullable();
            $table->timestamp('concluido_em')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('cliente_id');
            $table->index('prestador_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
