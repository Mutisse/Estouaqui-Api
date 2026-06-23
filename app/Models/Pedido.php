<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Pedido extends Model
{
    use HasFactory;

    protected $table = 'pedidos';

    protected $fillable = [
        'numero',
        'cliente_id',
        'prestador_id',
        'categoria_id',
        'descricao',
        'endereco',
        'latitude',
        'longitude',
        'foto',
        'status',
        'valor',
        'agendado_para',
        'concluido_em',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'agendado_para' => 'datetime',
        'concluido_em' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    protected $appends = [
        'status_label',
        'status_color',
        'valor_formatado',
        'data_formatada',
        'total_propostas',
    ];

    // ==========================================
    // 🔥 BOOT / EVENTOS
    // ==========================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($pedido) {
            if (!$pedido->numero) {
                $pedido->numero = self::gerarNumero();
            }
        });

        // 🔥 Ao criar, verificar se o prestador está disponível
        static::creating(function ($pedido) {
            if ($pedido->prestador_id && $pedido->agendado_para) {
                $pedido->validarDisponibilidadePrestador();
            }
        });

        // 🔥 Ao atualizar status para 'aceito', validar disponibilidade
        static::updating(function ($pedido) {
            if ($pedido->isDirty('status') && $pedido->status === 'aceito') {
                if ($pedido->prestador_id && $pedido->agendado_para) {
                    $pedido->validarDisponibilidadePrestador();
                }
            }
        });
    }

    // ==========================================
    // 🔥 RELACIONAMENTOS
    // ==========================================

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cliente_id');
    }

    public function prestador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prestador_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function propostas(): HasMany
    {
        return $this->hasMany(Proposta::class, 'pedido_id');
    }

    /**
     * 🔥 Relacionamento com avaliações
     */
    public function avaliacoes(): HasMany
    {
        return $this->hasMany(Avaliacao::class);
    }

    // ==========================================
    // 🔥 MÉTODOS DE VALIDAÇÃO DE DISPONIBILIDADE
    // ==========================================

    /**
     * 🔥 Validar se o prestador está disponível para este pedido
     */
    public function validarDisponibilidadePrestador(): bool
    {
        if (!$this->prestador_id || !$this->agendado_para) {
            return true;
        }

        $data = Carbon::parse($this->agendado_para)->format('Y-m-d');
        $hora = Carbon::parse($this->agendado_para)->format('H:i');

        // Verificar se o prestador está bloqueado na agenda
        $bloqueado = Agenda::where('prestador_id', $this->prestador_id)
            ->where('data', $data)
            ->where('horario_inicio', '<=', $hora)
            ->where('horario_fim', '>=', $hora)
            ->where('bloqueado', true)
            ->exists();

        if ($bloqueado) {
            throw new \Exception('O prestador não está disponível para esta data/hora');
        }

        // Verificar se já tem pedido na mesma data/hora
        $ocupado = self::where('prestador_id', $this->prestador_id)
            ->where('agendado_para', $this->agendado_para)
            ->whereIn('status', ['aceito', 'em_andamento'])
            ->where('id', '!=', $this->id ?? 0)
            ->exists();

        if ($ocupado) {
            throw new \Exception('O prestador já tem um serviço agendado para esta data/hora');
        }

        return true;
    }

    /**
     * 🔥 Verificar se o pedido conflita com a agenda do prestador
     */
    public function verificarConflitoAgenda(): bool
    {
        if (!$this->prestador_id || !$this->agendado_para) {
            return false;
        }

        $data = Carbon::parse($this->agendado_para)->format('Y-m-d');
        $hora = Carbon::parse($this->agendado_para)->format('H:i');

        return Agenda::where('prestador_id', $this->prestador_id)
            ->where('data', $data)
            ->where('horario_inicio', '<=', $hora)
            ->where('horario_fim', '>=', $hora)
            ->where('bloqueado', true)
            ->exists();
    }

    /**
     * 🔥 Verificar se já existe pedido no mesmo horário
     */
    public function verificarConflitoHorario(): bool
    {
        if (!$this->prestador_id || !$this->agendado_para) {
            return false;
        }

        return self::where('prestador_id', $this->prestador_id)
            ->where('agendado_para', $this->agendado_para)
            ->whereIn('status', ['aceito', 'em_andamento'])
            ->where('id', '!=', $this->id ?? 0)
            ->exists();
    }

    /**
     * 🔥 Verificar se o pedido pode ser aceito (disponibilidade)
     */
    public function podeSerAceito(): bool
    {
        if ($this->status !== 'pendente') {
            return false;
        }

        try {
            return $this->validarDisponibilidadePrestador();
        } catch (\Exception $e) {
            return false;
        }
    }

    // ==========================================
    // 🔥 CRIAÇÃO DE BLOQUEIO NA AGENDA
    // ==========================================

    /**
     * 🔥 Criar bloqueio na agenda do prestador para este pedido
     */
    public function criarBloqueioAgenda(): ?Agenda
    {
        if (!$this->prestador_id || !$this->agendado_para) {
            return null;
        }

        $data = Carbon::parse($this->agendado_para)->format('Y-m-d');
        $hora = Carbon::parse($this->agendado_para)->format('H:i');

        // Verificar se já existe bloqueio
        $existe = Agenda::where('prestador_id', $this->prestador_id)
            ->where('data', $data)
            ->where('horario_inicio', $hora)
            ->where('horario_fim', $hora)
            ->exists();

        if ($existe) {
            return null;
        }

        return Agenda::create([
            'prestador_id' => $this->prestador_id,
            'data' => $data,
            'horario_inicio' => $hora,
            'horario_fim' => $hora,
            'bloqueado' => true,
            'observacao' => 'Pedido #' . $this->numero . ' - ' . ($this->cliente->nome ?? 'Cliente'),
        ]);
    }

    /**
     * 🔥 Remover bloqueio da agenda do prestador para este pedido
     */
    public function removerBloqueioAgenda(): int
    {
        if (!$this->prestador_id || !$this->agendado_para) {
            return 0;
        }

        $data = Carbon::parse($this->agendado_para)->format('Y-m-d');
        $hora = Carbon::parse($this->agendado_para)->format('H:i');

        return Agenda::where('prestador_id', $this->prestador_id)
            ->where('data', $data)
            ->where('horario_inicio', $hora)
            ->where('horario_fim', $hora)
            ->where('observacao', 'like', 'Pedido #' . $this->numero . '%')
            ->delete();
    }

    // ==========================================
    // 🔥 MÉTODOS AUXILIARES
    // ==========================================

    /**
     * Gerar número único do pedido
     */
    public static function gerarNumero(): string
    {
        $ano = date('Y');
        $mes = date('m');
        $ultimo = self::whereYear('created_at', $ano)
            ->whereMonth('created_at', $mes)
            ->max('numero');

        if ($ultimo) {
            $sequencia = intval(substr($ultimo, -4)) + 1;
        } else {
            $sequencia = 1;
        }

        return 'PED-' . $ano . $mes . str_pad($sequencia, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 🔥 Obter prestadores disponíveis para este pedido
     */
    public function getPrestadoresDisponiveisAttribute()
    {
        if (!$this->agendado_para) {
            return collect();
        }

        $data = Carbon::parse($this->agendado_para)->format('Y-m-d');
        $hora = Carbon::parse($this->agendado_para)->format('H:i');

        return User::prestadores()
            ->where('verificado', true)
            ->where('disponivel', true)
            ->whereHas('categorias', function ($query) {
                $query->where('categoria_id', $this->categoria_id);
            })
            ->whereDoesntHave('agenda', function ($query) use ($data, $hora) {
                $query->where('data', $data)
                    ->where('horario_inicio', '<=', $hora)
                    ->where('horario_fim', '>=', $hora)
                    ->where('bloqueado', true);
            })
            ->whereDoesntHave('pedidos', function ($query) use ($data, $hora) {
                $query->where('agendado_para', $data . ' ' . $hora)
                    ->whereIn('status', ['aceito', 'em_andamento']);
            })
            ->get();
    }

    /**
     * 🔥 Obter quantidade de prestadores disponíveis
     */
    public function getPrestadoresDisponiveisCountAttribute(): int
    {
        return $this->prestadores_disponiveis->count();
    }

    /**
     * 🔥 Verificar se o pedido já foi aceito por algum prestador
     */
    public function getFoiAceitoAttribute(): bool
    {
        return !is_null($this->prestador_id) && in_array($this->status, ['aceito', 'em_andamento', 'concluido']);
    }

    // ==========================================
    // 🔥 SCOPES
    // ==========================================

    /**
     * Scope para pedidos pendentes
     */
    public function scopePendentes($query)
    {
        return $query->where('status', 'pendente');
    }

    /**
     * Scope para pedidos aceitos
     */
    public function scopeAceitos($query)
    {
        return $query->where('status', 'aceito');
    }

    /**
     * Scope para pedidos em andamento
     */
    public function scopeEmAndamento($query)
    {
        return $query->where('status', 'em_andamento');
    }

    /**
     * Scope para pedidos concluídos
     */
    public function scopeConcluidos($query)
    {
        return $query->where('status', 'concluido');
    }

    /**
     * Scope para pedidos cancelados
     */
    public function scopeCancelados($query)
    {
        return $query->where('status', 'cancelado');
    }

    /**
     * 🔥 Scope para pedidos que ainda não têm prestador
     */
    public function scopeSemPrestador($query)
    {
        return $query->whereNull('prestador_id');
    }

    /**
     * 🔥 Scope para pedidos com prestador
     */
    public function scopeComPrestador($query)
    {
        return $query->whereNotNull('prestador_id');
    }

    /**
     * 🔥 Scope para pedidos de um prestador específico
     */
    public function scopeDoPrestador($query, $prestadorId)
    {
        return $query->where('prestador_id', $prestadorId);
    }

    /**
     * 🔥 Scope para pedidos de um cliente específico
     */
    public function scopeDoCliente($query, $clienteId)
    {
        return $query->where('cliente_id', $clienteId);
    }

    /**
     * 🔥 Scope para pedidos agendados em uma data específica
     */
    public function scopeAgendadosEm($query, $data)
    {
        return $query->whereDate('agendado_para', $data);
    }

    /**
     * 🔥 Scope para pedidos agendados em um período
     */
    public function scopeAgendadosEntre($query, $inicio, $fim)
    {
        return $query->whereBetween('agendado_para', [$inicio, $fim]);
    }

    /**
     * 🔥 Scope para pedidos disponíveis (pendentes e sem prestador)
     */
    public function scopeDisponiveis($query)
    {
        return $query->where('status', 'pendente')
            ->whereNull('prestador_id');
    }

    // ==========================================
    // 🔥 ACCESSORS & MUTATORS
    // ==========================================

    public function getTotalPropostasAttribute(): int
    {
        return $this->propostas()->count();
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'pendente' => 'Pendente',
            'aceito' => 'Aceito',
            'em_andamento' => 'Em Andamento',
            'concluido' => 'Concluído',
            'cancelado' => 'Cancelado',
        ];
        return $labels[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        $colors = [
            'pendente' => 'warning',
            'aceito' => 'primary',
            'em_andamento' => 'info',
            'concluido' => 'positive',
            'cancelado' => 'negative',
        ];
        return $colors[$this->status] ?? 'grey';
    }

    public function getValorFormatadoAttribute(): string
    {
        if (!$this->valor) {
            return 'A definir';
        }
        return number_format($this->valor, 2, ',', '.') . ' MZN';
    }

    public function getDataFormatadaAttribute(): string
    {
        return $this->created_at ? $this->created_at->format('d/m/Y H:i') : '—';
    }

    /**
     * 🔥 Get data agendada formatada
     */
    public function getDataAgendadaFormatadaAttribute(): string
    {
        if (!$this->agendado_para) {
            return 'A definir';
        }
        return Carbon::parse($this->agendado_para)->format('d/m/Y H:i');
    }

    /**
     * 🔥 Get data agendada para exibição em calendário
     */
    public function getDataAgendadaCalendarAttribute(): string
    {
        if (!$this->agendado_para) {
            return '';
        }
        return Carbon::parse($this->agendado_para)->format('Y-m-d H:i');
    }

    /**
     * 🔥 Get duração estimada (se houver)
     */
    public function getDuracaoAttribute(): ?int
    {
        // Se tiver serviço associado, usar a duração do serviço
        if ($this->servico) {
            return $this->servico->duracao;
        }
        return null;
    }

    // ==========================================
    // 🔥 MÉTODOS DE VERIFICAÇÃO DE STATUS
    // ==========================================

    public function isPendente(): bool
    {
        return $this->status === 'pendente';
    }

    public function isAceito(): bool
    {
        return $this->status === 'aceito';
    }

    public function isEmAndamento(): bool
    {
        return $this->status === 'em_andamento';
    }

    public function isConcluido(): bool
    {
        return $this->status === 'concluido';
    }

    public function isCancelado(): bool
    {
        return $this->status === 'cancelado';
    }

    public function isFinalizado(): bool
    {
        return in_array($this->status, ['concluido', 'cancelado']);
    }

    public function isAtivo(): bool
    {
        return in_array($this->status, ['pendente', 'aceito', 'em_andamento']);
    }

    public function podeSerCancelado(): bool
    {
        return in_array($this->status, ['pendente', 'aceito']);
    }

    public function podeSerAceitoPeloPrestador($prestadorId): bool
    {
        if ($this->status !== 'pendente') {
            return false;
        }

        if ($this->prestador_id && $this->prestador_id !== $prestadorId) {
            return false;
        }

        // Verificar se o prestador está disponível
        $data = Carbon::parse($this->agendado_para)->format('Y-m-d');
        $hora = Carbon::parse($this->agendado_para)->format('H:i');

        $bloqueado = Agenda::where('prestador_id', $prestadorId)
            ->where('data', $data)
            ->where('horario_inicio', '<=', $hora)
            ->where('horario_fim', '>=', $hora)
            ->where('bloqueado', true)
            ->exists();

        if ($bloqueado) {
            return false;
        }

        $ocupado = self::where('prestador_id', $prestadorId)
            ->where('agendado_para', $this->agendado_para)
            ->whereIn('status', ['aceito', 'em_andamento'])
            ->where('id', '!=', $this->id)
            ->exists();

        return !$ocupado;
    }

    // ==========================================
    // 🔥 MÉTODOS DE TRANSIÇÃO DE STATUS
    // ==========================================

    /**
     * 🔥 Aceitar pedido (com validação)
     */
    public function aceitar($prestadorId): bool
    {
        if (!$this->podeSerAceitoPeloPrestador($prestadorId)) {
            return false;
        }

        $this->prestador_id = $prestadorId;
        $this->status = 'aceito';
        $this->save();

        // Criar bloqueio na agenda
        $this->criarBloqueioAgenda();

        return true;
    }

    /**
     * 🔥 Iniciar serviço
     */
    public function iniciar(): bool
    {
        if ($this->status !== 'aceito') {
            return false;
        }

        $this->status = 'em_andamento';
        $this->save();

        return true;
    }

    /**
     * 🔥 Concluir serviço
     */
    public function concluir(): bool
    {
        if ($this->status !== 'em_andamento') {
            return false;
        }

        $this->status = 'concluido';
        $this->concluido_em = Carbon::now();
        $this->save();

        // Remover bloqueio da agenda
        $this->removerBloqueioAgenda();

        return true;
    }

    /**
     * 🔥 Cancelar pedido
     */
    public function cancelar($motivo = null): bool
    {
        if (!$this->podeSerCancelado()) {
            return false;
        }

        $this->status = 'cancelado';
        $this->save();

        // Remover bloqueio da agenda
        $this->removerBloqueioAgenda();

        return true;
    }

    /**
     * 🔥 Reabrir pedido (se estiver cancelado)
     */
    public function reabrir(): bool
    {
        if ($this->status !== 'cancelado') {
            return false;
        }

        $this->status = 'pendente';
        $this->prestador_id = null;
        $this->save();

        return true;
    }
}
