<?php
// database/migrations/2026_06_11_000001_create_log_templates_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_templates', function (Blueprint $table) {
            $table->id();
            $table->string('evento', 100)->unique();
            $table->string('titulo', 255);
            $table->text('mensagem');
            $table->string('nivel', 20)->default('info');
            $table->string('modulo', 100);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index('evento');
            $table->index('nivel');
            $table->index('modulo');
            $table->index('ativo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_templates');
    }
};
