<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Servico extends Model
{
    use HasFactory;

    protected $table = 'servicos';

    protected $fillable = [
        'prestador_id',
        'categoria_id',
        'nome',
        'descricao',
        'preco',        // ✅ O campo no banco é 'preco', não 'preco_base'
        'duracao',
        'ativo',
    ];

    protected $casts = [
        'preco' => 'decimal:2',  // ✅ Nome correto
        'ativo' => 'boolean',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function prestador()
    {
        return $this->belongsTo(User::class, 'prestador_id');
    }
}
