<?php
// app/Models/IntervaloAgenda.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IntervaloAgenda extends Model
{
    use HasFactory;

    protected $table = 'intervalos_agenda';

    protected $fillable = [
        'prestador_id',
        'dias',
        'inicio',
        'fim',
        'descricao',
        'ativo',
    ];

    protected $casts = [
        'dias' => 'array',
        'ativo' => 'boolean',
    ];

    // ==========================================
    // 🔥 RELACIONAMENTOS
    // ==========================================

    public function prestador()
    {
        return $this->belongsTo(User::class, 'prestador_id');
    }

    // ==========================================
    // 🔥 SCOPES
    // ==========================================

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    public function scopeInativos($query)
    {
        return $query->where('ativo', false);
    }

    // ==========================================
    // 🔥 MÉTODOS AUXILIARES
    // ==========================================

    /**
     * Verificar se um dia está no intervalo
     */
    public function temDia($dia)
    {
        $diasMap = [
            'segunda' => 'segunda',
            'terca' => 'terca',
            'quarta' => 'quarta',
            'quinta' => 'quinta',
            'sexta' => 'sexta',
            'sabado' => 'sabado',
            'domingo' => 'domingo',
        ];

        $diaNormalizado = $diasMap[$dia] ?? $dia;
        return in_array($diaNormalizado, $this->dias);
    }

    /**
     * Gerar bloqueios para um período específico
     */
    public function gerarBloqueios($dataInicio, $dataFim)
    {
        $dataAtual = \Carbon\Carbon::parse($dataInicio);
        $dataFim = \Carbon\Carbon::parse($dataFim);
        $diasSemana = [
            'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo'
        ];

        $bloqueiosCriados = 0;

        while ($dataAtual <= $dataFim) {
            $diaSemana = $diasSemana[$dataAtual->dayOfWeek - 1];

            if ($this->temDia($diaSemana) && $this->ativo) {
                $dataStr = $dataAtual->format('Y-m-d');
                $horaInicio = $this->inicio;
                $horaFim = $this->fim;

                // Verificar se já existe bloqueio
                $existe = Agenda::where('prestador_id', $this->prestador_id)
                    ->where('data', $dataStr)
                    ->where('horario_inicio', $horaInicio)
                    ->where('horario_fim', $horaFim)
                    ->exists();

                if (!$existe) {
                    Agenda::create([
                        'prestador_id' => $this->prestador_id,
                        'data' => $dataStr,
                        'horario_inicio' => $horaInicio,
                        'horario_fim' => $horaFim,
                        'bloqueado' => true,
                        'observacao' => 'Bloqueio recorrente: ' . ($this->descricao ?? 'Intervalo'),
                    ]);
                    $bloqueiosCriados++;
                }
            }

            $dataAtual->addDay();
        }

        return $bloqueiosCriados;
    }
}
