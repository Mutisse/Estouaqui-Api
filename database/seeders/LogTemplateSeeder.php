<?php
// database/seeders/LogTemplateSeeder.php

namespace Database\Seeders;

use App\Models\LogTemplate;
use Illuminate\Database\Seeder;

class LogTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ========== SERVIÇOS ==========
            [
                'evento' => 'servico.criado',
                'titulo' => 'Serviço Criado',
                'mensagem' => 'Serviço "{{nome}}" foi criado por {{user_nome}}',
                'nivel' => 'info',
                'modulo' => 'servicos',
                'ativo' => true,
            ],
            [
                'evento' => 'servico.atualizado',
                'titulo' => 'Serviço Atualizado',
                'mensagem' => 'Serviço "{{nome}}" foi atualizado por {{user_nome}}',
                'nivel' => 'info',
                'modulo' => 'servicos',
                'ativo' => true,
            ],
            [
                'evento' => 'servico.removido',
                'titulo' => 'Serviço Removido',
                'mensagem' => 'Serviço "{{nome}}" foi removido por {{user_nome}}',
                'nivel' => 'warning',
                'modulo' => 'servicos',
                'ativo' => true,
            ],

            // ========== PEDIDOS ==========
            [
                'evento' => 'pedido.criado',
                'titulo' => 'Pedido Criado',
                'mensagem' => 'Pedido #{{numero}} foi criado por {{user_nome}}',
                'nivel' => 'info',
                'modulo' => 'pedidos',
                'ativo' => true,
            ],
            [
                'evento' => 'pedido.atualizado',
                'titulo' => 'Pedido Atualizado',
                'mensagem' => 'Pedido #{{numero}} foi alterado para {{status}} por {{user_nome}}',
                'nivel' => 'info',
                'modulo' => 'pedidos',
                'ativo' => true,
            ],
            [
                'evento' => 'pedido.cancelado',
                'titulo' => 'Pedido Cancelado',
                'mensagem' => 'Pedido #{{numero}} foi cancelado por {{user_nome}}. Motivo: {{motivo}}',
                'nivel' => 'warning',
                'modulo' => 'pedidos',
                'ativo' => true,
            ],

            // ========== UTILIZADORES ==========
            [
                'evento' => 'user.login',
                'titulo' => 'Login Realizado',
                'mensagem' => 'Usuário {{user_nome}} fez login no sistema',
                'nivel' => 'info',
                'modulo' => 'auth',
                'ativo' => true,
            ],
            [
                'evento' => 'user.logout',
                'titulo' => 'Logout Realizado',
                'mensagem' => 'Usuário {{user_nome}} fez logout',
                'nivel' => 'info',
                'modulo' => 'auth',
                'ativo' => true,
            ],
            [
                'evento' => 'user.criado',
                'titulo' => 'Usuário Criado',
                'mensagem' => 'Usuário {{user_nome}} ({{tipo}}) foi criado por {{admin_nome}}',
                'nivel' => 'info',
                'modulo' => 'users',
                'ativo' => true,
            ],
            [
                'evento' => 'user.bloqueado',
                'titulo' => 'Usuário Bloqueado',
                'mensagem' => 'Usuário {{user_nome}} foi bloqueado por {{admin_nome}}',
                'nivel' => 'warning',
                'modulo' => 'users',
                'ativo' => true,
            ],
            [
                'evento' => 'user.desbloqueado',
                'titulo' => 'Usuário Desbloqueado',
                'mensagem' => 'Usuário {{user_nome}} foi desbloqueado por {{admin_nome}}',
                'nivel' => 'info',
                'modulo' => 'users',
                'ativo' => true,
            ],
        ];

        foreach ($templates as $template) {
            LogTemplate::updateOrCreate(
                ['evento' => $template['evento']],
                $template
            );
        }

        $this->command->info('✅ Templates de log criados/atualizados com sucesso!');
    }
}
