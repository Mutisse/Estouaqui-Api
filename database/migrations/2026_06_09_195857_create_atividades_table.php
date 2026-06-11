<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('atividades')) {
            Schema::create('atividades', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('descricao');
                $table->enum('tipo', ['login', 'atualizacao', 'criacao', 'exclusao'])->default('atualizacao');
                $table->string('ip', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();

                // Índices para performance
                $table->index('user_id');
                $table->index('tipo');
                $table->index('created_at');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('atividades');
    }
};
