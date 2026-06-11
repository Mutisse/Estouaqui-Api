<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Saque extends Model
{
    protected $fillable = [
        'prestador_id', 'valor', 'metodo', 'dados_pagamento',
        'status', 'solicitado_em', 'processado_em', 'observacao'
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'dados_pagamento' => 'array',
        'solicitado_em' => 'datetime',
        'processado_em' => 'datetime',
    ];

    public function prestador()
    {
        return $this->belongsTo(User::class, 'prestador_id');
    }
}
