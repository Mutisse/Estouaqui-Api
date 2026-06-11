<?php
// app/Models/NotificationTemplate.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $table = 'notification_templates';

    protected $fillable = [
        'evento', 'titulo', 'mensagem', 'tipo', 'ativo'
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    /**
     * Renderizar título com variáveis
     */
    public function renderTitulo(array $data = []): string
    {
        $titulo = $this->titulo;
        foreach ($data as $key => $value) {
            $titulo = str_replace("{{{$key}}}", $value, $titulo);
        }
        return $titulo;
    }

    /**
     * Renderizar mensagem com variáveis
     */
    public function renderMensagem(array $data = []): string
    {
        $mensagem = $this->mensagem;
        foreach ($data as $key => $value) {
            $mensagem = str_replace("{{{$key}}}", $value, $mensagem);
        }
        return $mensagem;
    }
}
