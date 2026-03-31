<?php
// app/Models/PrestadorIntervalo.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrestadorIntervalo extends Model
{
    use HasFactory;

    protected $table = 'prestador_intervalos';

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
        'ativo' => 'boolean'
    ];

    public function prestador()
    {
        return $this->belongsTo(User::class, 'prestador_id');
    }
}
