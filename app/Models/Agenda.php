<?php
// app/Models/Agenda.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Agenda extends Model
{
    use HasFactory;

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

    protected $dates = [
        'data',
        'created_at',
        'updated_at',
    ];

    // ==========================================
    // 🔥 RELACIONAMENTOS
    // ==========================================

    /**
     * Prestador dono da agenda
     */
    public function prestador()
    {
        return $this->belongsTo(User::class, 'prestador_id');
    }

    // ==========================================
    // 🔥 SCOPES
    // ==========================================

    /**
     * Scope para buscar apenas bloqueios
     */
    public function scopeBloqueados($query)
    {
        return $query->where('bloqueado', true);
    }

    /**
     * Scope para buscar apenas desbloqueados
     */
    public function scopeDesbloqueados($query)
    {
        return $query->where('bloqueado', false);
    }

    /**
     * Scope para buscar por data específica
     */
    public function scopeNaData($query, $data)
    {
        return $query->where('data', $data);
    }

    /**
     * Scope para buscar por período
     */
    public function scopeEntreDatas($query, $inicio, $fim)
    {
        return $query->whereBetween('data', [$inicio, $fim]);
    }

    /**
     * 🔥 Scope para verificar se um horário está bloqueado
     */
    public function scopeHorarioBloqueado($query, $data, $hora)
    {
        return $query->where('data', $data)
            ->where('horario_inicio', '<=', $hora)
            ->where('horario_fim', '>=', $hora)
            ->where('bloqueado', true);
    }

    // ==========================================
    // 🔥 MÉTODOS AUXILIARES
    // ==========================================

    /**
     * 🔥 Verificar se um horário específico está bloqueado
     */
    public static function isHorarioBloqueado($prestadorId, $data, $hora): bool
    {
        return self::where('prestador_id', $prestadorId)
            ->where('data', $data)
            ->where('horario_inicio', '<=', $hora)
            ->where('horario_fim', '>=', $hora)
            ->where('bloqueado', true)
            ->exists();
    }

    /**
     * 🔥 Verificar conflito de horário
     */
    public static function hasConflito($prestadorId, $data, $horaInicio, $horaFim): bool
    {
        return self::where('prestador_id', $prestadorId)
            ->where('data', $data)
            ->where(function ($query) use ($horaInicio, $horaFim) {
                $query->whereBetween('horario_inicio', [$horaInicio, $horaFim])
                    ->orWhereBetween('horario_fim', [$horaInicio, $horaFim])
                    ->orWhere(function ($q) use ($horaInicio, $horaFim) {
                        $q->where('horario_inicio', '<=', $horaInicio)
                            ->where('horario_fim', '>=', $horaFim);
                    });
            })
            ->where('bloqueado', true)
            ->exists();
    }

    /**
     * 🔥 Obter todos os bloqueios de um dia
     */
    public static function getBloqueiosDoDia($prestadorId, $data)
    {
        return self::where('prestador_id', $prestadorId)
            ->where('data', $data)
            ->where('bloqueado', true)
            ->orderBy('horario_inicio')
            ->get();
    }

    /**
     * 🔥 Obter todos os horários ocupados de um dia (incluindo pedidos)
     */
    public static function getHorariosOcupados($prestadorId, $data)
    {
        // Bloqueios da agenda
        $bloqueios = self::where('prestador_id', $prestadorId)
            ->where('data', $data)
            ->where('bloqueado', true)
            ->get()
            ->pluck('horario_inicio')
            ->toArray();

        // Pedidos aceitos
        $pedidos = Pedido::where('prestador_id', $prestadorId)
            ->whereDate('agendado_para', $data)
            ->whereIn('status', ['aceito', 'em_andamento'])
            ->get()
            ->pluck('agendado_para')
            ->map(function ($item) {
                return \Carbon\Carbon::parse($item)->format('H:i');
            })
            ->toArray();

        return array_unique(array_merge($bloqueios, $pedidos));
    }

    /**
     * 🔥 Verificar disponibilidade de um dia inteiro
     */
    public static function getDisponibilidadeDia($prestadorId, $data)
    {
        $horariosOcupados = self::getHorariosOcupados($prestadorId, $data);

        $horariosDisponiveis = [];
        for ($h = 8; $h <= 20; $h++) {
            $hora = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
            if (!in_array($hora, $horariosOcupados)) {
                $horariosDisponiveis[] = $hora;
            }
        }

        return $horariosDisponiveis;
    }
}
