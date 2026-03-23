<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'nome',
        'email',
        'telefone',
        'endereco',
        'foto',
        'password',
        'tipo',
        'verificado',
        'ativo',
        'profissao',
        'sobre',
        'media_avaliacao',
        'total_avaliacoes',
        'blocked_at',
        'preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'verificado' => 'boolean',
        'ativo' => 'boolean',
        'blocked_at' => 'datetime',
        'preferences' => 'array',
        'media_avaliacao' => 'decimal:1',
    ];

    // ==========================================
    // VERIFICAÇÕES DE PERFIL
    // ==========================================

    public function isCliente(): bool
    {
        return $this->tipo === 'cliente';
    }

    public function isAdmin(): bool
    {
        return $this->tipo === 'admin';
    }

    public function isPrestador(): bool
    {
        return $this->tipo === 'prestador';
    }

    public function isVerificado(): bool
    {
        return $this->verificado === true;
    }

    public function isAtivo(): bool
    {
        return $this->ativo === true;
    }

    public function isBloqueado(): bool
    {
        return !is_null($this->blocked_at);
    }

    // ==========================================
    // RELACIONAMENTOS
    // ==========================================

    /**
     * Serviços oferecidos (apenas para prestador)
     */
    public function servicos()
    {
        return $this->hasMany(Servico::class, 'prestador_id');
    }

    /**
     * Categorias que o prestador atende
     */
    public function categorias()
    {
        return $this->belongsToMany(Categoria::class, 'prestador_categorias');
    }

    /**
     * Pedidos feitos pelo cliente
     */
    public function pedidosCliente()
    {
        return $this->hasMany(Pedido::class, 'cliente_id');
    }

    /**
     * Pedidos recebidos pelo prestador
     */
    public function pedidosPrestador()
    {
        return $this->hasMany(Pedido::class, 'prestador_id');
    }

    /**
     * Avaliações recebidas (como prestador)
     */
    public function avaliacoesRecebidas()
    {
        return $this->hasMany(Avaliacao::class, 'prestador_id');
    }

    /**
     * Avaliações feitas (como cliente)
     */
    public function avaliacoesFeitas()
    {
        return $this->hasMany(Avaliacao::class, 'cliente_id');
    }

    /**
     * Favoritos (prestadores favoritos do cliente)
     */
    public function favoritos()
    {
        return $this->belongsToMany(User::class, 'favoritos', 'cliente_id', 'prestador_id');
    }

    /**
     * Transações do usuário
     */
    public function transacoes()
    {
        return $this->hasMany(Transacao::class);
    }

    /**
     * Notificações (já existe no trait Notifiable)
     */

    // ==========================================
    // MÉTODOS AUXILIARES
    // ==========================================

    /**
     * Obter foto com URL completa
     */
    public function getFotoUrlAttribute()
    {
        return $this->foto ? asset('storage/' . $this->foto) : null;
    }

    /**
     * Atualizar média de avaliações
     */
    public function atualizarMediaAvaliacoes()
    {
        $this->media_avaliacao = $this->avaliacoesRecebidas()->avg('nota') ?? 0;
        $this->total_avaliacoes = $this->avaliacoesRecebidas()->count();
        $this->save();
    }

    /**
     * Bloquear usuário
     */
    public function bloquear()
    {
        $this->blocked_at = now();
        $this->save();
    }

    /**
     * Desbloquear usuário
     */
    public function desbloquear()
    {
        $this->blocked_at = null;
        $this->save();
    }
}
