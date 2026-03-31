<?php
// database/seeders/RaioOpcaoSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RaioOpcao;

class RaioOpcaoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $opcoes = [
            ['valor' => 2, 'label' => '2 km', 'ordem' => 1],
            ['valor' => 5, 'label' => '5 km', 'ordem' => 2],
            ['valor' => 10, 'label' => '10 km', 'ordem' => 3],
            ['valor' => 15, 'label' => '15 km', 'ordem' => 4],
            ['valor' => 20, 'label' => '20 km', 'ordem' => 5],
            ['valor' => 30, 'label' => '30 km', 'ordem' => 6],
            ['valor' => 50, 'label' => '50 km', 'ordem' => 7],
            ['valor' => 100, 'label' => '100 km', 'ordem' => 8],
        ];

        foreach ($opcoes as $opcao) {
            RaioOpcao::updateOrCreate(
                ['valor' => $opcao['valor']],
                [
                    'label' => $opcao['label'],
                    'ordem' => $opcao['ordem'],
                    'ativo' => true,
                ]
            );
        }

        $this->command->info('✅ RaioOpcoes seeded successfully! Total: ' . count($opcoes));
    }
}
