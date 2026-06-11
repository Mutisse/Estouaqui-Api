<?php
// database/seeders/NotificationTemplateSeeder.php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ========== PEDIDOS ==========
            [
                'evento' => 'pedido.criado',
                'titulo' => 'Pedido criado com sucesso!',
                'mensagem' => 'Seu pedido #{{numero}} foi criado e está aguardando um prestador.',
                'tipo' => 'pedido',
                'ativo' => true,
            ],
            [
                'evento' => 'pedido.aceito',
                'titulo' => 'Pedido aceito! 🎉',
                'mensagem' => 'Seu pedido #{{numero}} foi aceito por {{prestador_nome}}.',
                'tipo' => 'pedido',
                'ativo' => true,
            ],
            [
                'evento' => 'pedido.em_andamento',
                'titulo' => 'Pedido em andamento',
                'mensagem' => 'Seu pedido #{{numero}} está sendo realizado por {{prestador_nome}}.',
                'tipo' => 'pedido',
                'ativo' => true,
            ],
            [
                'evento' => 'pedido.concluido',
                'titulo' => 'Pedido concluído! ✅',
                'mensagem' => 'Seu pedido #{{numero}} foi concluído. Avalie o serviço prestado.',
                'tipo' => 'pedido',
                'ativo' => true,
            ],
            [
                'evento' => 'pedido.cancelado',
                'titulo' => 'Pedido cancelado',
                'mensagem' => 'Seu pedido #{{numero}} foi cancelado. Motivo: {{motivo}}',
                'tipo' => 'pedido',
                'ativo' => true,
            ],
            [
                'evento' => 'pedido.novo_para_prestador',
                'titulo' => 'Novo pedido disponível!',
                'mensagem' => 'Um novo pedido #{{numero}} foi criado na categoria {{categoria}}.',
                'tipo' => 'pedido',
                'ativo' => true,
            ],
            [
                'evento' => 'pedido.aceito_prestador',
                'titulo' => 'Pedido aceito! ✅',
                'mensagem' => 'Você aceitou o pedido #{{numero}} de {{cliente_nome}}.',
                'tipo' => 'pedido',
                'ativo' => true,
            ],
            [
                'evento' => 'pedido.cancelado_prestador',
                'titulo' => 'Pedido cancelado ❌',
                'mensagem' => 'O pedido #{{numero}} foi cancelado. Motivo: {{motivo}}',
                'tipo' => 'pedido',
                'ativo' => true,
            ],
            [
                'evento' => 'pedido.concluido_prestador',
                'titulo' => 'Pedido concluído! 🎉',
                'mensagem' => 'Você concluiu o pedido #{{numero}} de {{cliente_nome}}',
                'tipo' => 'pedido',
                'ativo' => true,
            ],

            // ========== FAVORITOS ==========
            [
                'evento' => 'favorito.adicionado',
                'titulo' => 'Você foi favoritado! ⭐',
                'mensagem' => '{{cliente_nome}} adicionou você aos favoritos.',
                'tipo' => 'favorito',
                'ativo' => true,
            ],
            [
                'evento' => 'favorito.removido',
                'titulo' => 'Favorito removido',
                'mensagem' => '{{cliente_nome}} removeu você dos favoritos.',
                'tipo' => 'favorito',
                'ativo' => true,
            ],

            // ========== SISTEMA / PERFIL ==========
            [
                'evento' => 'sistema.bem_vindo',
                'titulo' => 'Bem-vindo ao EstouAqui!',
                'mensagem' => 'Olá {{nome}}, estamos felizes em ter você conosco.',
                'tipo' => 'sistema',
                'ativo' => true,
            ],
            [
                'evento' => 'sistema.perfil_atualizado',
                'titulo' => 'Perfil atualizado',
                'mensagem' => 'Seu perfil foi atualizado com sucesso.',
                'tipo' => 'sistema',
                'ativo' => true,
            ],
            [
                'evento' => 'perfil.foto_atualizada',
                'titulo' => 'Foto atualizada 📸',
                'mensagem' => 'Sua foto de perfil foi atualizada com sucesso.',
                'tipo' => 'sistema',
                'ativo' => true,
            ],
            [
                'evento' => 'perfil.foto_removida',
                'titulo' => 'Foto removida',
                'mensagem' => 'Sua foto de perfil foi removida.',
                'tipo' => 'sistema',
                'ativo' => true,
            ],

            // ========== PORTFÓLIO ==========
            [
                'evento' => 'portfolio.foto_adicionada',
                'titulo' => 'Nova foto no portfólio! 🖼️',
                'mensagem' => 'Uma nova foto foi adicionada ao seu portfólio.',
                'tipo' => 'portfolio',
                'ativo' => true,
            ],
            [
                'evento' => 'portfolio.foto_removida',
                'titulo' => 'Foto removida do portfólio',
                'mensagem' => 'Uma foto foi removida do seu portfólio.',
                'tipo' => 'portfolio',
                'ativo' => true,
            ],

            // ========== CATEGORIAS ==========
            [
                'evento' => 'categoria.adicionada',
                'titulo' => 'Categoria adicionada 📂',
                'mensagem' => 'A categoria "{{categoria}}" foi adicionada ao seu perfil.',
                'tipo' => 'categoria',
                'ativo' => true,
            ],
            [
                'evento' => 'categoria.removida',
                'titulo' => 'Categoria removida',
                'mensagem' => 'A categoria "{{categoria}}" foi removida do seu perfil.',
                'tipo' => 'categoria',
                'ativo' => true,
            ],

            // ========== DISPONIBILIDADE ==========
            [
                'evento' => 'disponibilidade.atualizada',
                'titulo' => 'Disponibilidade atualizada 📅',
                'mensagem' => 'Sua disponibilidade de horários foi atualizada com sucesso.',
                'tipo' => 'disponibilidade',
                'ativo' => true,
            ],

            // ========== SERVIÇOS ==========
            [
                'evento' => 'servico.criado',
                'titulo' => 'Serviço criado! 🛠️',
                'mensagem' => 'Seu serviço "{{nome}}" foi criado com sucesso.',
                'tipo' => 'servico',
                'ativo' => true,
            ],
            [
                'evento' => 'servico.atualizado',
                'titulo' => 'Serviço atualizado ✏️',
                'mensagem' => 'Seu serviço "{{nome}}" foi atualizado com sucesso.',
                'tipo' => 'servico',
                'ativo' => true,
            ],
            [
                'evento' => 'servico.removido',
                'titulo' => 'Serviço removido 🗑️',
                'mensagem' => 'Seu serviço "{{nome}}" foi removido.',
                'tipo' => 'servico',
                'ativo' => true,
            ],

            // ========== AVALIAÇÕES ==========
            [
                'evento' => 'avaliacao.recebida',
                'titulo' => 'Nova avaliação! ⭐',
                'mensagem' => '{{cliente_nome}} avaliou seu serviço com {{nota}} estrelas.',
                'tipo' => 'avaliacao',
                'ativo' => true,
            ],
            [
                'evento' => 'avaliacao.resposta',
                'titulo' => 'Prestador respondeu sua avaliação',
                'mensagem' => '{{prestador_nome}} respondeu sua avaliação: "{{resposta}}"',
                'tipo' => 'avaliacao',
                'ativo' => true,
            ],

            // ========== PROMOÇÕES ==========
            [
                'evento' => 'promocao.nova',
                'titulo' => 'Nova promoção! 🎁',
                'mensagem' => '{{titulo}} - {{descricao}}. Use o código {{codigo}}',
                'tipo' => 'promocao',
                'ativo' => true,
            ],
            [
                'evento' => 'promocao.cupom_aplicado',
                'titulo' => 'Cupom aplicado! 🎉',
                'mensagem' => 'Cupom {{codigo}} aplicado com sucesso! Você ganhou {{desconto}} de desconto.',
                'tipo' => 'promocao',
                'ativo' => true,
            ],
            [
                'evento' => 'promocao.expirada',
                'titulo' => 'Promoção expirou ⏰',
                'mensagem' => 'A promoção {{titulo}} expirou. Fique atento às próximas ofertas!',
                'tipo' => 'promocao',
                'ativo' => true,
            ],

            // ========== MENSAGENS ==========
            [
                'evento' => 'mensagem.nova',
                'titulo' => 'Nova mensagem 📧',
                'mensagem' => '{{remetente}} enviou uma mensagem: "{{mensagem}}"',
                'tipo' => 'mensagem',
                'ativo' => true,
            ],

            // ========== SAQUES ==========
            [
                'evento' => 'sistema.saque_solicitado',
                'titulo' => 'Saque solicitado 💰',
                'mensagem' => 'Solicitação de saque no valor de {{valor}} MZN foi registrada.',
                'tipo' => 'sistema',
                'ativo' => true,
            ],
            [
                'evento' => 'sistema.saque_aprovado',
                'titulo' => 'Saque aprovado! ✅',
                'mensagem' => 'Seu saque de {{valor}} MZN foi aprovado.',
                'tipo' => 'sistema',
                'ativo' => true,
            ],
            [
                'evento' => 'sistema.saque_rejeitado',
                'titulo' => 'Saque não aprovado ❌',
                'mensagem' => 'Seu saque de {{valor}} MZN foi rejeitado. Motivo: {{motivo}}',
                'tipo' => 'sistema',
                'ativo' => true,
            ],
            [
                'evento' => 'sistema.saque_concluido',
                'titulo' => 'Saque concluído! 🎉',
                'mensagem' => 'Seu saque de {{valor}} MZN foi transferido para sua conta.',
                'tipo' => 'sistema',
                'ativo' => true,
            ],

            // ========== AGENDA ==========
            [
                'evento' => 'agenda.dia_bloqueado',
                'titulo' => 'Dia bloqueado na agenda 📅',
                'mensagem' => 'O dia {{data}} foi bloqueado na sua agenda.',
                'tipo' => 'sistema',
                'ativo' => true,
            ],
            [
                'evento' => 'agenda.dia_desbloqueado',
                'titulo' => 'Dia desbloqueado na agenda ✅',
                'mensagem' => 'O dia {{data}} foi desbloqueado na sua agenda.',
                'tipo' => 'sistema',
                'ativo' => true,
            ],
            // Adicionar no NotificationTemplateSeeder

            // ========== MENSAGENS (CHAT) ==========

            [
                'evento' => 'mensagem.nova',
                'titulo' => 'Nova mensagem 📧',
                'mensagem' => '{{remetente}} enviou uma mensagem: "{{mensagem}}"',
                'tipo' => 'mensagem',
                'ativo' => true,
            ],

            [
                'evento' => 'mensagem.nova_prestador',
                'titulo' => 'Nova mensagem de cliente 📱',
                'mensagem' => '{{cliente_nome}} enviou uma mensagem: "{{mensagem}}"',
                'tipo' => 'mensagem',
                'ativo' => true,
            ],

            [
                'evento' => 'mensagem.lidas',
                'titulo' => 'Mensagens lidas ✅',
                'mensagem' => '{{prestador_nome}} leu as suas mensagens',
                'tipo' => 'mensagem',
                'ativo' => true,
            ],
        ];

        foreach ($templates as $template) {
            NotificationTemplate::updateOrCreate(
                ['evento' => $template['evento']],
                $template
            );
        }
    }
}
