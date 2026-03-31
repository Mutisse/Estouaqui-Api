<?php
// app/Models/PrestadorDisponibilidade.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrestadorDisponibilidade extends Model
{
    use HasFactory;

    protected $table = 'prestador_disponibilidade';

    protected $fillable = [
        'prestador_id',
        'configuracoes',
        'horarios_padrao',
        'intervalos_padrao',
        'ativo'
    ];

    protected $casts = [
        'configuracoes' => 'array',
        'horarios_padrao' => 'array',
        'intervalos_padrao' => 'array',
        'ativo' => 'boolean'
    ];

    public function prestador()
    {
        return $this->belongsTo(User::class, 'prestador_id');
    }

    // Configurações padrão
    public static function getDefaultConfiguracoes(): array
    {
        return [
            'tempo_minimo_agendamento' => 60, // minutos
            'tempo_entre_servicos' => 15, // minutos
            'notificar_antes' => 30, // minutos
            'aceitar_agendamento_automatico' => true,
            'dias_antecedencia' => 30,
        ];
    }

    // Horários padrão
    public static function getDefaultHorariosPadrao(): array
    {
        return [
            'segunda' => ['08:00', '09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00'],
            'terca' => ['08:00', '09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00'],
            'quarta' => ['08:00', '09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00'],
            'quinta' => ['08:00', '09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00'],
            'sexta' => ['08:00', '09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00'],
            'sabado' => ['08:00', '09:00', '10:00', '11:00'],
            'domingo' => [],
        ];
    }

    // Intervalos padrão
    public static function getDefaultIntervalosPadrao(): array
    {
        return [
            [
                'dias' => ['segunda', 'terca', 'quarta', 'quinta', 'sexta'],
                'inicio' => '12:00',
                'fim' => '14:00',
                'descricao' => 'Horário de almoço'
            ],
            [
                'dias' => ['sabado'],
                'inicio' => '12:00',
                'fim' => '13:00',
                'descricao' => 'Horário de almoço'
            ]
        ];
    }
}
