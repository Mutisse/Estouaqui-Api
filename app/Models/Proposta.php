<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Proposta extends Model
{
    protected $fillable = [
        'pedido_id',
        'prestador_id',
        'servico_id',
        'valor',
        'duracao',
        'endereco',
        'mensagem',
        'status',
        'expira_em',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'duracao' => 'integer',
        'expira_em' => 'datetime',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function prestador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prestador_id');
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class);
    }
}
