<?php
// database/seeders/MesesSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Mes;

class MesesSeeder extends Seeder
{
    public function run(): void
    {
        $meses = [
            ['nome' => 'Janeiro', 'nome_curto' => 'JAN', 'numero' => 1],
            ['nome' => 'Fevereiro', 'nome_curto' => 'FEV', 'numero' => 2],
            ['nome' => 'Março', 'nome_curto' => 'MAR', 'numero' => 3],
            ['nome' => 'Abril', 'nome_curto' => 'ABR', 'numero' => 4],
            ['nome' => 'Maio', 'nome_curto' => 'MAI', 'numero' => 5],
            ['nome' => 'Junho', 'nome_curto' => 'JUN', 'numero' => 6],
            ['nome' => 'Julho', 'nome_curto' => 'JUL', 'numero' => 7],
            ['nome' => 'Agosto', 'nome_curto' => 'AGO', 'numero' => 8],
            ['nome' => 'Setembro', 'nome_curto' => 'SET', 'numero' => 9],
            ['nome' => 'Outubro', 'nome_curto' => 'OUT', 'numero' => 10],
            ['nome' => 'Novembro', 'nome_curto' => 'NOV', 'numero' => 11],
            ['nome' => 'Dezembro', 'nome_curto' => 'DEZ', 'numero' => 12],
        ];

        foreach ($meses as $mes) {
            Mes::create($mes);
        }
    }
}
