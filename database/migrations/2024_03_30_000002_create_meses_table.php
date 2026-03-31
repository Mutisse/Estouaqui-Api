<?php
// database/migrations/2024_03_30_000002_create_meses_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meses', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 50);
            $table->string('nome_curto', 10);
            $table->integer('numero');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meses');
    }
};
