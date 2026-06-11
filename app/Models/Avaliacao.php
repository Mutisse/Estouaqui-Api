<?php
// app/Models/Avaliacao.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Avaliacao extends Model
{
    use HasFactory;

    // ✅ FORÇAR O NOME CORRETO DA TABELA
    protected $table = 'avaliacoes';  // ← ADICIONE ESTA LINHA

    protected $fillable = [
        'cliente_id',
        'prestador_id',
        'pedido_id',
        'nota',
        'comentario',
        'status',
    ];

    protected $casts = [
        'nota' => 'integer',
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
