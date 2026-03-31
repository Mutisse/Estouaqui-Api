<?php

namespace Database\Seeders;

use App\Models\Promocao;
use Illuminate\Database\Seeder;

class PromocaoSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ USAR O MODEL CORRETO
        Promocao::create([
            'codigo' => 'BEMVINDO20',
            'titulo' => '20% OFF',
            'descricao' => 'No primeiro serviço',
            'tipo_desconto' => 'percentual',
            'valor_desconto' => 20,
            'valor_minimo' => 0,
            'validade' => now()->addDays(30),
            'ativo' => true,
        ]);

        Promocao::create([
            'codigo' => 'INDICAR',
            'titulo' => 'Ganhe 500 MZN',
            'descricao' => 'Indique um amigo e ganhe bónus',
            'tipo_desconto' => 'fixo',
            'valor_desconto' => 500,
            'valor_minimo' => 0,
            'validade' => now()->addDays(60),
            'ativo' => true,
        ]);

        Promocao::create([
            'codigo' => 'FRETEGRATIS',
            'titulo' => 'Frete grátis',
            'descricao' => 'Para serviços acima de 1000 MZN',
            'tipo_desconto' => 'fixo',
            'valor_desconto' => 0,
            'valor_minimo' => 1000,
            'validade' => now()->addDays(15),
            'ativo' => true,
        ]);
    }
}
