<?php

namespace Database\Seeders;

use App\Models\Configuracoes;
use Illuminate\Database\Seeder;

class ConfiguracaoSeeder extends Seeder
{
    public function run(): void
    {
        // ==========================================
        // CONFIGURAÇÕES GERAIS
        // ==========================================

        // Nome do sistema
        Configuracoes::updateOrCreate(
            ['chave' => 'nome_sistema', 'grupo' => 'geral'],
            [
                'valor' => 'Estou Aqui',
                'tipo' => 'string',
                'descricao' => 'Nome do sistema',
                'ativo' => true,
            ]
        );

        // Logo
        Configuracoes::updateOrCreate(
            ['chave' => 'logo', 'grupo' => 'geral'],
            [
                'valor' => '',
                'tipo' => 'string',
                'descricao' => 'Logo do sistema',
                'ativo' => true,
            ]
        );

        // Favicon
        Configuracoes::updateOrCreate(
            ['chave' => 'favicon', 'grupo' => 'geral'],
            [
                'valor' => '',
                'tipo' => 'string',
                'descricao' => 'Favicon do sistema',
                'ativo' => true,
            ]
        );

        // Email de contato
        Configuracoes::updateOrCreate(
            ['chave' => 'email_contato', 'grupo' => 'geral'],
            [
                'valor' => 'contato@estouaqui.co.mz',
                'tipo' => 'string',
                'descricao' => 'Email de contato do sistema',
                'ativo' => true,
            ]
        );

        // Telefone de contato
        Configuracoes::updateOrCreate(
            ['chave' => 'telefone_contato', 'grupo' => 'geral'],
            [
                'valor' => '+258841234567',
                'tipo' => 'string',
                'descricao' => 'Telefone de contato',
                'ativo' => true,
            ]
        );

        // WhatsApp
        Configuracoes::updateOrCreate(
            ['chave' => 'whatsapp_contato', 'grupo' => 'geral'],
            [
                'valor' => '+258841234567',
                'tipo' => 'string',
                'descricao' => 'WhatsApp de contato',
                'ativo' => true,
            ]
        );

        // Endereço
        Configuracoes::updateOrCreate(
            ['chave' => 'endereco', 'grupo' => 'geral'],
            [
                'valor' => 'Maputo, Moçambique',
                'tipo' => 'string',
                'descricao' => 'Endereço da empresa',
                'ativo' => true,
            ]
        );

        // Modo manutenção
        Configuracoes::updateOrCreate(
            ['chave' => 'manutencao', 'grupo' => 'geral'],
            [
                'valor' => 'false',
                'tipo' => 'boolean',
                'descricao' => 'Ativar/Desativar modo manutenção',
                'ativo' => true,
            ]
        );

        // Mensagem de manutenção
        Configuracoes::updateOrCreate(
            ['chave' => 'mensagem_manutencao', 'grupo' => 'geral'],
            [
                'valor' => 'Sistema em manutenção. Voltaremos em breve!',
                'tipo' => 'string',
                'descricao' => 'Mensagem exibida durante manutenção',
                'ativo' => true,
            ]
        );

        // Tempo de sessão (minutos)
        Configuracoes::updateOrCreate(
            ['chave' => 'tempo_sessao', 'grupo' => 'geral'],
            [
                'valor' => '120',
                'tipo' => 'integer',
                'descricao' => 'Tempo de sessão em minutos',
                'ativo' => true,
            ]
        );

        // Máximo tentativas login
        Configuracoes::updateOrCreate(
            ['chave' => 'max_tentativas_login', 'grupo' => 'geral'],
            [
                'valor' => '5',
                'tipo' => 'integer',
                'descricao' => 'Máximo de tentativas de login antes do bloqueio',
                'ativo' => true,
            ]
        );

        // Registro automático
        Configuracoes::updateOrCreate(
            ['chave' => 'registro_automatico', 'grupo' => 'geral'],
            [
                'valor' => 'true',
                'tipo' => 'boolean',
                'descricao' => 'Permitir registro automático de novos usuários',
                'ativo' => true,
            ]
        );

        // Verificação de email
        Configuracoes::updateOrCreate(
            ['chave' => 'verificacao_email', 'grupo' => 'geral'],
            [
                'valor' => 'true',
                'tipo' => 'boolean',
                'descricao' => 'Exigir verificação de email',
                'ativo' => true,
            ]
        );

        // Notificações por email
        Configuracoes::updateOrCreate(
            ['chave' => 'notificacoes_email', 'grupo' => 'geral'],
            [
                'valor' => 'true',
                'tipo' => 'boolean',
                'descricao' => 'Ativar notificações por email',
                'ativo' => true,
            ]
        );

        // Notificações push
        Configuracoes::updateOrCreate(
            ['chave' => 'notificacoes_push', 'grupo' => 'geral'],
            [
                'valor' => 'true',
                'tipo' => 'boolean',
                'descricao' => 'Ativar notificações push',
                'ativo' => true,
            ]
        );

        // SMTP Host
        Configuracoes::updateOrCreate(
            ['chave' => 'smtp_host', 'grupo' => 'geral'],
            [
                'valor' => '',
                'tipo' => 'string',
                'descricao' => 'Servidor SMTP',
                'ativo' => true,
            ]
        );

        // SMTP Porta
        Configuracoes::updateOrCreate(
            ['chave' => 'smtp_porta', 'grupo' => 'geral'],
            [
                'valor' => '587',
                'tipo' => 'integer',
                'descricao' => 'Porta SMTP',
                'ativo' => true,
            ]
        );

        // SMTP Usuário
        Configuracoes::updateOrCreate(
            ['chave' => 'smtp_usuario', 'grupo' => 'geral'],
            [
                'valor' => '',
                'tipo' => 'string',
                'descricao' => 'Usuário SMTP',
                'ativo' => true,
            ]
        );

        // SMTP Senha
        Configuracoes::updateOrCreate(
            ['chave' => 'smtp_senha', 'grupo' => 'geral'],
            [
                'valor' => '',
                'tipo' => 'string',
                'descricao' => 'Senha SMTP',
                'ativo' => true,
            ]
        );

        // SMTP Criptografia
        Configuracoes::updateOrCreate(
            ['chave' => 'smtp_criptografia', 'grupo' => 'geral'],
            [
                'valor' => 'tls',
                'tipo' => 'string',
                'descricao' => 'Criptografia SMTP',
                'ativo' => true,
            ]
        );

        // Comissão do sistema
        Configuracoes::updateOrCreate(
            ['chave' => 'comissao', 'grupo' => 'geral'],
            [
                'valor' => '10',
                'tipo' => 'float',
                'descricao' => 'Comissão do sistema sobre pedidos (%)',
                'ativo' => true,
            ]
        );

        // Valor mínimo para saque
        Configuracoes::updateOrCreate(
            ['chave' => 'valor_minimo_saque', 'grupo' => 'geral'],
            [
                'valor' => '500',
                'tipo' => 'float',
                'descricao' => 'Valor mínimo para solicitar saque (MZN)',
                'ativo' => true,
            ]
        );

        // Pagamento automático
        Configuracoes::updateOrCreate(
            ['chave' => 'pagamento_automatico', 'grupo' => 'geral'],
            [
                'valor' => 'false',
                'tipo' => 'boolean',
                'descricao' => 'Ativar pagamento automático',
                'ativo' => true,
            ]
        );

        // Moeda padrão
        Configuracoes::updateOrCreate(
            ['chave' => 'moeda', 'grupo' => 'geral'],
            [
                'valor' => 'MZN',
                'tipo' => 'string',
                'descricao' => 'Moeda padrão do sistema',
                'ativo' => true,
            ]
        );

        // ✅ Opções de moeda (CORRIGIDO - chave correta)
        Configuracoes::updateOrCreate(
            ['chave' => 'opcoes_moeda', 'grupo' => 'geral'],
            [
                'valor' => json_encode([
                    ['label' => 'MZN - Metical', 'value' => 'MZN'],
                    ['label' => 'USD - Dólar Americano', 'value' => 'USD'],
                    ['label' => 'EUR - Euro', 'value' => 'EUR'],
                    ['label' => 'ZAR - Rand Sul-Africano', 'value' => 'ZAR'],
                    ['label' => 'BRL - Real Brasileiro', 'value' => 'BRL'],
                ]),
                'tipo' => 'array',
                'descricao' => 'Opções de moeda disponíveis',
                'ativo' => true,
            ]
        );

        // Fuso horário padrão
        Configuracoes::updateOrCreate(
            ['chave' => 'fuso_horario', 'grupo' => 'geral'],
            [
                'valor' => 'Africa/Maputo',
                'tipo' => 'string',
                'descricao' => 'Fuso horário padrão',
                'ativo' => true,
            ]
        );

        // ✅ Opções de fuso horário (CORRIGIDO - chave correta)
        Configuracoes::updateOrCreate(
            ['chave' => 'opcoes_fuso_horario', 'grupo' => 'geral'],
            [
                'valor' => json_encode([
                    ['label' => 'Africa/Maputo', 'value' => 'Africa/Maputo'],
                    ['label' => 'Africa/Johannesburg', 'value' => 'Africa/Johannesburg'],
                    ['label' => 'Africa/Luanda', 'value' => 'Africa/Luanda'],
                    ['label' => 'Europe/Lisbon', 'value' => 'Europe/Lisbon'],
                    ['label' => 'America/Sao_Paulo', 'value' => 'America/Sao_Paulo'],
                    ['label' => 'UTC', 'value' => 'UTC'],
                ]),
                'tipo' => 'array',
                'descricao' => 'Opções de fuso horário disponíveis',
                'ativo' => true,
            ]
        );

        // ✅ Opções de criptografia SMTP (CORRIGIDO - chave correta)
        Configuracoes::updateOrCreate(
            ['chave' => 'opcoes_criptografia', 'grupo' => 'geral'],
            [
                'valor' => json_encode([
                    ['label' => 'TLS', 'value' => 'tls'],
                    ['label' => 'SSL', 'value' => 'ssl'],
                    ['label' => 'Nenhuma', 'value' => 'none'],
                ]),
                'tipo' => 'array',
                'descricao' => 'Opções de criptografia SMTP',
                'ativo' => true,
            ]
        );

        // ✅ Opções de módulos do sistema (CORRIGIDO - chave correta)
        Configuracoes::updateOrCreate(
            ['chave' => 'opcoes_modulos', 'grupo' => 'geral'],
            [
                'valor' => json_encode([
                    ['label' => 'Dashboard', 'value' => 'dashboard'],
                    ['label' => 'Pedidos', 'value' => 'pedidos'],
                    ['label' => 'Prestadores', 'value' => 'prestadores'],
                    ['label' => 'Clientes', 'value' => 'clientes'],
                    ['label' => 'Categorias', 'value' => 'categorias'],
                    ['label' => 'Promoções', 'value' => 'promocoes'],
                    ['label' => 'Avaliações', 'value' => 'avaliacoes'],
                    ['label' => 'Financeiro', 'value' => 'financeiro'],
                    ['label' => 'Relatórios', 'value' => 'relatorios'],
                    ['label' => 'Configurações', 'value' => 'configuracoes'],
                    ['label' => 'Utilizadores', 'value' => 'usuarios'],
                    ['label' => 'Backups', 'value' => 'backups'],
                    ['label' => 'Logs', 'value' => 'logs'],
                    ['label' => 'Suporte', 'value' => 'suporte'],
                    ['label' => 'Monitoramento', 'value' => 'monitoramento'],
                ]),
                'tipo' => 'array',
                'descricao' => 'Módulos do sistema',
                'ativo' => true,
            ]
        );

        // Limite de pedidos por dia
        Configuracoes::updateOrCreate(
            ['chave' => 'limite_pedidos_por_dia', 'grupo' => 'geral'],
            [
                'valor' => '10',
                'tipo' => 'integer',
                'descricao' => 'Limite máximo de pedidos por dia',
                'ativo' => true,
            ]
        );

        // Tempo para cancelamento
        Configuracoes::updateOrCreate(
            ['chave' => 'tempo_cancelamento_pedido', 'grupo' => 'geral'],
            [
                'valor' => '30',
                'tipo' => 'integer',
                'descricao' => 'Tempo em minutos para cancelamento',
                'ativo' => true,
            ]
        );

        // Nota mínima avaliação
        Configuracoes::updateOrCreate(
            ['chave' => 'nota_minima_avaliacao', 'grupo' => 'geral'],
            [
                'valor' => '1',
                'tipo' => 'integer',
                'descricao' => 'Nota mínima para avaliação',
                'ativo' => true,
            ]
        );

        // Nota máxima avaliação
        Configuracoes::updateOrCreate(
            ['chave' => 'nota_maxima_avaliacao', 'grupo' => 'geral'],
            [
                'valor' => '5',
                'tipo' => 'integer',
                'descricao' => 'Nota máxima para avaliação',
                'ativo' => true,
            ]
        );

        // ==========================================
        // CONFIGURAÇÕES DE PRESTADOR
        // ==========================================

        // ✅ Opções de raios de atendimento (NOVO - chave correta)
        Configuracoes::updateOrCreate(
            ['chave' => 'opcoes_raios', 'grupo' => 'geral'],
            [
                'valor' => json_encode([
                    ['label' => '5 km', 'value' => 5],
                    ['label' => '10 km', 'value' => 10],
                    ['label' => '15 km', 'value' => 15],
                    ['label' => '20 km', 'value' => 20],
                    ['label' => '25 km', 'value' => 25],
                    ['label' => '30 km', 'value' => 30],
                    ['label' => '40 km', 'value' => 40],
                    ['label' => '50 km', 'value' => 50],
                    ['label' => '100 km', 'value' => 100],
                ]),
                'tipo' => 'array',
                'descricao' => 'Opções de raios de atendimento disponíveis (km)',
                'ativo' => true,
            ]
        );

        // Raios de atendimento disponíveis (valor padrão)
        Configuracoes::updateOrCreate(
            ['chave' => 'raios_atendimento', 'grupo' => 'prestador'],
            [
                'valor' => json_encode([5, 10, 15, 20, 25, 30, 50]),
                'tipo' => 'array',
                'descricao' => 'Raios de atendimento disponíveis para prestadores (km)',
                'ativo' => true,
            ]
        );

        // Raio padrão
        Configuracoes::updateOrCreate(
            ['chave' => 'raio_padrao', 'grupo' => 'prestador'],
            [
                'valor' => '15',
                'tipo' => 'integer',
                'descricao' => 'Raio padrão de atendimento (km)',
                'ativo' => true,
            ]
        );

        // Distância máxima
        Configuracoes::updateOrCreate(
            ['chave' => 'distancia_maxima', 'grupo' => 'prestador'],
            [
                'valor' => '50',
                'tipo' => 'integer',
                'descricao' => 'Distância máxima para atendimento (km)',
                'ativo' => true,
            ]
        );

        // ✅ Opções de dias da semana (NOVO - chave correta)
        Configuracoes::updateOrCreate(
            ['chave' => 'opcoes_dias_semana', 'grupo' => 'geral'],
            [
                'valor' => json_encode([
                    ['label' => 'Segunda-feira', 'value' => 'monday'],
                    ['label' => 'Terça-feira', 'value' => 'tuesday'],
                    ['label' => 'Quarta-feira', 'value' => 'wednesday'],
                    ['label' => 'Quinta-feira', 'value' => 'thursday'],
                    ['label' => 'Sexta-feira', 'value' => 'friday'],
                    ['label' => 'Sábado', 'value' => 'saturday'],
                    ['label' => 'Domingo', 'value' => 'sunday'],
                ]),
                'tipo' => 'array',
                'descricao' => 'Opções de dias da semana',
                'ativo' => true,
            ]
        );

        // Dias da semana disponíveis
        Configuracoes::updateOrCreate(
            ['chave' => 'dias_semana', 'grupo' => 'prestador'],
            [
                'valor' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
                'tipo' => 'array',
                'descricao' => 'Dias da semana para disponibilidade',
                'ativo' => true,
            ]
        );

        // Horário início padrão
        Configuracoes::updateOrCreate(
            ['chave' => 'horario_inicio', 'grupo' => 'prestador'],
            [
                'valor' => '08:00',
                'tipo' => 'string',
                'descricao' => 'Horário início padrão',
                'ativo' => true,
            ]
        );

        // Horário fim padrão
        Configuracoes::updateOrCreate(
            ['chave' => 'horario_fim', 'grupo' => 'prestador'],
            [
                'valor' => '18:00',
                'tipo' => 'string',
                'descricao' => 'Horário fim padrão',
                'ativo' => true,
            ]
        );

        // Intervalo entre atendimentos (minutos)
        Configuracoes::updateOrCreate(
            ['chave' => 'intervalo_minutos', 'grupo' => 'prestador'],
            [
                'valor' => '60',
                'tipo' => 'integer',
                'descricao' => 'Intervalo padrão entre atendimentos (minutos)',
                'ativo' => true,
            ]
        );

        // Disponibilidade padrão
        Configuracoes::updateOrCreate(
            ['chave' => 'disponibilidade_padrao', 'grupo' => 'prestador'],
            [
                'valor' => json_encode([
                    'inicio' => '08:00',
                    'fim' => '18:00',
                    'intervalo' => 60,
                ]),
                'tipo' => 'array',
                'descricao' => 'Disponibilidade padrão para novos prestadores',
                'ativo' => true,
            ]
        );

        // ✅ Opções de documentos aceitos (NOVO - chave correta)
        Configuracoes::updateOrCreate(
            ['chave' => 'opcoes_documentos', 'grupo' => 'geral'],
            [
                'valor' => json_encode([
                    ['label' => 'BI / Cartão de Cidadão', 'value' => 'bi', 'extensions' => ['jpg', 'jpeg', 'png', 'pdf']],
                    ['label' => 'NUIT', 'value' => 'nuit', 'extensions' => ['jpg', 'jpeg', 'png', 'pdf']],
                    ['label' => 'Certificado de Formação', 'value' => 'certificado', 'extensions' => ['jpg', 'jpeg', 'png', 'pdf']],
                    ['label' => 'Carta de Condução', 'value' => 'carta', 'extensions' => ['jpg', 'jpeg', 'png', 'pdf']],
                    ['label' => 'Comprovante de Residência', 'value' => 'residencia', 'extensions' => ['jpg', 'jpeg', 'png', 'pdf']],
                ]),
                'tipo' => 'array',
                'descricao' => 'Opções de documentos aceitos para verificação',
                'ativo' => true,
            ]
        );

        // Documentos aceitos
        Configuracoes::updateOrCreate(
            ['chave' => 'documentos_aceitos', 'grupo' => 'prestador'],
            [
                'valor' => json_encode(['bi', 'nuit']),
                'tipo' => 'array',
                'descricao' => 'Tipos de documentos aceitos para verificação',
                'ativo' => true,
            ]
        );

        // Tamanho máximo de arquivo (MB)
        Configuracoes::updateOrCreate(
            ['chave' => 'max_file_size', 'grupo' => 'prestador'],
            [
                'valor' => '5',
                'tipo' => 'integer',
                'descricao' => 'Tamanho máximo de arquivo para upload (MB)',
                'ativo' => true,
            ]
        );

        // Máximo de fotos no portfólio
        Configuracoes::updateOrCreate(
            ['chave' => 'max_portfolio_photos', 'grupo' => 'prestador'],
            [
                'valor' => '10',
                'tipo' => 'integer',
                'descricao' => 'Número máximo de fotos no portfólio',
                'ativo' => true,
            ]
        );

        // Mínimo de fotos no portfólio
        Configuracoes::updateOrCreate(
            ['chave' => 'min_portfolio_photos', 'grupo' => 'prestador'],
            [
                'valor' => '3',
                'tipo' => 'integer',
                'descricao' => 'Número mínimo de fotos no portfólio',
                'ativo' => true,
            ]
        );

        // Precisa aprovação manual
        Configuracoes::updateOrCreate(
            ['chave' => 'precisa_aprovacao', 'grupo' => 'prestador'],
            [
                'valor' => 'true',
                'tipo' => 'boolean',
                'descricao' => 'Prestadores precisam de aprovação manual',
                'ativo' => true,
            ]
        );

        // Tempo máximo resposta (horas)
        Configuracoes::updateOrCreate(
            ['chave' => 'tempo_resposta_maximo', 'grupo' => 'prestador'],
            [
                'valor' => '24',
                'tipo' => 'integer',
                'descricao' => 'Tempo máximo para resposta a pedidos (horas)',
                'ativo' => true,
            ]
        );

        // Comissão especial
        Configuracoes::updateOrCreate(
            ['chave' => 'comissao_especial', 'grupo' => 'prestador'],
            [
                'valor' => '0',
                'tipo' => 'float',
                'descricao' => 'Comissão especial para prestadores (%)',
                'ativo' => true,
            ]
        );

        // Bônus por avaliação 5 estrelas
        Configuracoes::updateOrCreate(
            ['chave' => 'bonus_avaliacao', 'grupo' => 'prestador'],
            [
                'valor' => '5',
                'tipo' => 'float',
                'descricao' => 'Bônus por avaliação 5 estrelas (MZN)',
                'ativo' => true,
            ]
        );

        // Tempo mínimo de serviço (minutos)
        Configuracoes::updateOrCreate(
            ['chave' => 'tempo_minimo_servico', 'grupo' => 'prestador'],
            [
                'valor' => '30',
                'tipo' => 'integer',
                'descricao' => 'Tempo mínimo para realização do serviço (minutos)',
                'ativo' => true,
            ]
        );

        // ==========================================
        // CONFIGURAÇÕES DE PAGAMENTO
        // ==========================================

        // M-Pesa ativo
        Configuracoes::updateOrCreate(
            ['chave' => 'mpesa_ativo', 'grupo' => 'pagamento'],
            [
                'valor' => 'true',
                'tipo' => 'boolean',
                'descricao' => 'Ativar pagamento via M-Pesa',
                'ativo' => true,
            ]
        );

        // Número M-Pesa
        Configuracoes::updateOrCreate(
            ['chave' => 'mpesa_numero', 'grupo' => 'pagamento'],
            [
                'valor' => '841234567',
                'tipo' => 'string',
                'descricao' => 'Número de telefone M-Pesa',
                'ativo' => true,
            ]
        );

        // Chave API M-Pesa
        Configuracoes::updateOrCreate(
            ['chave' => 'mpesa_chave', 'grupo' => 'pagamento'],
            [
                'valor' => '',
                'tipo' => 'string',
                'descricao' => 'Chave da API M-Pesa',
                'ativo' => true,
            ]
        );

        // Visa ativo
        Configuracoes::updateOrCreate(
            ['chave' => 'visa_ativo', 'grupo' => 'pagamento'],
            [
                'valor' => 'true',
                'tipo' => 'boolean',
                'descricao' => 'Aceitar pagamentos Visa',
                'ativo' => true,
            ]
        );

        // Mastercard ativo
        Configuracoes::updateOrCreate(
            ['chave' => 'mastercard_ativo', 'grupo' => 'pagamento'],
            [
                'valor' => 'true',
                'tipo' => 'boolean',
                'descricao' => 'Aceitar pagamentos Mastercard',
                'ativo' => true,
            ]
        );

        // PayPal ativo
        Configuracoes::updateOrCreate(
            ['chave' => 'paypal_ativo', 'grupo' => 'pagamento'],
            [
                'valor' => 'false',
                'tipo' => 'boolean',
                'descricao' => 'Ativar pagamento via PayPal',
                'ativo' => true,
            ]
        );

        // Email PayPal
        Configuracoes::updateOrCreate(
            ['chave' => 'paypal_email', 'grupo' => 'pagamento'],
            [
                'valor' => '',
                'tipo' => 'string',
                'descricao' => 'Email da conta PayPal',
                'ativo' => true,
            ]
        );

        // Transferência bancária ativa
        Configuracoes::updateOrCreate(
            ['chave' => 'transferencia_ativo', 'grupo' => 'pagamento'],
            [
                'valor' => 'true',
                'tipo' => 'boolean',
                'descricao' => 'Aceitar transferência bancária',
                'ativo' => true,
            ]
        );

        // Depósito ativo
        Configuracoes::updateOrCreate(
            ['chave' => 'deposito_ativo', 'grupo' => 'pagamento'],
            [
                'valor' => 'true',
                'tipo' => 'boolean',
                'descricao' => 'Aceitar depósito bancário',
                'ativo' => true,
            ]
        );

        // Parcelamento máximo
        Configuracoes::updateOrCreate(
            ['chave' => 'parcelamento_maximo', 'grupo' => 'pagamento'],
            [
                'valor' => '3',
                'tipo' => 'integer',
                'descricao' => 'Número máximo de parcelas',
                'ativo' => true,
            ]
        );

        // Juros por parcelamento
        Configuracoes::updateOrCreate(
            ['chave' => 'juros_parcelamento', 'grupo' => 'pagamento'],
            [
                'valor' => '2.5',
                'tipo' => 'float',
                'descricao' => 'Juros por parcela (%)',
                'ativo' => true,
            ]
        );

        $this->command->info('✅ Configurações inseridas/atualizadas com sucesso!');
        $this->command->info('📌 Incluídas opções para: raios, dias semana, documentos, fuso horário, moeda, criptografia e módulos');
    }
}
