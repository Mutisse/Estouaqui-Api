<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Avaliacao extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'prestador_id',
        'pedido_id',
        'nota',
        'comentario',
        'categorias',
        'recomenda',
    ];

    protected $casts = [
        'categorias' => 'array',
        'recomenda' => 'boolean',
    ];

    public function cliente()
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function prestador()
    {
        return $this->belongsTo(User::class, 'prestador_id');
    }

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }
}
