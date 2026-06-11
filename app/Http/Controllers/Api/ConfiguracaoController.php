<?php
// app/Http/Controllers/Api/ConfiguracaoController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Configuracoes;  // ← CORRETO: Configuracoes (singular)
use Illuminate\Support\Facades\DB;

class ConfiguracaoController extends Controller
{
    public function getPrestadorConfig()
    {
        try {
            $configs = Configuracoes::getByGroup('prestador');  // ← CORRETO

            return response()->json([
                'success' => true,
                'data' => [
                    'raios_atendimento' => $configs['prestador.raios_atendimento'] ?? [],
                    'dias_semana' => $configs['prestador.dias_semana'] ?? [],
                    'disponibilidade_padrao' => $configs['prestador.disponibilidade_padrao'] ?? [],
                    'documentos_aceitos' => $configs['prestador.documentos_aceitos'] ?? [],
                    'max_file_size' => $configs['prestador.max_file_size'] ?? 5,
                    'max_portfolio_photos' => $configs['prestador.max_portfolio_photos'] ?? 10,
                    'min_portfolio_photos' => $configs['prestador.min_portfolio_photos'] ?? 3,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar configurações: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/configuracoes/raio-options
     */
    public function raioOptions()
    {
        try {
            $raios = Configuracoes::get('prestador.raios_atendimento');  // ← CORRETO

            if (!$raios) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuração de raios não encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $raios
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar opções de raio'
            ], 500);
        }
    }

    /**
     * GET /api/configuracoes/ordenacao-options
     */
    public function ordenacaoOptions()
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['label' => 'Mais próximos', 'value' => 'distancia'],
                ['label' => 'Mais recentes', 'value' => 'data'],
                ['label' => 'Menor preço', 'value' => 'preco_asc'],
                ['label' => 'Maior preço', 'value' => 'preco_desc'],
            ]
        ]);
    }

    public function show(string $chave)
    {
        try {
            $valor = Configuracoes::get($chave);  // ← CORRETO

            if ($valor === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuração não encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'chave' => $chave,
                    'valor' => $valor
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar configuração'
            ], 500);
        }
    }

    public function byGroup(string $grupo)
    {
        try {
            $configs = Configuracoes::getByGroup($grupo);  // ← CORRETO

            return response()->json([
                'success' => true,
                'data' => $configs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar configurações do grupo'
            ], 500);
        }
    }
}
