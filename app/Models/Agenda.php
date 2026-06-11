<?php
// app/Models/Agenda.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agenda extends Model
{
    protected $table = 'agenda';

    protected $fillable = [
        'prestador_id',
        'data',
        'horario_inicio',
        'horario_fim',
        'bloqueado',
        'observacao',
    ];

    protected $casts = [
        'data' => 'date',
        'bloqueado' => 'boolean',
    ];

    public function prestador()
    {
        return $this->belongsTo(User::class, 'prestador_id');
    }
}
