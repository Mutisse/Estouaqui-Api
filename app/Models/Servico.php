<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Servico extends Model
{
    use HasFactory;

    protected $fillable = [
        'prestador_id',
        'categoria_id',
        'nome',
        'descricao',
        'preco',
        'duracao',
        'icone',
        'ativo',
    ];

    protected $casts = [
        'preco' => 'decimal:2',
        'duracao' => 'integer',
        'ativo' => 'boolean',
    ];

    // Pertence a um prestador
    public function prestador()
    {
        return $this->belongsTo(User::class, 'prestador_id');
    }

    // Pertence a uma categoria
    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    // Tem muitos pedidos
    public function pedidos()
    {
        return $this->hasMany(Pedido::class, 'servico_id');
    }
}
