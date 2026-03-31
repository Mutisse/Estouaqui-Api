<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ==========================================
            // NOTIFICAÇÕES PARA CLIENTE
            // ==========================================
            [
                'type' => 'pedido_confirmado',
                'title_pt' => 'Pedido Confirmado',
                'title_en' => 'Order Confirmed',
                'body_pt' => 'Seu pedido #{pedido_numero} foi confirmado pelo prestador.',
                'body_en' => 'Your order #{pedido_numero} has been confirmed by the service provider.',
                'icon' => 'check_circle',
                'color' => 'positive',
                'channels' => json_encode(['database', 'push', 'email']),
            ],
            [
                'type' => 'pedido_em_andamento',
                'title_pt' => 'Serviço em Andamento',
                'title_en' => 'Service in Progress',
                'body_pt' => 'O prestador iniciou o serviço do seu pedido #{pedido_numero}.',
                'body_en' => 'The service provider has started your order #{pedido_numero}.',
                'icon' => 'play_circle',
                'color' => 'info',
                'channels' => json_encode(['database', 'push']),
            ],
            [
                'type' => 'pedido_concluido',
                'title_pt' => 'Serviço Concluído',
                'title_en' => 'Service Completed',
                'body_pt' => 'Seu serviço #{pedido_numero} foi concluído. Avalie o prestador!',
                'body_en' => 'Your service #{pedido_numero} has been completed. Rate the provider!',
                'icon' => 'task_alt',
                'color' => 'positive',
                'channels' => json_encode(['database', 'push', 'email']),
            ],
            [
                'type' => 'pedido_cancelado',
                'title_pt' => 'Pedido Cancelado',
                'title_en' => 'Order Cancelled',
                'body_pt' => 'Seu pedido #{pedido_numero} foi cancelado.',
                'body_en' => 'Your order #{pedido_numero} has been cancelled.',
                'icon' => 'cancel',
                'color' => 'negative',
                'channels' => json_encode(['database', 'push', 'email']),
            ],
            [
                'type' => 'promocao_nova',
                'title_pt' => 'Nova Promoção',
                'title_en' => 'New Promotion',
                'body_pt' => '{promocao_titulo}. Use o cupom {cupom} para {desconto}% de desconto!',
                'body_en' => '{promocao_titulo}. Use coupon {cupom} for {desconto}% off!',
                'icon' => 'local_offer',
                'color' => 'warning',
                'channels' => json_encode(['database', 'push', 'email']),
            ],
            [
                'type' => 'servico_lembrete',
                'title_pt' => 'Lembrete de Serviço',
                'title_en' => 'Service Reminder',
                'body_pt' => 'Seu serviço será realizado em {horas} horas. Prepare-se!',
                'body_en' => 'Your service will be performed in {hours} hours. Get ready!',
                'icon' => 'schedule',
                'color' => 'info',
                'channels' => json_encode(['push', 'sms']),
            ],

            // ==========================================
            // NOTIFICAÇÕES PARA PRESTADOR
            // ==========================================
            [
                'type' => 'nova_solicitacao',
                'title_pt' => 'Nova Solicitação',
                'title_en' => 'New Request',
                'body_pt' => 'Você recebeu uma nova solicitação de serviço de {cliente_nome}.',
                'body_en' => 'You received a new service request from {cliente_nome}.',
                'icon' => 'notification_important',
                'color' => 'primary',
                'channels' => json_encode(['database', 'push', 'sms']),
            ],
            [
                'type' => 'solicitacao_aceita',
                'title_pt' => 'Solicitação Aceita',
                'title_en' => 'Request Accepted',
                'body_pt' => 'Sua solicitação para {cliente_nome} foi aceita.',
                'body_en' => 'Your request for {cliente_nome} has been accepted.',
                'icon' => 'check',
                'color' => 'positive',
                'channels' => json_encode(['database', 'push']),
            ],
            [
                'type' => 'solicitacao_recusada',
                'title_pt' => 'Solicitação Recusada',
                'title_en' => 'Request Declined',
                'body_pt' => 'A solicitação de {cliente_nome} foi recusada.',
                'body_en' => 'The request from {cliente_nome} has been declined.',
                'icon' => 'close',
                'color' => 'negative',
                'channels' => json_encode(['database', 'push']),
            ],
            [
                'type' => 'cliente_avaliou',
                'title_pt' => 'Nova Avaliação',
                'title_en' => 'New Rating',
                'body_pt' => '{cliente_nome} avaliou seu serviço com {nota} estrelas.',
                'body_en' => '{cliente_nome} rated your service {nota} stars.',
                'icon' => 'star',
                'color' => 'warning',
                'channels' => json_encode(['database', 'push', 'email']),
            ],
            [
                'type' => 'pagamento_recebido',
                'title_pt' => 'Pagamento Recebido',
                'title_en' => 'Payment Received',
                'body_pt' => 'Você recebeu {valor} pelo serviço #{pedido_numero}.',
                'body_en' => 'You received {valor} for service #{pedido_numero}.',
                'icon' => 'payments',
                'color' => 'positive',
                'channels' => json_encode(['database', 'push', 'email']),
            ],
            [
                'type' => 'saque_processado',
                'title_pt' => 'Saque Processado',
                'title_en' => 'Withdrawal Processed',
                'body_pt' => 'Seu saque de {valor} foi processado com sucesso.',
                'body_en' => 'Your withdrawal of {valor} has been processed successfully.',
                'icon' => 'money',
                'color' => 'positive',
                'channels' => json_encode(['database', 'email']),
            ],
            [
                'type' => 'agenda_lembrete',
                'title_pt' => 'Lembrete de Agenda',
                'title_en' => 'Schedule Reminder',
                'body_pt' => 'Você tem um serviço agendado para {data} às {hora}.',
                'body_en' => 'You have a service scheduled for {date} at {time}.',
                'icon' => 'event',
                'color' => 'info',
                'channels' => json_encode(['push', 'sms']),
            ],

            // ==========================================
            // NOTIFICAÇÕES PARA ADMIN
            // ==========================================
            [
                'type' => 'novo_prestador_pendente',
                'title_pt' => 'Novo Prestador Pendente',
                'title_en' => 'New Pending Provider',
                'body_pt' => 'O prestador {prestador_nome} aguarda aprovação.',
                'body_en' => 'Provider {prestador_nome} is waiting for approval.',
                'icon' => 'person_add',
                'color' => 'warning',
                'channels' => json_encode(['database', 'push', 'email']),
            ],
            [
                'type' => 'prestador_aprovado',
                'title_pt' => 'Prestador Aprovado',
                'title_en' => 'Provider Approved',
                'body_pt' => 'O prestador {prestador_nome} foi aprovado com sucesso.',
                'body_en' => 'Provider {prestador_nome} has been approved successfully.',
                'icon' => 'verified',
                'color' => 'positive',
                'channels' => json_encode(['database', 'email']),
            ],
            [
                'type' => 'relatorio_semanal',
                'title_pt' => 'Relatório Semanal',
                'title_en' => 'Weekly Report',
                'body_pt' => 'Relatório da semana está disponível para download.',
                'body_en' => 'Weekly report is available for download.',
                'icon' => 'assessment',
                'color' => 'primary',
                'channels' => json_encode(['database', 'email']),
            ],
            [
                'type' => 'alerta_seguranca',
                'title_pt' => 'Alerta de Segurança',
                'title_en' => 'Security Alert',
                'body_pt' => 'Atividade suspeita detectada: {descricao}',
                'body_en' => 'Suspicious activity detected: {descricao}',
                'icon' => 'security',
                'color' => 'negative',
                'channels' => json_encode(['database', 'push', 'email', 'sms']),
            ],
            [
                'type' => 'erro_sistema',
                'title_pt' => 'Erro no Sistema',
                'title_en' => 'System Error',
                'body_pt' => 'Ocorreu um erro no sistema: {erro}',
                'body_en' => 'A system error occurred: {erro}',
                'icon' => 'bug_report',
                'color' => 'negative',
                'channels' => json_encode(['database', 'email']),
            ],
        ];

        foreach ($templates as $template) {
            DB::table('notification_templates')->updateOrInsert(
                ['type' => $template['type']],
                $template
            );
        }
    }
}
