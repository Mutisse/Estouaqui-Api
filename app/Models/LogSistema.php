<?php
// app/Models/LogSistema.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogSistema extends Model
{
    use HasFactory;

    protected $table = 'logs_sistema';

    protected $fillable = [
        'user_id',
        'user_nome',
        'user_email',
        'acao',
        'nivel',
        'descricao',
        'ip',
        'user_agent',
        'modulo',
        'dados_anteriores',
        'dados_novos'
    ];

    protected $casts = [
        'dados_anteriores' => 'array',
        'dados_novos' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
