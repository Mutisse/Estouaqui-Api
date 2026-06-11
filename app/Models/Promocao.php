<?php
// app/Models/Promocao.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promocao extends Model
{
    use HasFactory;

    protected $table = 'promocoes';

    protected $fillable = [
        'titulo',
        'descricao',
        'codigo',
        'tipo_desconto',
        'valor_desconto',
        'valor_minimo',
        'uso_maximo',
        'uso_atual',
        'validade_inicio',
        'validade_fim',
        'ativo',
    ];

    protected $casts = [
        'validade_inicio' => 'date',
        'validade_fim' => 'date',
        'ativo' => 'boolean',
        'valor_desconto' => 'decimal:2',
        'valor_minimo' => 'decimal:2',
    ];

    /**
     * Verifica se a promoção é válida
     */
    public function isValida()
    {
        $hoje = now()->startOfDay();
        return $this->ativo &&
               $hoje >= $this->validade_inicio &&
               $hoje <= $this->validade_fim &&
               ($this->uso_maximo === null || $this->uso_atual < $this->uso_maximo);
    }

    /**
     * Aplica o cupom (incrementa uso)
     */
    public function aplicar()
    {
        if ($this->uso_maximo !== null) {
            $this->increment('uso_atual');
        }
    }

    /**
     * Calcula o desconto para um valor
     */
    public function calcularDesconto($valor)
    {
        if ($valor < $this->valor_minimo) {
            return 0;
        }

        if ($this->tipo_desconto === 'percentual') {
            return ($valor * $this->valor_desconto) / 100;
        }

        return min($this->valor_desconto, $valor);
    }
}
