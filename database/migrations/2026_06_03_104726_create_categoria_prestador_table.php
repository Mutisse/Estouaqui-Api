<?php
// database/migrations/xxxx_create_categoria_prestador_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categoria_prestador', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['categoria_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categoria_prestador');
    }
};
