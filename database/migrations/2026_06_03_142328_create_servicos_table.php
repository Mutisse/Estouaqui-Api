<?php
// database/migrations/xxxx_create_servicos_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestador_id')->constrained('users')->onDelete('cascade');
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->decimal('preco', 10, 2);
            $table->integer('duracao')->default(60); // em minutos
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index('prestador_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicos');
    }
};
