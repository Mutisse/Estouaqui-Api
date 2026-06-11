<?php
// app/Models/Categoria.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    use HasFactory;

    protected $table = 'categorias';

    protected $fillable = [
        'nome',
        'slug',
        'descricao',
        'icone',
        'cor',
        'imagem',
        'ordem',
        'status',
        'categoria_pai_id'
    ];

    protected $casts = [
        'status' => 'boolean',
        'ordem' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relacionamento com categorias filhas
     */
    public function subcategorias()
    {
        return $this->hasMany(Categoria::class, 'categoria_pai_id');
    }

    /**
     * Relacionamento com categoria pai
     */
    public function categoriaPai()
    {
        return $this->belongsTo(Categoria::class, 'categoria_pai_id');
    }

    /**
     * Relacionamento com serviços
     */
    public function servicos()
    {
        return $this->hasMany(Servico::class, 'categoria_id');
    }

    /**
     * Relacionamento com pedidos
     */
    public function pedidos()
    {
        return $this->hasMany(Pedido::class, 'categoria_id');
    }

    /**
     * Escopo para categorias ativas
     */
    public function scopeAtivo($query)
    {
        return $query->where('status', true);
    }

    /**
     * Escopo para categorias principais (sem pai)
     */
    public function scopePrincipais($query)
    {
        return $query->whereNull('categoria_pai_id');
    }
}
