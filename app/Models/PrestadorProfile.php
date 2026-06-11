<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrestadorProfile extends Model
{
    protected $table = 'prestador_profiles';

    protected $fillable = [
        'user_id',
        'disponibilidade',
        'portfolio',
        'documento',
        'status_documento',
        'documento_verificado_em',
        'configuracoes_prestador',
    ];

    protected $casts = [
        'disponibilidade' => 'array',
        'portfolio' => 'array',
        'configuracoes_prestador' => 'array',
        'documento_verificado_em' => 'datetime',
    ];

    /**
     * Relacionamento com o usuário
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Acessor para URLs do portfólio
     */
    public function getPortfolioUrlsAttribute(): array
    {
        if (!$this->portfolio) {
            return [];
        }

        return array_map(function ($path) {
            return asset('storage/' . $path);
        }, $this->portfolio);
    }

    /**
     * Acessor para URL do documento
     */
    public function getDocumentoUrlAttribute(): ?string
    {
        return $this->documento ? asset('storage/' . $this->documento) : null;
    }
}
