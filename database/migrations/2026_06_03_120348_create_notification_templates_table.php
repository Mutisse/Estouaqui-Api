<?php
// database/migrations/xxxx_create_notification_templates_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('evento')->unique(); // ex: 'pedido.criado', 'pedido.aceito'
            $table->string('titulo'); // Template do título
            $table->text('mensagem'); // Template da mensagem
            $table->string('tipo')->default('sistema'); // pedido, favorito, sistema, etc
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
