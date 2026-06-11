<?php
// database/migrations/2026_06_11_000001_create_permissao_role_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permissao_role')) {
            Schema::create('permissao_role', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('permissao_id');
                $table->unsignedBigInteger('role_id');
                $table->timestamps();

                $table->foreign('permissao_id')->references('id')->on('permissoes')->onDelete('cascade');
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');

                $table->unique(['permissao_id', 'role_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('permissao_role');
    }
};
