<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracoes', function (Blueprint $table) {
            $table->id();
            $table->string('grupo')->default('geral');
            $table->string('chave')->unique();
            $table->text('valor');
            $table->string('tipo')->default('json');
            $table->text('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['grupo', 'chave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes');
    }
};
