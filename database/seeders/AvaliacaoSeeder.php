<?php
// database/seeders/AvaliacaoSeeder.php

namespace Database\Seeders;

use App\Models\Avaliacao;
use App\Models\User;
use App\Models\Pedido;
use Illuminate\Database\Seeder;

class AvaliacaoSeeder extends Seeder
{
    public function run(): void
    {
        // Buscar um pedido concluído
        $pedido = Pedido::where('status', 'concluido')->first();

        if ($pedido) {
            Avaliacao::updateOrCreate(
                ['pedido_id' => $pedido->id],
                [
                    'cliente_id' => $pedido->cliente_id,
                    'prestador_id' => $pedido->prestador_id,
                    'nota' => 5,
                    'comentario' => 'Excelente profissional, muito competente!',
                    'status' => 'aprovada',
                ]
            );
            echo "✅ Avaliação criada com sucesso para o pedido #{$pedido->id}\n";
        } else {
            echo "⚠️ Nenhum pedido concluído encontrado\n";

            // Criar um pedido de exemplo se não existir
            $cliente = User::where('tipo', 'cliente')->first();
            $prestador = User::where('tipo', 'prestador')->first();

            if ($cliente && $prestador) {
                $pedido = Pedido::create([
                    'numero' => 'PED-' . time(),
                    'cliente_id' => $cliente->id,
                    'prestador_id' => $prestador->id,
                    'categoria_id' => 1,
                    'descricao' => 'Serviço de exemplo',
                    'endereco' => 'Endereço de exemplo',
                    'status' => 'concluido',
                    'valor' => 1000,
                    'concluido_em' => now(),
                ]);

                Avaliacao::create([
                    'pedido_id' => $pedido->id,
                    'cliente_id' => $cliente->id,
                    'prestador_id' => $prestador->id,
                    'nota' => 5,
                    'comentario' => 'Excelente profissional, muito competente!',
                    'status' => 'aprovada',
                ]);
                echo "✅ Pedido e avaliação criados com sucesso!\n";
            }
        }
    }
}
