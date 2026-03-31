<?php
// app/Models/DiaSemana.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiaSemana extends Model
{
    use HasFactory;

    protected $table = 'dias_semana';

    protected $fillable = [
        'nome',
        'nome_curto',
        'ordem',
        'ativo'
    ];

    protected $casts = [
        'ativo' => 'boolean'
    ];

    public function intervalos()
    {
        return $this->belongsToMany(PrestadorIntervalo::class, 'prestador_intervalo_dias', 'dia_id', 'intervalo_id');
    }
}
