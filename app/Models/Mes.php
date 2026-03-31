<?php
// app/Models/Mes.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mes extends Model
{
    use HasFactory;

    protected $table = 'meses';

    protected $fillable = [
        'nome',
        'nome_curto',
        'numero',
        'ativo'
    ];

    protected $casts = [
        'ativo' => 'boolean'
    ];
}
