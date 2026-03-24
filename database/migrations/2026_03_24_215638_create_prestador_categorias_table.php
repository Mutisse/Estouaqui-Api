<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestador_categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('categoria_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Evita duplicação
            $table->unique(['user_id', 'categoria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestador_categorias');
    }
};
