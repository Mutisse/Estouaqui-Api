<?php
// database/seeders/PromocaoSeeder.php

namespace Database\Seeders;

use App\Models\Promocao;
use Illuminate\Database\Seeder;

class PromocaoSeeder extends Seeder
{
    public function run(): void
    {
        Promocao::create([
            'titulo' => 'Bónus de Boas-Vindas',
            'descricao' => 'Ganhe 500 MZN no seu primeiro serviço',
            'codigo' => 'BEMVINDO500',
            'tipo_desconto' => 'fixo',
            'valor_desconto' => 500,
            'valor_minimo' => 1000,
            'uso_maximo' => 100,
            'validade_inicio' => now(),
            'validade_fim' => now()->addMonths(3),
            'ativo' => true,
        ]);

        Promocao::create([
            'titulo' => 'Desconto de 10%',
            'descricao' => '10% de desconto em qualquer serviço',
            'codigo' => 'ESTAQUI10',
            'tipo_desconto' => 'percentual',
            'valor_desconto' => 10,
            'valor_minimo' => 500,
            'uso_maximo' => null,
            'validade_inicio' => now(),
            'validade_fim' => now()->addMonths(1),
            'ativo' => true,
        ]);
    }
}
