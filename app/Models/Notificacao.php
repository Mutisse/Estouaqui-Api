<?php
// app/Models/Notificacao.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notificacao extends Model
{
    use HasFactory;

    protected $table = 'notificacoes';

    protected $fillable = [
        'user_id',
        'titulo',
        'mensagem',
        'tipo',
        'data',
        'lida',
        'lida_em',
    ];

    protected $casts = [
        'data' => 'array',
        'lida' => 'boolean',
        'lida_em' => 'datetime',
    ];

    /**
     * Relacionamento com o usuário
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope para notificações não lidas
     */
    public function scopeNaoLidas($query)
    {
        return $query->where('lida', false);
    }

    /**
     * Scope para notificações lidas
     */
    public function scopeLidas($query)
    {
        return $query->where('lida', true);
    }

    /**
     * Scope para filtrar por tipo
     */
    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Marcar notificação como lida
     */
    public function marcarComoLida()
    {
        $this->update([
            'lida' => true,
            'lida_em' => now(),
        ]);
    }

    /**
     * Criar notificação para usuário
     */
    public static function criar($userId, $titulo, $mensagem, $tipo = 'sistema', $data = null)
    {
        return self::create([
            'user_id' => $userId,
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'tipo' => $tipo,
            'data' => $data,
            'lida' => false,
        ]);
    }
}
