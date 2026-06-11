<?php
// database/seeders/CategoriaSeeder.php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            ['nome' => 'Canalização', 'slug' => 'canalizacao', 'icone' => 'plumbing', 'cor' => '#3B82F6', 'ordem' => 1],
            ['nome' => 'Eletricidade', 'slug' => 'eletricidade', 'icone' => 'electrical_services', 'cor' => '#F59E0B', 'ordem' => 2],
            ['nome' => 'Pintura', 'slug' => 'pintura', 'icone' => 'brush', 'cor' => '#10B981', 'ordem' => 3],
            ['nome' => 'Limpeza', 'slug' => 'limpeza', 'icone' => 'cleaning_services', 'cor' => '#8B5CF6', 'ordem' => 4],
            ['nome' => 'Jardinagem', 'slug' => 'jardinagem', 'icone' => 'grass', 'cor' => '#22C55E', 'ordem' => 5],
            ['nome' => 'Reparos Gerais', 'slug' => 'reparos', 'icone' => 'handyman', 'cor' => '#EF4444', 'ordem' => 6],
        ];

        foreach ($categorias as $categoria) {
            Categoria::create($categoria);
        }
    }
}
