<?php
// app/Models/Chat.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'prestador_id',
        'ultima_mensagem',
        'ultima_mensagem_data'
    ];

    protected $casts = [
        'ultima_mensagem_data' => 'datetime',
    ];

    public function cliente()
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function prestador()
    {
        return $this->belongsTo(User::class, 'prestador_id');
    }

    public function mensagens()
    {
        return $this->hasMany(Mensagem::class);
    }

    /**
     * Relacionamento para mensagens NÃO LIDAS (sem usar auth() no relacionamento)
     */
    public function mensagensNaoLidas()
    {
        return $this->hasMany(Mensagem::class)->where('lida', false);
    }

    /**
     * Método auxiliar para contar mensagens não lidas de um usuário específico
     */
    public function contarMensagensNaoLidas($userId)
    {
        return $this->mensagens()
            ->where('receiver_id', $userId)
            ->where('lida', false)
            ->count();
    }
}
