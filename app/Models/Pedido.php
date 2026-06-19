<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pedido extends Model
{
    use HasFactory;

    protected $table = 'pedidos';

    protected $fillable = [
        'numero',
        'cliente_id',
        'prestador_id',
        'categoria_id',
        'descricao',
        'endereco',
        'latitude',      // ✅ ADICIONADO
        'longitude',     // ✅ ADICIONADO
        'foto',
        'status',
        'valor',
        'agendado_para',
        'concluido_em',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'agendado_para' => 'datetime',
        'concluido_em' => 'datetime',
        'latitude' => 'decimal:8',   // ✅ ADICIONADO
        'longitude' => 'decimal:8',  // ✅ ADICIONADO
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($pedido) {
            $pedido->numero = 'PED-' . strtoupper(uniqid());
        });
    }

    // ==========================================
    // RELACIONAMENTOS
    // ==========================================

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function prestador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prestador_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function propostas(): HasMany
    {
        return $this->hasMany(Proposta::class, 'pedido_id');
    }

    // ==========================================
    // MÉTODOS AUXILIARES
    // ==========================================

    public function getTotalPropostasAttribute(): int
    {
        return $this->propostas()->count();
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'pendente' => 'Pendente',
            'aceito' => 'Aceito',
            'em_andamento' => 'Em Andamento',
            'concluido' => 'Concluído',
            'cancelado' => 'Cancelado',
        ];
        return $labels[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        $colors = [
            'pendente' => 'warning',
            'aceito' => 'primary',
            'em_andamento' => 'info',
            'concluido' => 'positive',
            'cancelado' => 'negative',
        ];
        return $colors[$this->status] ?? 'grey';
    }

    public function isPendente(): bool
    {
        return $this->status === 'pendente';
    }

    public function isAceito(): bool
    {
        return $this->status === 'aceito';
    }

    public function isEmAndamento(): bool
    {
        return $this->status === 'em_andamento';
    }

    public function isConcluido(): bool
    {
        return $this->status === 'concluido';
    }

    public function isCancelado(): bool
    {
        return $this->status === 'cancelado';
    }

    public function isFinalizado(): bool
    {
        return in_array($this->status, ['concluido', 'cancelado']);
    }

    public function podeSerCancelado(): bool
    {
        return in_array($this->status, ['pendente', 'aceito']);
    }

    public function getValorFormatadoAttribute(): string
    {
        if (!$this->valor) {
            return 'A definir';
        }
        return number_format($this->valor, 2, ',', '.') . ' MZN';
    }

    public function getDataFormatadaAttribute(): string
    {
        return $this->created_at ? $this->created_at->format('d/m/Y H:i') : '—';
    }
}
