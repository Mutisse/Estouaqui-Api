<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('mensagens_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->onDelete('cascade');
            $table->foreignId('remetente_id')->constrained('users')->onDelete('cascade');
            $table->enum('remetente_tipo', ['cliente', 'prestador', 'admin']);
            $table->string('remetente_nome');
            $table->text('mensagem');
            $table->json('anexos')->nullable();
            $table->boolean('lida')->default(false);
            $table->timestamp('lida_em')->nullable();
            $table->timestamps();

            $table->index('ticket_id');
            $table->index('created_at');
            $table->index('lida');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mensagens_tickets');
    }
};
