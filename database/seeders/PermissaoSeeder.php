<?php
// database/seeders/PermissaoSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PermissaoSeeder extends Seeder
{
    public function run(): void
    {
        $permissoes = [
            // ========== DASHBOARD ==========
            ['nome' => 'Ver Dashboard', 'descricao' => 'Acessar o dashboard principal', 'modulo' => 'dashboard'],
            ['nome' => 'Ver Estatísticas', 'descricao' => 'Acessar estatísticas do sistema', 'modulo' => 'dashboard'],

            // ========== UTILIZADORES ==========
            ['nome' => 'Ver Utilizadores', 'descricao' => 'Listar todos os utilizadores', 'modulo' => 'utilizadores'],
            ['nome' => 'Criar Utilizador', 'descricao' => 'Criar novos utilizadores', 'modulo' => 'utilizadores'],
            ['nome' => 'Editar Utilizador', 'descricao' => 'Editar dados de utilizadores', 'modulo' => 'utilizadores'],
            ['nome' => 'Eliminar Utilizador', 'descricao' => 'Eliminar utilizadores', 'modulo' => 'utilizadores'],
            ['nome' => 'Verificar Utilizador', 'descricao' => 'Verificar prestadores', 'modulo' => 'utilizadores'],
            ['nome' => 'Bloquear Utilizador', 'descricao' => 'Bloquear/Desbloquear utilizadores', 'modulo' => 'utilizadores'],

            // ========== PRESTADORES ==========
            ['nome' => 'Ver Prestadores', 'descricao' => 'Listar todos os prestadores', 'modulo' => 'prestadores'],
            ['nome' => 'Verificar Prestador', 'descricao' => 'Verificar conta de prestador', 'modulo' => 'prestadores'],
            ['nome' => 'Ativar Prestador', 'descricao' => 'Ativar prestador', 'modulo' => 'prestadores'],
            ['nome' => 'Desativar Prestador', 'descricao' => 'Desativar prestador', 'modulo' => 'prestadores'],
            ['nome' => 'Editar Prestador', 'descricao' => 'Editar dados do prestador', 'modulo' => 'prestadores'],
            ['nome' => 'Ver Profissões', 'descricao' => 'Listar profissões', 'modulo' => 'prestadores'],

            // ========== CATEGORIAS ==========
            ['nome' => 'Ver Categorias', 'descricao' => 'Listar categorias', 'modulo' => 'categorias'],
            ['nome' => 'Criar Categoria', 'descricao' => 'Criar nova categoria', 'modulo' => 'categorias'],
            ['nome' => 'Editar Categoria', 'descricao' => 'Editar categoria', 'modulo' => 'categorias'],
            ['nome' => 'Eliminar Categoria', 'descricao' => 'Eliminar categoria', 'modulo' => 'categorias'],
            ['nome' => 'Alternar Status Categoria', 'descricao' => 'Ativar/Desativar categoria', 'modulo' => 'categorias'],
            ['nome' => 'Reordenar Categorias', 'descricao' => 'Alterar ordem das categorias', 'modulo' => 'categorias'],

            // ========== PEDIDOS ==========
            ['nome' => 'Ver Pedidos', 'descricao' => 'Listar todos os pedidos', 'modulo' => 'pedidos'],
            ['nome' => 'Ver Detalhes Pedido', 'descricao' => 'Ver detalhes de um pedido', 'modulo' => 'pedidos'],
            ['nome' => 'Atualizar Status Pedido', 'descricao' => 'Alterar status do pedido', 'modulo' => 'pedidos'],
            ['nome' => 'Cancelar Pedido', 'descricao' => 'Cancelar pedido', 'modulo' => 'pedidos'],
            ['nome' => 'Eliminar Pedido', 'descricao' => 'Eliminar pedido', 'modulo' => 'pedidos'],
            ['nome' => 'Ver Propostas', 'descricao' => 'Ver propostas de pedidos', 'modulo' => 'pedidos'],
            ['nome' => 'Aceitar Proposta', 'descricao' => 'Aceitar proposta de pedido', 'modulo' => 'pedidos'],
            ['nome' => 'Recusar Proposta', 'descricao' => 'Recusar proposta de pedido', 'modulo' => 'pedidos'],
            ['nome' => 'Ver Estatísticas Pedidos', 'descricao' => 'Ver estatísticas de pedidos', 'modulo' => 'pedidos'],

            // ========== SERVIÇOS ==========
            ['nome' => 'Ver Serviços', 'descricao' => 'Listar serviços', 'modulo' => 'servicos'],
            ['nome' => 'Criar Serviço', 'descricao' => 'Criar novo serviço', 'modulo' => 'servicos'],
            ['nome' => 'Editar Serviço', 'descricao' => 'Editar serviço', 'modulo' => 'servicos'],
            ['nome' => 'Eliminar Serviço', 'descricao' => 'Eliminar serviço', 'modulo' => 'servicos'],
            ['nome' => 'Alternar Status Serviço', 'descricao' => 'Ativar/Desativar serviço', 'modulo' => 'servicos'],
            ['nome' => 'Ver Estatísticas Serviços', 'descricao' => 'Ver estatísticas de serviços', 'modulo' => 'servicos'],

            // ========== AVALIAÇÕES ==========
            ['nome' => 'Ver Avaliações', 'descricao' => 'Listar avaliações', 'modulo' => 'avaliacoes'],
            ['nome' => 'Aprovar Avaliação', 'descricao' => 'Aprovar avaliação', 'modulo' => 'avaliacoes'],
            ['nome' => 'Rejeitar Avaliação', 'descricao' => 'Rejeitar avaliação', 'modulo' => 'avaliacoes'],
            ['nome' => 'Eliminar Avaliação', 'descricao' => 'Eliminar avaliação', 'modulo' => 'avaliacoes'],
            ['nome' => 'Ver Estatísticas Avaliações', 'descricao' => 'Ver estatísticas de avaliações', 'modulo' => 'avaliacoes'],

            // ========== FINANCEIRO ==========
            ['nome' => 'Ver Financeiro', 'descricao' => 'Acessar módulo financeiro', 'modulo' => 'financeiro'],
            ['nome' => 'Ver Transações', 'descricao' => 'Listar transações', 'modulo' => 'financeiro'],
            ['nome' => 'Aprovar Saque', 'descricao' => 'Aprovar solicitação de saque', 'modulo' => 'financeiro'],
            ['nome' => 'Recusar Saque', 'descricao' => 'Recusar solicitação de saque', 'modulo' => 'financeiro'],
            ['nome' => 'Exportar Financeiro', 'descricao' => 'Exportar dados financeiros', 'modulo' => 'financeiro'],

            // ========== PROMOÇÕES ==========
            ['nome' => 'Ver Promoções', 'descricao' => 'Listar promoções', 'modulo' => 'promocoes'],
            ['nome' => 'Criar Promoção', 'descricao' => 'Criar nova promoção', 'modulo' => 'promocoes'],
            ['nome' => 'Editar Promoção', 'descricao' => 'Editar promoção', 'modulo' => 'promocoes'],
            ['nome' => 'Eliminar Promoção', 'descricao' => 'Eliminar promoção', 'modulo' => 'promocoes'],
            ['nome' => 'Alternar Status Promoção', 'descricao' => 'Ativar/Desativar promoção', 'modulo' => 'promocoes'],
            ['nome' => 'Ver Estatísticas Promoções', 'descricao' => 'Ver estatísticas de promoções', 'modulo' => 'promocoes'],

            // ========== NOTIFICAÇÕES ==========
            ['nome' => 'Ver Notificações', 'descricao' => 'Listar notificações', 'modulo' => 'notificacoes'],
            ['nome' => 'Enviar Notificação', 'descricao' => 'Enviar notificação', 'modulo' => 'notificacoes'],
            ['nome' => 'Eliminar Notificação', 'descricao' => 'Eliminar notificação', 'modulo' => 'notificacoes'],
            ['nome' => 'Marcar Lida', 'descricao' => 'Marcar notificação como lida', 'modulo' => 'notificacoes'],
            ['nome' => 'Ver Estatísticas Notificações', 'descricao' => 'Ver estatísticas de notificações', 'modulo' => 'notificacoes'],
            ['nome' => 'Ver Templates', 'descricao' => 'Ver templates de notificações', 'modulo' => 'notificacoes'],

            // ========== BACKUPS ==========
            ['nome' => 'Ver Backups', 'descricao' => 'Listar backups', 'modulo' => 'backups'],
            ['nome' => 'Criar Backup', 'descricao' => 'Criar novo backup', 'modulo' => 'backups'],
            ['nome' => 'Restaurar Backup', 'descricao' => 'Restaurar backup', 'modulo' => 'backups'],
            ['nome' => 'Eliminar Backup', 'descricao' => 'Eliminar backup', 'modulo' => 'backups'],
            ['nome' => 'Baixar Backup', 'descricao' => 'Baixar backup', 'modulo' => 'backups'],
            ['nome' => 'Ver Estatísticas Backups', 'descricao' => 'Ver estatísticas de backups', 'modulo' => 'backups'],
            ['nome' => 'Ver Configurações Backup', 'descricao' => 'Ver configurações de backup', 'modulo' => 'backups'],
            ['nome' => 'Editar Configurações Backup', 'descricao' => 'Editar configurações de backup', 'modulo' => 'backups'],

            // ========== LOGS ==========
            ['nome' => 'Ver Logs', 'descricao' => 'Visualizar logs do sistema', 'modulo' => 'logs'],
            ['nome' => 'Exportar Logs', 'descricao' => 'Exportar logs', 'modulo' => 'logs'],
            ['nome' => 'Limpar Logs', 'descricao' => 'Limpar logs antigos', 'modulo' => 'logs'],
            ['nome' => 'Ver Estatísticas Logs', 'descricao' => 'Ver estatísticas de logs', 'modulo' => 'logs'],

            // ========== MONITORAMENTO ==========
            ['nome' => 'Ver Monitoramento', 'descricao' => 'Acessar monitoramento', 'modulo' => 'monitoramento'],
            ['nome' => 'Ver Status', 'descricao' => 'Ver status do sistema', 'modulo' => 'monitoramento'],
            ['nome' => 'Ver Alertas', 'descricao' => 'Ver alertas do sistema', 'modulo' => 'monitoramento'],
            ['nome' => 'Marcar Alerta Lido', 'descricao' => 'Marcar alerta como lido', 'modulo' => 'monitoramento'],
            ['nome' => 'Testar Serviço', 'descricao' => 'Testar serviço', 'modulo' => 'monitoramento'],

            // ========== PERFORMANCE ==========
            ['nome' => 'Ver Performance', 'descricao' => 'Acessar performance', 'modulo' => 'performance'],
            ['nome' => 'Ver Métricas', 'descricao' => 'Ver métricas de performance', 'modulo' => 'performance'],
            ['nome' => 'Ver Histórico', 'descricao' => 'Ver histórico de performance', 'modulo' => 'performance'],

            // ========== SUPORTE ==========
            ['nome' => 'Ver Tickets', 'descricao' => 'Listar tickets de suporte', 'modulo' => 'suporte'],
            ['nome' => 'Ver Detalhes Ticket', 'descricao' => 'Ver detalhes do ticket', 'modulo' => 'suporte'],
            ['nome' => 'Responder Ticket', 'descricao' => 'Responder ticket', 'modulo' => 'suporte'],
            ['nome' => 'Alterar Status Ticket', 'descricao' => 'Alterar status do ticket', 'modulo' => 'suporte'],
            ['nome' => 'Eliminar Ticket', 'descricao' => 'Eliminar ticket', 'modulo' => 'suporte'],
            ['nome' => 'Ver Estatísticas Suporte', 'descricao' => 'Ver estatísticas de suporte', 'modulo' => 'suporte'],

            // ========== CONFIGURAÇÕES ==========
            ['nome' => 'Ver Configurações', 'descricao' => 'Ver configurações do sistema', 'modulo' => 'configuracoes'],
            ['nome' => 'Editar Configurações', 'descricao' => 'Editar configurações', 'modulo' => 'configuracoes'],
            ['nome' => 'Ver Permissões', 'descricao' => 'Ver permissões', 'modulo' => 'permissoes'],
            ['nome' => 'Editar Permissões', 'descricao' => 'Editar permissões', 'modulo' => 'permissoes'],
            ['nome' => 'Ver Roles', 'descricao' => 'Ver papéis', 'modulo' => 'roles'],
            ['nome' => 'Editar Roles', 'descricao' => 'Editar papéis', 'modulo' => 'roles'],

            // ========== RELATÓRIOS ==========
            ['nome' => 'Ver Relatórios', 'descricao' => 'Acessar relatórios', 'modulo' => 'relatorios'],
            ['nome' => 'Exportar Relatórios', 'descricao' => 'Exportar relatórios', 'modulo' => 'relatorios'],
        ];

        foreach ($permissoes as $permissao) {
            DB::table('permissoes')->insert([
                'nome' => $permissao['nome'],
                'descricao' => $permissao['descricao'],
                'modulo' => $permissao['modulo'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✅ ' . count($permissoes) . ' permissões inseridas com sucesso!');
    }
}
