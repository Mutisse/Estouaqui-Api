<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('logs_sistema', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_nome')->nullable();
            $table->string('user_email')->nullable();
            $table->string('acao');
            $table->string('nivel')->default('info');
            $table->text('descricao');
            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('dados_anteriores')->nullable();
            $table->json('dados_novos')->nullable();
            $table->string('modulo')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('acao');
            $table->index('nivel');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('logs_sistema');
    }
};
