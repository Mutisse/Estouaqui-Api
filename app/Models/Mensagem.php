<?php
// app/Models/Mensagem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mensagem extends Model
{
    use HasFactory;

    protected $table = 'mensagens';

    protected $fillable = [
        'chat_id', 'sender_id', 'receiver_id', 'mensagem', 'lida', 'lida_em'
    ];

    protected $casts = [
        'lida' => 'boolean',
        'lida_em' => 'datetime',
    ];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function marcarComoLida()
    {
        if (!$this->lida) {
            $this->update([
                'lida' => true,
                'lida_em' => now(),
            ]);
        }
    }

    public function scopeNaoLidas($query, $userId)
    {
        return $query->where('receiver_id', $userId)->where('lida', false);
    }
}
