<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CategoriaSeeder::class,
            NotificationTemplateSeeder::class,
            PromocaoSeeder::class,
            DiasSemanaSeeder::class,
            MesesSeeder::class,
            HorariosPadraoSeeder::class,
            PrestadorDisponibilidadeSeeder::class,
            ServicoTipoSeeder::class,
            RaioOpcaoSeeder::class,


        ]);
    }
}
