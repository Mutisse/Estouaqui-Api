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
        'ativo' => 'boolean',
    ];

    public function prestador()
    {
        return $this->belongsTo(User::class, 'prestador_id');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function pedidos()
    {
        return $this->hasMany(Pedido::class);
    }
}
