<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('prestador_id')->constrained('users')->onDelete('cascade');
            $table->text('ultima_mensagem')->nullable();
            $table->timestamp('ultima_mensagem_data')->nullable();
            $table->timestamps();

            $table->unique(['cliente_id', 'prestador_id']);
            $table->index('prestador_id');
            $table->index('cliente_id');
            $table->index('ultima_mensagem_data');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
