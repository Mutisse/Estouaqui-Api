<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            // Adicionar categoria (tipo de serviço que cliente quer)
            $table->foreignId('categoria_id')->nullable()->after('cliente_id')->constrained();

            // Adicionar descricao (o que precisa ser feito)
            $table->text('descricao')->nullable()->after('categoria_id');

            // Adicionar foto (opcional)
            $table->string('foto')->nullable()->after('descricao');

            // Garantir que prestador_id pode ser NULL (cliente ainda não escolheu)
            $table->foreignId('prestador_id')->nullable()->change();

            // Garantir que servico_id pode ser NULL (não usa serviço pré-definido)
            $table->foreignId('servico_id')->nullable()->change();

            // Garantir que valor pode ser NULL (só tem valor depois de aceitar proposta)
            $table->decimal('valor', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropForeign(['categoria_id']);
            $table->dropColumn(['categoria_id', 'descricao', 'foto']);

            $table->foreignId('prestador_id')->nullable(false)->change();
            $table->foreignId('servico_id')->nullable(false)->change();
            $table->decimal('valor', 10, 2)->nullable(false)->change();
        });
    }
};
