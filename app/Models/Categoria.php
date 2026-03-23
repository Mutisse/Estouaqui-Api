<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'slug',
        'icone',
        'descricao',
        'ativo',
    ];

    public function servicos()
    {
        return $this->hasMany(Servico::class);
    }
}
