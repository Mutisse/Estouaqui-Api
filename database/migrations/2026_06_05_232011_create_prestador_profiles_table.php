// database/migrations/2026_06_06_000008_create_prestador_profiles_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestador_profiles', function (Blueprint $table) {
            $table->id();

            // Relacionamento com user
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade')
                  ->unique();

            // ==========================================
            // CAMPOS ESPECÍFICOS DO PRESTADOR
            // (NÃO duplicam os que já existem em users)
            // ==========================================

            // Disponibilidade (JSON com horários)
            $table->json('disponibilidade')->nullable();

            // Portfólio (JSON com fotos)
            $table->json('portfolio')->nullable();

            // Documentos
            $table->string('documento')->nullable();
            $table->enum('status_documento', ['pendente', 'aprovado', 'rejeitado'])
                  ->default('pendente');
            $table->timestamp('documento_verificado_em')->nullable();

            // Configurações específicas do prestador
            $table->json('configuracoes_prestador')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestador_profiles');
    }
};
