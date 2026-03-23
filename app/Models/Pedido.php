<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero',
        'cliente_id',
        'prestador_id',
        'servico_id',
        'data',
        'endereco',
        'observacoes',
        'status',
        'valor',
    ];

    protected $casts = [
        'data' => 'datetime',
    ];

    public function cliente()
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function prestador()
    {
        return $this->belongsTo(User::class, 'prestador_id');
    }

    public function servico()
    {
        return $this->belongsTo(Servico::class);
    }

    public function avaliacao()
    {
        return $this->hasOne(Avaliacao::class);
    }
}
