<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Ticket extends Model
{
    use HasFactory;

    protected $table = 'tickets';

    protected $fillable = [
        'numero',
        'titulo',
        'descricao',
        'status',
        'prioridade',
        'categoria',
        'cliente_id',
        'prestador_id',
        'admin_id',
        'anexos',
        'resolvido_em',
    ];

    protected $casts = [
        'anexos' => 'array',
        'resolvido_em' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            $ticket->numero = 'TKT-' . strtoupper(uniqid());
        });
    }

    public function cliente()
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function prestador()
    {
        return $this->belongsTo(User::class, 'prestador_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function mensagens()
    {
        return $this->hasMany(MensagemTicket::class, 'ticket_id');
    }
}
