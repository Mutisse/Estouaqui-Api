<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proposta extends Model
{
    use HasFactory;

    protected $fillable = [
        'pedido_id',
        'prestador_id',
        'valor',
        'mensagem',
        'status',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relacionamentos
    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function prestador()
    {
        return $this->belongsTo(User::class, 'prestador_id');
    }

    // Scopes
    public function scopePendente($query)
    {
        return $query->where('status', 'pendente');
    }

    public function scopeAceita($query)
    {
        return $query->where('status', 'aceita');
    }

    public function scopeRecusada($query)
    {
        return $query->where('status', 'recusada');
    }
}
