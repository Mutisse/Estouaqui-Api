<?php
// app/Models/IntervaloAgenda.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

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

    protected $attributes = [
        'ativo' => true,
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

    /**
     * 🔥 Scope para buscar intervalos que afetam um dia específico
     */
    public function scopeParaDia($query, $diaDaSemana)
    {
        $diasMap = [
            1 => 'segunda',
            2 => 'terca',
            3 => 'quarta',
            4 => 'quinta',
            5 => 'sexta',
            6 => 'sabado',
            0 => 'domingo',
        ];

        $dia = $diasMap[$diaDaSemana] ?? $diaDaSemana;

        return $query->where('ativo', true)
            ->whereJsonContains('dias', $dia);
    }

    // ==========================================
    // 🔥 MÉTODOS AUXILIARES
    // ==========================================

    /**
     * Verificar se um dia está no intervalo
     */
    public function temDia($dia): bool
    {
        if (!is_array($this->dias)) {
            return false;
        }

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
     * 🔥 Verificar se um horário específico está dentro do intervalo
     */
    public function contemHorario($hora): bool
    {
        $horaComparar = $hora;
        $inicio = $this->inicio;
        $fim = $this->fim;

        // Converter para minutos para facilitar comparação
        $horaMin = $this->horaParaMinutos($horaComparar);
        $inicioMin = $this->horaParaMinutos($inicio);
        $fimMin = $this->horaParaMinutos($fim);

        return $horaMin >= $inicioMin && $horaMin <= $fimMin;
    }

    /**
     * 🔥 Converter hora para minutos
     */
    private function horaParaMinutos($hora): int
    {
        $partes = explode(':', $hora);
        return (int) $partes[0] * 60 + (int) ($partes[1] ?? 0);
    }

    /**
     * 🔥 Gerar bloqueios para um período específico
     */
    public function gerarBloqueios($dataInicio, $dataFim): array
    {
        $dataAtual = Carbon::parse($dataInicio);
        $dataFim = Carbon::parse($dataFim);

        $bloqueiosCriados = [];
        $bloqueiosIgnorados = [];

        while ($dataAtual <= $dataFim) {
            $diaSemana = $dataAtual->dayOfWeek;
            $diasMap = [
                1 => 'segunda',
                2 => 'terca',
                3 => 'quarta',
                4 => 'quinta',
                5 => 'sexta',
                6 => 'sabado',
                0 => 'domingo',
            ];
            $diaSemanaNome = $diasMap[$diaSemana];

            if ($this->temDia($diaSemanaNome) && $this->ativo) {
                $dataStr = $dataAtual->format('Y-m-d');
                $horaInicio = $this->inicio;
                $horaFim = $this->fim;

                // Verificar se já existe bloqueio neste horário
                $existe = Agenda::where('prestador_id', $this->prestador_id)
                    ->where('data', $dataStr)
                    ->where('horario_inicio', $horaInicio)
                    ->where('horario_fim', $horaFim)
                    ->exists();

                if (!$existe) {
                    $agenda = Agenda::create([
                        'prestador_id' => $this->prestador_id,
                        'data' => $dataStr,
                        'horario_inicio' => $horaInicio,
                        'horario_fim' => $horaFim,
                        'bloqueado' => true,
                        'observacao' => 'Bloqueio recorrente: ' . ($this->descricao ?? 'Intervalo'),
                    ]);
                    $bloqueiosCriados[] = [
                        'data' => $dataStr,
                        'horario_inicio' => $horaInicio,
                        'horario_fim' => $horaFim,
                        'agenda_id' => $agenda->id,
                    ];
                } else {
                    $bloqueiosIgnorados[] = [
                        'data' => $dataStr,
                        'horario_inicio' => $horaInicio,
                        'horario_fim' => $horaFim,
                        'motivo' => 'Já existe bloqueio para este horário',
                    ];
                }
            }

            $dataAtual->addDay();
        }

        return [
            'criados' => $bloqueiosCriados,
            'ignorados' => $bloqueiosIgnorados,
            'total_criados' => count($bloqueiosCriados),
            'total_ignorados' => count($bloqueiosIgnorados),
        ];
    }

    /**
     * 🔥 Remover bloqueios gerados por este intervalo
     */
    public function removerBloqueios($dataInicio, $dataFim): int
    {
        $dataAtual = Carbon::parse($dataInicio);
        $dataFim = Carbon::parse($dataFim);
        $removidos = 0;

        while ($dataAtual <= $dataFim) {
            $diaSemana = $dataAtual->dayOfWeek;
            $diasMap = [
                1 => 'segunda',
                2 => 'terca',
                3 => 'quarta',
                4 => 'quinta',
                5 => 'sexta',
                6 => 'sabado',
                0 => 'domingo',
            ];
            $diaSemanaNome = $diasMap[$diaSemana];

            if ($this->temDia($diaSemanaNome)) {
                $dataStr = $dataAtual->format('Y-m-d');

                $deleted = Agenda::where('prestador_id', $this->prestador_id)
                    ->where('data', $dataStr)
                    ->where('horario_inicio', $this->inicio)
                    ->where('horario_fim', $this->fim)
                    ->where('observacao', 'like', 'Bloqueio recorrente: ' . $this->descricao . '%')
                    ->delete();

                $removidos += $deleted;
            }

            $dataAtual->addDay();
        }

        return $removidos;
    }

    /**
     * 🔥 Obter os dias da semana em português
     */
    public function getDiasFormatadosAttribute(): string
    {
        if (!$this->dias || !is_array($this->dias)) {
            return 'Nenhum dia definido';
        }

        $diasMap = [
            'segunda' => 'Segunda',
            'terca' => 'Terça',
            'quarta' => 'Quarta',
            'quinta' => 'Quinta',
            'sexta' => 'Sexta',
            'sabado' => 'Sábado',
            'domingo' => 'Domingo',
        ];

        $diasFormatados = array_map(function ($dia) use ($diasMap) {
            return $diasMap[$dia] ?? $dia;
        }, $this->dias);

        return implode(', ', $diasFormatados);
    }

    /**
     * 🔥 Obter descrição do intervalo
     */
    public function getDescricaoCompletaAttribute(): string
    {
        $dias = $this->dias_formatados;
        $horario = $this->inicio . ' - ' . $this->fim;
        $status = $this->ativo ? 'Ativo' : 'Inativo';

        return "{$dias} • {$horario} • {$status}";
    }

    /**
     * 🔥 Verificar se o intervalo está ativo
     */
    public function isAtivo(): bool
    {
        return (bool) $this->ativo;
    }

    /**
     * 🔥 Ativar/Desativar intervalo
     */
    public function setAtivo(bool $ativo): self
    {
        $this->ativo = $ativo;
        $this->save();
        return $this;
    }

    /**
     * 🔥 Ativar intervalo
     */
    public function ativar(): self
    {
        return $this->setAtivo(true);
    }

    /**
     * 🔥 Desativar intervalo
     */
    public function desativar(): self
    {
        return $this->setAtivo(false);
    }

    /**
     * 🔥 Verificar se o intervalo é válido
     */
    public function isValid(): bool
    {
        // Verificar se tem dias
        if (!$this->dias || !is_array($this->dias) || count($this->dias) === 0) {
            return false;
        }

        // Verificar se tem horário de início e fim
        if (!$this->inicio || !$this->fim) {
            return false;
        }

        // Verificar se o horário de início é menor que o fim
        $inicioMin = $this->horaParaMinutos($this->inicio);
        $fimMin = $this->horaParaMinutos($this->fim);

        if ($inicioMin >= $fimMin) {
            return false;
        }

        return true;
    }

    /**
     * 🔥 Validar e retornar erros
     */
    public function getValidationErrors(): array
    {
        $errors = [];

        if (!$this->dias || !is_array($this->dias) || count($this->dias) === 0) {
            $errors[] = 'Selecione pelo menos um dia da semana';
        }

        if (!$this->inicio) {
            $errors[] = 'Informe o horário de início';
        }

        if (!$this->fim) {
            $errors[] = 'Informe o horário de fim';
        }

        if ($this->inicio && $this->fim) {
            $inicioMin = $this->horaParaMinutos($this->inicio);
            $fimMin = $this->horaParaMinutos($this->fim);

            if ($inicioMin >= $fimMin) {
                $errors[] = 'O horário de início deve ser menor que o horário de fim';
            }
        }

        return $errors;
    }

    // ==========================================
    // 🔥 SCOPES ADICIONAIS
    // ==========================================

    /**
     * Scope para buscar intervalos que contêm um horário específico
     */
    public function scopeContemHorario($query, $hora)
    {
        return $query->where('inicio', '<=', $hora)
            ->where('fim', '>=', $hora);
    }

    /**
     * Scope para buscar intervalos que afetam um dia e horário
     */
    public function scopeParaDiaHorario($query, $data, $hora)
    {
        $diaDaSemana = Carbon::parse($data)->dayOfWeek;
        $diasMap = [
            1 => 'segunda',
            2 => 'terca',
            3 => 'quarta',
            4 => 'quinta',
            5 => 'sexta',
            6 => 'sabado',
            0 => 'domingo',
        ];
        $dia = $diasMap[$diaDaSemana];

        return $query->where('ativo', true)
            ->whereJsonContains('dias', $dia)
            ->where('inicio', '<=', $hora)
            ->where('fim', '>=', $hora);
    }
}
