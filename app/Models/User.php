<?php
// app/Models/User.php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Casts\Attribute;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'nome',
        'email',
        'telefone',
        'password',
        'tipo',
        'foto',
        'disponivel',
        'verificado',
        'profissao',
        'sobre',
        'media_avaliacao',
        'total_avaliacoes',
        'latitude',
        'longitude',
        'raio_atendimento',
        'configuracoes',
        'role_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'disponivel' => 'boolean',
        'verificado' => 'boolean',
        'media_avaliacao' => 'decimal:2',
        'configuracoes' => 'array',
    ];

    // ==========================================
    // 🔥 RELACIONAMENTOS
    // ==========================================

    /**
     * Perfil do prestador (one-to-one)
     */
    public function prestadorProfile()
    {
        return $this->hasOne(PrestadorProfile::class);
    }

    /**
     * Categorias que o prestador atende (muitos-para-muitos)
     */
    public function categorias()
    {
        return $this->belongsToMany(Categoria::class, 'categoria_prestador');
    }

    /**
     * Pedidos feitos pelo cliente
     */
    public function pedidosComoCliente()
    {
        return $this->hasMany(Pedido::class, 'cliente_id');
    }

    /**
     * Pedidos recebidos pelo prestador
     */
    public function pedidosComoPrestador()
    {
        return $this->hasMany(Pedido::class, 'prestador_id');
    }

    /**
     * 🔥 AGENDA - Relacionamento com agenda do prestador
     */
    public function agenda()
    {
        return $this->hasMany(Agenda::class, 'prestador_id');
    }

    /**
     * 🔥 PEDIDOS - Alias para pedidosComoPrestador (para consistência)
     */
    public function pedidos()
    {
        return $this->pedidosComoPrestador();
    }

    /**
     * Favoritos do cliente (relação com a tabela favoritos)
     */
    public function favoritos()
    {
        return $this->hasMany(Favorito::class, 'cliente_id');
    }

    /**
     * Prestadores favoritos do cliente
     */
    public function prestadoresFavoritos()
    {
        return $this->belongsToMany(User::class, 'favoritos', 'cliente_id', 'prestador_id');
    }

    /**
     * Notificações do usuário
     */
    public function notificacoes()
    {
        return $this->hasMany(Notificacao::class, 'user_id');
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
     * Serviços oferecidos (para prestador)
     */
    public function servicos()
    {
        return $this->hasMany(Servico::class, 'prestador_id');
    }

    /**
     * Endereços do usuário
     */
    public function enderecos()
    {
        return $this->hasMany(Endereco::class);
    }

    /**
     * Role do usuário
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Propostas enviadas pelo prestador
     */
    public function propostas()
    {
        return $this->hasMany(Proposta::class, 'prestador_id');
    }

    // ==========================================
    // 🔥 SCOPES (Consultas comuns)
    // ==========================================

    /**
     * Scope para filtrar apenas prestadores
     */
    public function scopePrestadores($query)
    {
        return $query->where('tipo', 'prestador');
    }

    /**
     * Scope para filtrar apenas clientes
     */
    public function scopeClientes($query)
    {
        return $query->where('tipo', 'cliente');
    }

    /**
     * Scope para filtrar apenas admins
     */
    public function scopeAdmins($query)
    {
        return $query->where('tipo', 'admin');
    }

    /**
     * Scope para prestadores disponíveis
     */
    public function scopeDisponiveis($query)
    {
        return $query->where('disponivel', true);
    }

    /**
     * Scope para prestadores verificados
     */
    public function scopeVerificados($query)
    {
        return $query->where('verificado', true);
    }

    /**
     * 🔥 Scope para prestadores disponíveis em uma data/hora específica
     */
    public function scopeDisponiveisEm($query, $data, $hora)
    {
        return $query->where('disponivel', true)
            ->where('verificado', true)
            ->whereDoesntHave('agenda', function ($q) use ($data, $hora) {
                $q->where('data', $data)
                    ->where('horario_inicio', '<=', $hora)
                    ->where('horario_fim', '>=', $hora)
                    ->where('bloqueado', true);
            })
            ->whereDoesntHave('pedidos', function ($q) use ($data, $hora) {
                $q->where('agendado_para', $data . ' ' . $hora)
                    ->whereIn('status', ['aceito', 'em_andamento']);
            });
    }

    // ==========================================
    // 🔥 ACCESSORS & MUTATORS
    // ==========================================

    /**
     * Get user's full name
     */
    public function getNomeCompletoAttribute()
    {
        return $this->nome;
    }

    /**
     * Get user's first name
     */
    public function getPrimeiroNomeAttribute()
    {
        $parts = explode(' ', $this->nome);
        return $parts[0];
    }

    /**
     * Get user avatar URL (with fallback)
     */
    public function getAvatarUrlAttribute()
    {
        if ($this->foto) {
            if (filter_var($this->foto, FILTER_VALIDATE_URL)) {
                return $this->foto;
            }
            return asset('storage/' . $this->foto);
        }
        return 'https://ui-avatars.com/api/?background=667eea&color=fff&bold=true&size=120&name=' . urlencode($this->nome);
    }

    /**
     * Get formatted average rating
     */
    public function getMediaAvaliacaoFormatadaAttribute()
    {
        return number_format($this->media_avaliacao, 1);
    }

    /**
     * Acessor para configurações (com valores padrão)
     */
    public function getConfiguracoesAttribute($value)
    {
        if (!$value) {
            return [
                'notificacoes_email' => true,
                'notificacoes_push' => true,
                'idioma' => 'pt',
                'tema' => 'system',
            ];
        }
        return is_array($value) ? $value : json_decode($value, true);
    }

    /**
     * Mutator para configurações
     */
    public function setConfiguracoesAttribute($value)
    {
        $this->attributes['configuracoes'] = is_array($value) ? json_encode($value) : $value;
    }

    // ==========================================
    // 🔥 MÉTODOS AUXILIARES
    // ==========================================

    /**
     * Check if user is a client
     */
    public function isCliente(): bool
    {
        return $this->tipo === 'cliente';
    }

    /**
     * Check if user is a provider
     */
    public function isPrestador(): bool
    {
        return $this->tipo === 'prestador';
    }

    /**
     * Check if user is an admin
     */
    public function isAdmin(): bool
    {
        return $this->tipo === 'admin';
    }

    /**
     * Check if user is root
     */
    public function isRoot(): bool
    {
        return $this->tipo === 'root' || $this->hasRole('root');
    }

    /**
     * Check if provider is available
     */
    public function isDisponivel(): bool
    {
        return $this->isPrestador() && $this->disponivel;
    }

    /**
     * Check if provider is verified
     */
    public function isVerificado(): bool
    {
        return (bool) $this->verificado;
    }

    /**
     * 🔥 Verificar se o prestador está disponível em uma data/hora específica
     */
    public function isDisponivelEm($data, $hora): bool
    {
        if (!$this->isPrestador() || !$this->disponivel || !$this->verificado) {
            return false;
        }

        // Verificar se está bloqueado na agenda
        $bloqueado = Agenda::where('prestador_id', $this->id)
            ->where('data', $data)
            ->where('horario_inicio', '<=', $hora)
            ->where('horario_fim', '>=', $hora)
            ->where('bloqueado', true)
            ->exists();

        if ($bloqueado) {
            return false;
        }

        // Verificar se já tem pedido na mesma data/hora
        $ocupado = Pedido::where('prestador_id', $this->id)
            ->where('agendado_para', $data . ' ' . $hora)
            ->whereIn('status', ['aceito', 'em_andamento'])
            ->exists();

        return !$ocupado;
    }

    /**
     * Get total favoritos count
     */
    public function getFavoritosCountAttribute(): int
    {
        return $this->favoritos()->count();
    }

    /**
     * Get total pedidos as client
     */
    public function getTotalPedidosClienteAttribute(): int
    {
        return $this->pedidosComoCliente()->count();
    }

    /**
     * Get total pedidos as provider
     */
    public function getTotalPedidosPrestadorAttribute(): int
    {
        return $this->pedidosComoPrestador()->count();
    }

    /**
     * Update average rating from new evaluation
     */
    public function atualizarMediaAvaliacao(): void
    {
        $media = $this->avaliacoesRecebidas()
            ->where('status', 'aprovada')
            ->avg('nota') ?? 0;

        $total = $this->avaliacoesRecebidas()
            ->where('status', 'aprovada')
            ->count();

        $this->update([
            'media_avaliacao' => round($media, 2),
            'total_avaliacoes' => $total,
        ]);
    }

    /**
     * 🔥 Verificar se tem role específica
     */
    public function hasRole($roleName): bool
    {
        return $this->role?->nome === $roleName;
    }

    // ==========================================
    // 🔥 ACESSORES PARA DADOS DO PRESTADOR PROFILE
    // ==========================================

    /**
     * Get disponibilidade from prestador profile
     */
    public function getDisponibilidadeAttribute()
    {
        if (!$this->isPrestador()) {
            return null;
        }
        return $this->prestadorProfile?->disponibilidade;
    }

    /**
     * Get portfolio from prestador profile
     */
    public function getPortfolioAttribute()
    {
        if (!$this->isPrestador()) {
            return null;
        }
        return $this->prestadorProfile?->portfolio;
    }

    /**
     * Get documento from prestador profile
     */
    public function getDocumentoAttribute()
    {
        if (!$this->isPrestador()) {
            return null;
        }
        return $this->prestadorProfile?->documento;
    }

    /**
     * Get status_documento from prestador profile
     */
    public function getStatusDocumentoAttribute()
    {
        if (!$this->isPrestador()) {
            return null;
        }
        return $this->prestadorProfile?->status_documento;
    }
}
