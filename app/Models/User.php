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
        // ✅ PROPRIEDADES DE LOCALIZAÇÃO
        'raio',
        'latitude',
        'longitude',
        'documento',
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
        // ✅ CASTS DE LOCALIZAÇÃO
        'raio' => 'integer',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
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
     * Tipos de serviço que o prestador oferece
     */
    public function servicoTipos()
    {
        return $this->belongsToMany(ServicoTipo::class, 'prestador_servico_tipos', 'prestador_id', 'servico_tipo_id');
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
     * Mensagens enviadas
     */
    public function mensagensEnviadas()
    {
        return $this->hasMany(Mensagem::class, 'remetente_id');
    }

    /**
     * Mensagens recebidas
     */
    public function mensagensRecebidas()
    {
        return $this->hasMany(Mensagem::class, 'destinatario_id');
    }

    /**
     * Conversas (usuários com quem o usuário atual conversou)
     */
    public function conversas()
    {
        $conversasIds = $this->mensagensEnviadas()
            ->select('destinatario_id')
            ->union($this->mensagensRecebidas()->select('remetente_id'))
            ->distinct()
            ->pluck('destinatario_id', 'remetente_id')
            ->toArray();

        return User::whereIn('id', $conversasIds);
    }

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

    // ==========================================
    // MÉTODOS DE LOCALIZAÇÃO
    // ==========================================

    /**
     * Definir localização
     */
    public function setLocationAttribute($value)
    {
        if (is_array($value) && isset($value['latitude'], $value['longitude'])) {
            $this->attributes['latitude'] = $value['latitude'];
            $this->attributes['longitude'] = $value['longitude'];
        }
    }

    /**
     * Calcular distância até um ponto (em km) usando a fórmula de Haversine
     */
    public function distanceTo($latitude, $longitude)
    {
        if ($this->latitude && $this->longitude) {
            $lat1 = deg2rad($this->latitude);
            $lon1 = deg2rad($this->longitude);
            $lat2 = deg2rad($latitude);
            $lon2 = deg2rad($longitude);

            $delta = $lon2 - $lon1;
            $cos = sin($lat1) * sin($lat2) + cos($lat1) * cos($lat2) * cos($delta);
            $angle = acos($cos);

            return $angle * 6371; // Raio da Terra em km
        }

        return null;
    }

    /**
     * Escopo para prestadores próximos (usando a fórmula de Haversine)
     */
    public function scopeNearby($query, $latitude, $longitude, $radius = 10)
    {
        return $query->whereRaw(
            "(
                6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )
            ) <= ?",
            [$latitude, $longitude, $latitude, $radius]
        );
    }

    /**
     * Escopo para prestadores dentro do raio de atendimento
     */
    public function scopeWithinRadius($query, $latitude, $longitude)
    {
        return $query->whereRaw(
            "(
                6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )
            ) <= COALESCE(raio, 10)",
            [$latitude, $longitude, $latitude]
        );
    }

    /**
     * Escopo para usuários com localização definida
     */
    public function scopeHasLocation($query)
    {
        return $query->whereNotNull('latitude')->whereNotNull('longitude');
    }
}
