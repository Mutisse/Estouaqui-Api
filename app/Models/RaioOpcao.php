<?php
// app/Models/RaioOpcao.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RaioOpcao extends Model
{
    use HasFactory;

    protected $table = 'raio_opcoes';

    protected $fillable = [
        'valor',
        'label',
        'ordem',
        'ativo'
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'valor' => 'integer',
        'ordem' => 'integer'
    ];
}
