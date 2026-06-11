<?php
// app/Models/LogTemplate.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogTemplate extends Model
{
    use HasFactory;

    protected $table = 'log_templates';

    protected $fillable = [
        'evento',
        'titulo',
        'mensagem',
        'nivel',
        'modulo',
        'ativo'
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Renderizar título com os dados
     */
    public function renderTitulo(array $data): string
    {
        $titulo = $this->titulo;
        foreach ($data as $key => $value) {
            $titulo = str_replace("{{{$key}}}", (string) $value, $titulo);
        }
        return $titulo;
    }

    /**
     * Renderizar mensagem com os dados
     */
    public function renderMensagem(array $data): string
    {
        $mensagem = $this->mensagem;
        foreach ($data as $key => $value) {
            $mensagem = str_replace("{{{$key}}}", (string) $value, $mensagem);
        }
        return $mensagem;
    }

    /**
     * Scope para templates ativos
     */
    public function scopeAtivo($query)
    {
        return $query->where('ativo', true);
    }

    /**
     * Scope para filtrar por módulo
     */
    public function scopeModulo($query, $modulo)
    {
        return $query->where('modulo', $modulo);
    }

    /**
     * Scope para filtrar por nível
     */
    public function scopeNivel($query, $nivel)
    {
        return $query->where('nivel', $nivel);
    }
}
