<?php
// app/Models/ServicoTipo.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicoTipo extends Model
{
    use HasFactory;

    protected $table = 'servico_tipos';

    protected $fillable = [
        'nome',
        'slug',
        'icone',
        'cor',
        'descricao',
        'ordem',
        'ativo'
    ];

    protected $casts = [
        'ativo' => 'boolean'
    ];

    // Relação com serviços (se necessário)
    public function servicos()
    {
        return $this->hasMany(Servico::class, 'tipo_id');
    }

    // Lista padrão para seed
    public static function getDefaultTipos(): array
    {
        return [
            ['nome' => 'Eletricista', 'slug' => 'eletricista', 'icone' => 'bolt', 'cor' => 'warning', 'ordem' => 1],
            ['nome' => 'Canalizador', 'slug' => 'canalizador', 'icone' => 'water_drop', 'cor' => 'info', 'ordem' => 2],
            ['nome' => 'Pintor', 'slug' => 'pintor', 'icone' => 'brush', 'cor' => 'accent', 'ordem' => 3],
            ['nome' => 'Informático', 'slug' => 'informatico', 'icone' => 'computer', 'cor' => 'purple', 'ordem' => 4],
            ['nome' => 'Cabeleireiro', 'slug' => 'cabeleireiro', 'icone' => 'content_cut', 'cor' => 'secondary', 'ordem' => 5],
            ['nome' => 'Manicure', 'slug' => 'manicure', 'icone' => 'spa', 'cor' => 'pink', 'ordem' => 6],
            ['nome' => 'Limpeza', 'slug' => 'limpeza', 'icone' => 'cleaning_services', 'cor' => 'positive', 'ordem' => 7],
            ['nome' => 'Baby-sitter', 'slug' => 'baby-sitter', 'icone' => 'child_care', 'cor' => 'info', 'ordem' => 8],
            ['nome' => 'Motorista', 'slug' => 'motorista', 'icone' => 'drive_eta', 'cor' => 'primary', 'ordem' => 9],
            ['nome' => 'Costureira', 'slug' => 'costureira', 'icone' => 'sewing', 'cor' => 'secondary', 'ordem' => 10],
            ['nome' => 'Jardinagem', 'slug' => 'jardinagem', 'icone' => 'yard', 'cor' => 'green', 'ordem' => 11],
            ['nome' => 'Fotógrafo', 'slug' => 'fotografo', 'icone' => 'photo_camera', 'cor' => 'accent', 'ordem' => 12],
            ['nome' => 'Personal Trainer', 'slug' => 'personal-trainer', 'icone' => 'fitness_center', 'cor' => 'warning', 'ordem' => 13],
            ['nome' => 'Professor', 'slug' => 'professor', 'icone' => 'school', 'cor' => 'primary', 'ordem' => 14],
        ];
    }
}
