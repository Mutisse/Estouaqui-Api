<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promocoes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->enum('tipo_desconto', ['percentual', 'fixo'])->default('percentual');
            $table->decimal('valor_desconto', 10, 2);
            $table->decimal('valor_minimo', 10, 2)->default(0);
            $table->date('validade');
            $table->boolean('ativo')->default(true);
            $table->string('imagem')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('codigo');
            $table->index('ativo');
            $table->index('validade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promocoes');
    }
};
