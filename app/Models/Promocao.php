<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promocao extends Model
{
    use HasFactory, SoftDeletes;

    // ✅ ESPECIFICAR O NOME CORRETO DA TABELA
    protected $table = 'promocoes';

    protected $fillable = [
        'codigo',
        'titulo',
        'descricao',
        'tipo_desconto',
        'valor_desconto',
        'valor_minimo',
        'validade',
        'ativo',
        'imagem',
    ];

    protected $casts = [
        'validade' => 'date',
        'ativo' => 'boolean',
        'valor_desconto' => 'decimal:2',
        'valor_minimo' => 'decimal:2',
    ];

    /**
     * Verificar se promoção está ativa e válida
     */
    public function isValida(): bool
    {
        return $this->ativo && $this->validade >= now()->startOfDay();
    }

    /**
     * Calcular desconto
     */
    public function calcularDesconto(float $valorPedido): float
    {
        if (!$this->isValida() || $valorPedido < $this->valor_minimo) {
            return 0;
        }

        if ($this->tipo_desconto === 'percentual') {
            return ($valorPedido * $this->valor_desconto) / 100;
        }

        return min($this->valor_desconto, $valorPedido);
    }

    /**
     * Scope para promoções ativas
     */
    public function scopeAtivas($query)
    {
        return $query->where('ativo', true)
            ->whereDate('validade', '>=', now());
    }
}
