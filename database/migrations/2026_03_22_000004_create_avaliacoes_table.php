<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avaliacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('prestador_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('pedido_id')->constrained()->onDelete('cascade');
            $table->integer('nota')->min(1)->max(5);
            $table->text('comentario')->nullable();
            $table->json('categorias')->nullable(); // Pontualidade, qualidade, etc
            $table->boolean('recomenda')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avaliacoes');
    }
};
