<?php
// database/migrations/2026_06_14_000000_fix_tickets_cliente_prestador_nullable.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tickets', function (Blueprint $table) {
            // ✅ Permitir NULL para cliente_id (quando for prestador)
            $table->foreignId('cliente_id')->nullable()->change();

            // ✅ Permitir NULL para prestador_id (quando for cliente)
            $table->foreignId('prestador_id')->nullable()->change();
        });

        // ✅ Corrigir também as foreign keys para ON DELETE SET NULL
        Schema::table('tickets', function (Blueprint $table) {
            // Remover constraints existentes (se houver)
            $table->dropForeign(['cliente_id']);
            $table->dropForeign(['prestador_id']);

            // Recriar com ON DELETE SET NULL
            $table->foreign('cliente_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('prestador_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Remover constraints
            $table->dropForeign(['cliente_id']);
            $table->dropForeign(['prestador_id']);

            // Recriar como NOT NULL
            $table->foreignId('cliente_id')->nullable(false)->change();
            $table->foreignId('prestador_id')->nullable(false)->change();

            // Recriar constraints originais
            $table->foreign('cliente_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('prestador_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
