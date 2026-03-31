<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HorarioPadrao;

class HorariosPadraoSeeder extends Seeder
{
    public function run(): void
    {
        $horarios = [
            ['horario' => '08:00', 'label' => '08:00', 'ordem' => 1],
            ['horario' => '09:00', 'label' => '09:00', 'ordem' => 2],
            ['horario' => '10:00', 'label' => '10:00', 'ordem' => 3],
            ['horario' => '11:00', 'label' => '11:00', 'ordem' => 4],
            ['horario' => '12:00', 'label' => '12:00', 'ordem' => 5],
            ['horario' => '13:00', 'label' => '13:00', 'ordem' => 6],
            ['horario' => '14:00', 'label' => '14:00', 'ordem' => 7],
            ['horario' => '15:00', 'label' => '15:00', 'ordem' => 8],
            ['horario' => '16:00', 'label' => '16:00', 'ordem' => 9],
            ['horario' => '17:00', 'label' => '17:00', 'ordem' => 10],
            ['horario' => '18:00', 'label' => '18:00', 'ordem' => 11],
        ];

        foreach ($horarios as $horario) {
            HorarioPadrao::create($horario);
        }
    }
}
