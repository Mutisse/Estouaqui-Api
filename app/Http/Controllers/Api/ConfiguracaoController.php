<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Configuracoes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConfiguracaoController extends Controller
{
    /**
     * Get prestador configurations
     * GET /api/configuracoes/prestador
     */
    public function getPrestadorConfig()
    {
        try {
            $configs = Configuracoes::getPrestadorConfig();

            // Configurações padrão para prestador
            $defaultConfig = [
                'tempo_medio_resposta' => 60,
                'raio_atendimento_maximo' => 50,
                'max_servicos_ativos' => 10,
                'comissao_plataforma' => 10,
                'dias_antecedencia_minima' => 1,
                'cancelamento_gratis_horas' => 24,
                'avaliacao_minima' => 1,
                'max_fotos_servico' => 10,
                'max_videos_servico' => 3,
                'tamanho_max_arquivo_mb' => 10,
                'formatos_imagem' => ['jpg', 'png', 'jpeg', 'webp'],
                'pagamento_automatico' => true,
                'dias_para_pagamento' => 7,
            ];

            // Merge com configurações do banco
            $mergedConfig = array_merge($defaultConfig, $configs);

            return response()->json([
                'success' => true,
                'data' => $mergedConfig
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao carregar configurações de prestador: ' . $e->getMessage());

            // Retorna configurações padrão em caso de erro
            return response()->json([
                'success' => true,
                'data' => [
                    'tempo_medio_resposta' => 60,
                    'raio_atendimento_maximo' => 50,
                    'max_servicos_ativos' => 10,
                    'comissao_plataforma' => 10,
                    'dias_antecedencia_minima' => 1,
                    'cancelamento_gratis_horas' => 24,
                    'avaliacao_minima' => 1,
                    'max_fotos_servico' => 10,
                    'max_videos_servico' => 3,
                    'tamanho_max_arquivo_mb' => 10,
                    'formatos_imagem' => ['jpg', 'png', 'jpeg', 'webp'],
                    'pagamento_automatico' => true,
                    'dias_para_pagamento' => 7,
                ]
            ]);
        }
    }

    /**
     * Get cliente configurations
     * GET /api/configuracoes/cliente
     */
    public function getClienteConfig()
    {
        try {
            $configs = Configuracoes::getClienteConfig();

            // Configurações padrão para cliente
            $defaultConfig = [
                'tempo_maximo_cancelamento_horas' => 2,
                'taxa_servico' => 5,
                'minimo_avaliacao_caracteres' => 10,
                'maximo_avaliacao_caracteres' => 500,
                'dias_para_reclamacao' => 7,
                'max_servicos_ativos' => 5,
                'notificacoes_email' => true,
                'notificacoes_push' => true,
            ];

            // Merge com configurações do banco
            $mergedConfig = array_merge($defaultConfig, $configs);

            return response()->json([
                'success' => true,
                'data' => $mergedConfig
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao carregar configurações de cliente: ' . $e->getMessage());

            // Retorna configurações padrão em caso de erro
            return response()->json([
                'success' => true,
                'data' => [
                    'tempo_maximo_cancelamento_horas' => 2,
                    'taxa_servico' => 5,
                    'minimo_avaliacao_caracteres' => 10,
                    'maximo_avaliacao_caracteres' => 500,
                    'dias_para_reclamacao' => 7,
                    'max_servicos_ativos' => 5,
                    'notificacoes_email' => true,
                    'notificacoes_push' => true,
                ]
            ]);
        }
    }

    /**
     * Get sistema configurations
     * GET /api/configuracoes/sistema
     */
    public function getSistemaConfig()
    {
        try {
            $configs = Configuracoes::getByGroup('sistema');

            // Configurações padrão do sistema
            $defaultConfig = [
                'manutencao' => false,
                'versao_app' => '1.0.0',
                'url_termos' => '/termos',
                'url_privacidade' => '/privacidade',
                'email_contato' => 'suporte@estouaqui.com',
                'telefone_contato' => '+244 900 000 000',
                'whatsapp_contato' => '+244 900 000 000',
                'taxa_plataforma' => 10,
                'moeda' => 'Kz',
                'limite_servicos_gratuitos' => 3,
            ];

            // Merge com configurações do banco
            $mergedConfig = array_merge($defaultConfig, $configs);

            return response()->json([
                'success' => true,
                'data' => $mergedConfig
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao carregar configurações do sistema: ' . $e->getMessage());

            return response()->json([
                'success' => true,
                'data' => [
                    'manutencao' => false,
                    'versao_app' => '1.0.0',
                    'url_termos' => '/termos',
                    'url_privacidade' => '/privacidade',
                    'email_contato' => 'suporte@estouaqui.com',
                    'telefone_contato' => '+244 900 000 000',
                    'whatsapp_contato' => '+244 900 000 000',
                    'taxa_plataforma' => 10,
                    'moeda' => 'Kz',
                    'limite_servicos_gratuitos' => 3,
                ]
            ]);
        }
    }

    /**
     * Update configuration
     * PUT /api/configuracoes/{chave}
     */
    public function update(Request $request, string $chave)
    {
        $request->validate([
            'valor' => 'required',
            'grupo' => 'sometimes|string',
            'descricao' => 'nullable|string'
        ]);

        try {
            $grupo = $request->get('grupo', 'geral');
            $descricao = $request->get('descricao');

            $config = Configuracoes::set($chave, $request->valor, $grupo, $descricao);

            return response()->json([
                'success' => true,
                'message' => 'Configuração atualizada com sucesso',
                'data' => $config
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar configuração: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar configuração',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all configurations by group
     * GET /api/configuracoes/grupo/{grupo}
     */
    public function getByGroup(string $grupo)
    {
        try {
            $configs = Configuracoes::getByGroup($grupo);

            return response()->json([
                'success' => true,
                'data' => $configs
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar configurações por grupo: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar configurações',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
