<?php
// app/Models/IntervaloAgenda.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntervaloAgenda extends Model
{
    protected $table = 'intervalos_agenda';

    protected $fillable = [
        'prestador_id',
        'dias',
        'inicio',
        'fim',
        'descricao',
        'ativo'
    ];

    protected $casts = [
        'dias' => 'array',
        'ativo' => 'boolean',
    ];

    public function prestador()
    {
        return $this->belongsTo(User::class, 'prestador_id');
    }
}
