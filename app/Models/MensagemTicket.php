<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MensagemTicket extends Model
{
    use HasFactory;

    protected $table = 'mensagens_tickets';

    protected $fillable = [
        'ticket_id',
        'remetente_id',
        'remetente_tipo',
        'remetente_nome',
        'mensagem',
        'anexos',
        'lida',
        'lida_em',
    ];

    protected $casts = [
        'anexos' => 'array',
        'lida' => 'boolean',
        'lida_em' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function remetente()
    {
        return $this->belongsTo(User::class, 'remetente_id');
    }
}
