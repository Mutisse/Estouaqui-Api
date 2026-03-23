<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            ['nome' => 'Eletricista', 'slug' => 'eletricista', 'icone' => 'bolt'],
            ['nome' => 'Canalizador', 'slug' => 'canalizador', 'icone' => 'water_drop'],
            ['nome' => 'Pintor', 'slug' => 'pintor', 'icone' => 'brush'],
            ['nome' => 'Limpeza', 'slug' => 'limpeza', 'icone' => 'cleaning_services'],
            ['nome' => 'Informático', 'slug' => 'informatico', 'icone' => 'computer'],
            ['nome' => 'Cabeleireiro', 'slug' => 'cabeleireiro', 'icone' => 'content_cut'],
            ['nome' => 'Jardinagem', 'slug' => 'jardinagem', 'icone' => 'grass'],
            ['nome' => 'Motorista', 'slug' => 'motorista', 'icone' => 'directions_car'],
            ['nome' => 'Fotógrafo', 'slug' => 'fotografo', 'icone' => 'camera_alt'],
            ['nome' => 'Personal Trainer', 'slug' => 'personal-trainer', 'icone' => 'fitness_center'],
        ];

        foreach ($categorias as $cat) {
            Categoria::create($cat);
        }
    }
}
