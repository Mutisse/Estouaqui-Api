<?php
// app/Models/Mensagem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mensagem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mensagens';

    protected $fillable = [
        'remetente_id',
        'destinatario_id',
        'mensagem',
        'lida',
        'lida_em',
    ];

    protected $casts = [
        'lida' => 'boolean',
        'lida_em' => 'datetime',
    ];

    // ==========================================
    // RELACIONAMENTOS
    // ==========================================

    /**
     * Usuário que enviou a mensagem
     */
    public function remetente()
    {
        return $this->belongsTo(User::class, 'remetente_id');
    }

    /**
     * Usuário que recebeu a mensagem
     */
    public function destinatario()
    {
        return $this->belongsTo(User::class, 'destinatario_id');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Buscar mensagens não lidas
     */
    public function scopeNaoLidas($query)
    {
        return $query->where('lida', false);
    }

    /**
     * Buscar mensagens entre dois usuários
     */
    public function scopeEntre($query, $usuario1, $usuario2)
    {
        return $query->where(function ($q) use ($usuario1, $usuario2) {
            $q->where('remetente_id', $usuario1)
              ->where('destinatario_id', $usuario2);
        })->orWhere(function ($q) use ($usuario1, $usuario2) {
            $q->where('remetente_id', $usuario2)
              ->where('destinatario_id', $usuario1);
        });
    }

    // ==========================================
    // MÉTODOS AUXILIARES
    // ==========================================

    /**
     * Marcar mensagem como lida
     */
    public function marcarComoLida()
    {
        $this->lida = true;
        $this->lida_em = now();
        $this->save();
    }

    /**
     * Verificar se a mensagem foi enviada por um usuário específico
     */
    public function foiEnviadaPor($usuarioId)
    {
        return $this->remetente_id == $usuarioId;
    }

    /**
     * Verificar se a mensagem foi recebida por um usuário específico
     */
    public function foiRecebidaPor($usuarioId)
    {
        return $this->destinatario_id == $usuarioId;
    }
}
