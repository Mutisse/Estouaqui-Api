<?php
// app/Models/HorarioPadrao.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HorarioPadrao extends Model
{
    use HasFactory;

    protected $table = 'horarios_padrao';

    protected $fillable = [
        'horario',
        'label',
        'ordem',
        'ativo'
    ];

    protected $casts = [
        'ativo' => 'boolean'
    ];

    // Horários padrão pré-definidos
    public static function getDefaultHorarios(): array
    {
        return [
            '08:00', '09:00', '10:00', '11:00', '12:00',
            '13:00', '14:00', '15:00', '16:00', '17:00', '18:00'
        ];
    }
}
