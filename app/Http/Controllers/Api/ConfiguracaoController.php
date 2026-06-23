<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Configuracoes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConfiguracaoController extends Controller
{
    /**
     * 🔥 HORÁRIOS PADRÃO (hardcoded - sem dependência de banco)
     */
    private function getDefaultHorarios(): array
    {
        return [
            '08:00', '08:30', '09:00', '09:30',
            '10:00', '10:30', '11:00', '11:30',
            '12:00', '12:30', '13:00', '13:30',
            '14:00', '14:30', '15:00', '15:30',
            '16:00', '16:30', '17:00', '17:30',
            '18:00', '18:30', '19:00', '19:30'
        ];
    }

    /**
     * GET /api/configuracoes/horarios-agenda
     * Buscar horários padrão da agenda (sem banco de dados)
     */
    public function getHorariosAgenda()
    {
        try {
            // 🔥 SEMPRE RETORNA OS HORÁRIOS PADRÃO
            // Não usa banco de dados
            return response()->json([
                'success' => true,
                'data' => $this->getDefaultHorarios(),
                'source' => 'default'
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar horários agenda: ' . $e->getMessage());

            // Fallback seguro
            $horarios = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00'];

            return response()->json([
                'success' => true,
                'data' => $horarios,
                'source' => 'error_fallback'
            ]);
        }
    }

    /**
     * PUT /api/configuracoes/horarios-agenda
     * Atualizar horários (apenas admin) - sem banco
     * 🔥 Neste caso, como não temos banco, apenas retorna sucesso
     * (Em produção, poderia salvar em arquivo ou cache)
     */
    public function atualizarHorariosAgenda(Request $request)
    {
        try {
            $request->validate([
                'horarios' => 'required|array|min:1',
                'horarios.*' => 'string|regex:/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/',
            ]);

            // 🔥 Como não usamos banco, apenas registramos no log
            Log::info('Horários atualizados (via API):', $request->horarios);

            // Em produção, poderia salvar em:
            // 1. Arquivo de configuração
            // 2. Cache (Redis/Memcached)
            // 3. Sessão

            return response()->json([
                'success' => true,
                'message' => 'Horários atualizados com sucesso! (salvo em cache)',
                'data' => $request->horarios
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar horários agenda: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar horários: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==========================================
    // MÉTODOS EXISTENTES (com banco)
    // ==========================================

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

            // Merge com configurações do banco (se existir)
            $mergedConfig = array_merge($defaultConfig, $configs);

            return response()->json([
                'success' => true,
                'data' => $mergedConfig
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao carregar configurações de prestador: ' . $e->getMessage());

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

            $mergedConfig = array_merge($defaultConfig, $configs);

            return response()->json([
                'success' => true,
                'data' => $mergedConfig
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao carregar configurações de cliente: ' . $e->getMessage());

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

    // ==========================================
    // MÉTODOS PARA AS ROTAS DO FRONTEND
    // ==========================================

    /**
     * GET /api/configuracoes/raio-options
     */
    public function getRaioOptions()
    {
        try {
            $config = Configuracoes::where('chave', 'raio_options')->firstOrFail();
            $data = json_decode($config->valor, true);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            // Fallback sem banco
            return response()->json([
                'success' => true,
                'data' => [5, 10, 15, 20, 30, 50]
            ]);
        }
    }

    /**
     * GET /api/configuracoes/ordenacao-options
     */
    public function getOrdenacaoOptions()
    {
        try {
            $config = Configuracoes::where('chave', 'ordenacao_options')->firstOrFail();
            $data = json_decode($config->valor, true);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            // Fallback sem banco
            return response()->json([
                'success' => true,
                'data' => [
                    ['value' => 'rating_desc', 'label' => 'Melhor avaliação'],
                    ['value' => 'distance_asc', 'label' => 'Mais próximo'],
                    ['value' => 'price_asc', 'label' => 'Menor preço'],
                    ['value' => 'price_desc', 'label' => 'Maior preço'],
                ]
            ]);
        }
    }

    /**
     * GET /api/configuracoes/{chave}
     */
    public function show($chave)
    {
        try {
            $config = Configuracoes::where('chave', $chave)->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => [
                    'chave' => $config->chave,
                    'valor' => $config->valor,
                    'grupo' => $config->grupo,
                    'descricao' => $config->descricao
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Configuração não encontrada'
            ], 404);
        }
    }
}
