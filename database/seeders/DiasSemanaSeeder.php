<?php
// database/seeders/DiasSemanaSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DiaSemana;

class DiasSemanaSeeder extends Seeder
{
    public function run(): void
    {
        $dias = [
            ['nome' => 'Segunda-feira', 'nome_curto' => 'SEG', 'ordem' => 1],
            ['nome' => 'Terça-feira', 'nome_curto' => 'TER', 'ordem' => 2],
            ['nome' => 'Quarta-feira', 'nome_curto' => 'QUA', 'ordem' => 3],
            ['nome' => 'Quinta-feira', 'nome_curto' => 'QUI', 'ordem' => 4],
            ['nome' => 'Sexta-feira', 'nome_curto' => 'SEX', 'ordem' => 5],
            ['nome' => 'Sábado', 'nome_curto' => 'SAB', 'ordem' => 6],
            ['nome' => 'Domingo', 'nome_curto' => 'DOM', 'ordem' => 7],
        ];

        foreach ($dias as $dia) {
            DiaSemana::create($dia);
        }
    }
}
