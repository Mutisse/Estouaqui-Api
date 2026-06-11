<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Configuracoes extends Model
{
    use HasFactory;

    protected $table = 'configuracoes';

    protected $fillable = [
        'grupo',
        'chave',
        'valor',
        'tipo',
        'descricao',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Buscar configuração por chave - SEM CACHE
     */
    public static function get(string $chave, $default = null, ?string $grupo = null)
    {
        try {
            $query = self::where('chave', $chave)->where('ativo', true);

            if ($grupo) {
                $query->where('grupo', $grupo);
            }

            $config = $query->first();

            if (!$config) {
                return $default;
            }

            $valor = $config->valor;
            $tipo = $config->tipo;

            switch ($tipo) {
                case 'boolean':
                    return filter_var($valor, FILTER_VALIDATE_BOOLEAN);
                case 'integer':
                    return (int) $valor;
                case 'float':
                    return (float) $valor;
                case 'array':
                case 'json':
                    return json_decode($valor, true);
                default:
                    return $valor;
            }
        } catch (\Exception $e) {
            Log::error('Erro ao buscar configuração: ' . $e->getMessage());
            return $default;
        }
    }

    /**
     * Definir configuração
     */
    public static function set(string $chave, $valor, string $grupo = 'geral', ?string $descricao = null, ?string $tipo = null): self
    {
        if (!$tipo) {
            if (is_bool($valor)) {
                $tipo = 'boolean';
                $valor = $valor ? 'true' : 'false';
            } elseif (is_int($valor)) {
                $tipo = 'integer';
                $valor = (string) $valor;
            } elseif (is_float($valor)) {
                $tipo = 'float';
                $valor = (string) $valor;
            } elseif (is_array($valor)) {
                $tipo = 'array';
                $valor = json_encode($valor);
            } else {
                $tipo = 'string';
                $valor = (string) $valor;
            }
        }

        return self::updateOrCreate(
            ['chave' => $chave, 'grupo' => $grupo],
            [
                'valor' => $valor,
                'tipo' => $tipo,
                'descricao' => $descricao,
                'ativo' => true,
            ]
        );
    }

    public function scopeDoGrupo($query, string $grupo)
    {
        return $query->where('grupo', $grupo);
    }

    public function scopeDaChave($query, string $chave)
    {
        return $query->where('chave', $chave);
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }
}
