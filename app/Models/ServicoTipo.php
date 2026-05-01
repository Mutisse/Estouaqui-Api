<?php
// app/Models/ServicoTipo.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicoTipo extends Model
{
    use HasFactory;

    protected $table = 'servico_tipos';

    protected $fillable = [
        'nome',
        'slug',
        'icone',
        'cor',
        'descricao',
        'ordem',
        'ativo'
    ];

    protected $casts = [
        'ativo' => 'boolean'
    ];

    // Relação com serviços
    public function servicos()
    {
        return $this->hasMany(Servico::class, 'tipo_id');
    }

    // Escopo para tipos ativos
    public function scopeAtivo($query)
    {
        return $query->where('ativo', true);
    }

    // Escopo para ordenação padrão
    public function scopeOrdenado($query)
    {
        return $query->orderBy('ordem', 'asc')->orderBy('nome', 'asc');
    }

    // Buscar todos os tipos ativos ordenados
    public static function getTiposAtivos()
    {
        return self::ativo()->ordenado()->get();
    }

    // Buscar por slug
    public static function findBySlug(string $slug)
    {
        return self::where('slug', $slug)->first();
    }

    // Buscar tipos com contagem de serviços
    public static function getTiposComContagem()
    {
        return self::withCount('servicos')
            ->ativo()
            ->ordenado()
            ->get();
    }
}
