<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transacao extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero',
        'user_id',
        'pedido_id',
        'tipo',
        'status',
        'valor',
        'descricao',
        'metodo',
        'detalhes',
        'data_confirmacao',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_confirmacao' => 'datetime',
    ];

    // Gerar número único para transação
    public static function boot()
    {
        parent::boot();

        static::creating(function ($transacao) {
            $transacao->numero = 'TRX-' . strtoupper(uniqid());
        });
    }

    // Relacionamentos
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }
}
