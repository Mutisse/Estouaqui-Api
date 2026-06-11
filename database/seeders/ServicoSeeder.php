<?php
// database/seeders/ServicoSeeder.php

namespace Database\Seeders;

use App\Models\Servico;  // ← Importar o model
use Illuminate\Database\Seeder;

class ServicoSeeder extends Seeder
{
    public function run(): void
    {
        Servico::updateOrCreate(
            ['prestador_id' => 4, 'nome' => 'Reparo de Canalização'],
            [
                'descricao' => 'Reparo de tubulações, torneiras e vasos sanitários',
                'preco' => 1500.00,
                'duracao' => 120,
                'ativo' => true,
            ]
        );

        Servico::updateOrCreate(
            ['prestador_id' => 4, 'nome' => 'Instalação de Torneiras'],
            [
                'descricao' => 'Instalação de torneiras e misturadores',
                'preco' => 800.00,
                'duracao' => 60,
                'ativo' => true,
            ]
        );

        echo "✅ Serviços criados com sucesso!\n";
    }
}
