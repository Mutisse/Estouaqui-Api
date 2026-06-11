<?php
// app/Models/Proposta.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proposta extends Model
{
    use HasFactory;

    protected $table = 'propostas';

    protected $fillable = [
        'pedido_id',
        'prestador_id',
        'valor',
        'mensagem',
        'status'
    ];

    protected $casts = [
        'valor' => 'decimal:2',
    ];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }

    public function prestador()
    {
        return $this->belongsTo(User::class, 'prestador_id');
    }
}
