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
        'categoria_id',
        'servico_id',
        'descricao',
        'foto',
        'data',
        'endereco',
        'observacoes',
        'status',
        'valor',
    ];

    protected $casts = [
        'data' => 'datetime',
        'valor' => 'decimal:2',
    ];

    // Pertence a um cliente
    public function cliente()
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    // Pertence a um prestador (pode ser NULL até aceitar)
    public function prestador()
    {
        return $this->belongsTo(User::class, 'prestador_id');
    }

    // Pertence a uma categoria
    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    // Pertence a um serviço (opcional)
    public function servico()
    {
        return $this->belongsTo(Servico::class, 'servico_id');
    }

    // Tem uma avaliação
    public function avaliacao()
    {
        return $this->hasOne(Avaliacao::class, 'pedido_id');
    }

    // Tem muitas propostas
    public function propostas()
    {
        return $this->hasMany(Proposta::class, 'pedido_id');
    }
}
