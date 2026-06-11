<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    use HasFactory;

    protected $table = 'pedidos';

    protected $fillable = [
        'numero', 'cliente_id', 'prestador_id', 'categoria_id',
        'descricao', 'endereco', 'foto', 'status', 'valor',
        'agendado_para', 'concluido_em'
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'agendado_para' => 'datetime',
        'concluido_em' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($pedido) {
            $pedido->numero = 'PED-' . strtoupper(uniqid());
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

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    // ✅ ADICIONAR ESTE RELACIONAMENTO
    public function propostas()
    {
        return $this->hasMany(Proposta::class, 'pedido_id');
    }

    // ✅ ADICIONAR ESTE MÉTODO PARA CONTAR PROPOSTAS
    public function getTotalPropostasAttribute()
    {
        return $this->propostas()->count();
    }

    
}
