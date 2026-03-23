<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Informações pessoais
            $table->string('nome');
            $table->string('email')->unique();
            $table->string('telefone')->nullable();
            $table->string('endereco')->nullable();
            $table->string('foto')->nullable();

            // Autenticação
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();

            // Perfil do usuário
            $table->enum('tipo', ['cliente', 'admin', 'prestador'])->default('cliente');
            $table->boolean('verificado')->default(false); // Prestador verificado
            $table->boolean('ativo')->default(true); // Conta ativa

            // Campos específicos para prestador
            $table->string('profissao')->nullable();
            $table->text('sobre')->nullable();

            // Avaliações
            $table->decimal('media_avaliacao', 2, 1)->nullable(); // Ex: 4.8
            $table->integer('total_avaliacoes')->default(0);

            // Status de bloqueio
            $table->timestamp('blocked_at')->nullable();

            // Preferências do usuário (JSON)
            $table->json('preferences')->nullable();

            // Soft delete
            $table->softDeletes();

            // Timestamps
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
