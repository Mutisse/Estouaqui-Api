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
        'cor',
        'descricao',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    // Uma categoria tem muitos serviços
    public function servicos()
    {
        return $this->hasMany(Servico::class, 'categoria_id');
    }

    // Uma categoria tem muitos prestadores (através da tabela pivot)
    public function prestadores()
    {
        return $this->belongsToMany(User::class, 'prestador_categorias', 'categoria_id', 'user_id');
    }

    // Uma categoria tem muitos pedidos
    public function pedidos()
    {
        return $this->hasMany(Pedido::class, 'categoria_id');
    }
}
