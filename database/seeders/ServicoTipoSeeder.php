<?php
// database/seeders/ServicoTipoSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServicoTipo;

class ServicoTipoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Todos os tipos de serviço em um único array
        $tipos = [
            // Tipos principais
            ['nome' => 'Eletricista', 'slug' => 'eletricista', 'icone' => 'bolt', 'cor' => 'warning', 'ordem' => 1],
            ['nome' => 'Canalizador', 'slug' => 'canalizador', 'icone' => 'water_drop', 'cor' => 'info', 'ordem' => 2],
            ['nome' => 'Pintor', 'slug' => 'pintor', 'icone' => 'brush', 'cor' => 'accent', 'ordem' => 3],
            ['nome' => 'Informático', 'slug' => 'informatico', 'icone' => 'computer', 'cor' => 'purple', 'ordem' => 4],
            ['nome' => 'Cabeleireiro', 'slug' => 'cabeleireiro', 'icone' => 'content_cut', 'cor' => 'secondary', 'ordem' => 5],
            ['nome' => 'Manicure', 'slug' => 'manicure', 'icone' => 'spa', 'cor' => 'pink', 'ordem' => 6],
            ['nome' => 'Limpeza', 'slug' => 'limpeza', 'icone' => 'cleaning_services', 'cor' => 'positive', 'ordem' => 7],
            ['nome' => 'Baby-sitter', 'slug' => 'baby-sitter', 'icone' => 'child_care', 'cor' => 'info', 'ordem' => 8],
            ['nome' => 'Motorista', 'slug' => 'motorista', 'icone' => 'drive_eta', 'cor' => 'primary', 'ordem' => 9],
            ['nome' => 'Costureira', 'slug' => 'costureira', 'icone' => 'sewing', 'cor' => 'secondary', 'ordem' => 10],
            ['nome' => 'Jardinagem', 'slug' => 'jardinagem', 'icone' => 'yard', 'cor' => 'green', 'ordem' => 11],
            ['nome' => 'Fotógrafo', 'slug' => 'fotografo', 'icone' => 'photo_camera', 'cor' => 'accent', 'ordem' => 12],
            ['nome' => 'Personal Trainer', 'slug' => 'personal-trainer', 'icone' => 'fitness_center', 'cor' => 'warning', 'ordem' => 13],
            ['nome' => 'Professor', 'slug' => 'professor', 'icone' => 'school', 'cor' => 'primary', 'ordem' => 14],

            // Tipos extras
            ['nome' => 'Mecânico', 'slug' => 'mecanico', 'icone' => 'car_repair', 'cor' => 'orange', 'ordem' => 15],
            ['nome' => 'Eletrodomésticos', 'slug' => 'eletrodomesticos', 'icone' => 'kitchen', 'cor' => 'primary', 'ordem' => 16],
            ['nome' => 'Marceneiro', 'slug' => 'marceneiro', 'icone' => 'handyman', 'cor' => 'brown', 'ordem' => 17],
            ['nome' => 'Serralheiro', 'slug' => 'serralheiro', 'icone' => 'construction', 'cor' => 'grey', 'ordem' => 18],
            ['nome' => 'Vidraceiro', 'slug' => 'vidraceiro', 'icone' => 'window', 'cor' => 'info', 'ordem' => 19],
            ['nome' => 'Dentista', 'slug' => 'dentista', 'icone' => 'medical_services', 'cor' => 'primary', 'ordem' => 20],
            ['nome' => 'Fisioterapeuta', 'slug' => 'fisioterapeuta', 'icone' => 'fitness_center', 'cor' => 'green', 'ordem' => 21],
            ['nome' => 'Psicólogo', 'slug' => 'psicologo', 'icone' => 'psychology', 'cor' => 'purple', 'ordem' => 22],
            ['nome' => 'Massagista', 'slug' => 'massagista', 'icone' => 'spa', 'cor' => 'pink', 'ordem' => 23],
            ['nome' => 'Tradutor', 'slug' => 'tradutor', 'icone' => 'translate', 'cor' => 'primary', 'ordem' => 24],
            ['nome' => 'Arquiteto', 'slug' => 'arquiteto', 'icone' => 'architecture', 'cor' => 'accent', 'ordem' => 25],
            ['nome' => 'Engenheiro', 'slug' => 'engenheiro', 'icone' => 'engineering', 'cor' => 'primary', 'ordem' => 26],
            ['nome' => 'Advogado', 'slug' => 'advogado', 'icone' => 'gavel', 'cor' => 'secondary', 'ordem' => 27],
            ['nome' => 'Contabilista', 'slug' => 'contabilista', 'icone' => 'calculate', 'cor' => 'info', 'ordem' => 28],
            ['nome' => 'Segurança', 'slug' => 'seguranca', 'icone' => 'security', 'cor' => 'grey', 'ordem' => 29],
            ['nome' => 'Entregador', 'slug' => 'entregador', 'icone' => 'delivery', 'cor' => 'warning', 'ordem' => 30],
        ];

        foreach ($tipos as $tipo) {
            ServicoTipo::updateOrCreate(
                ['slug' => $tipo['slug']],
                [
                    'nome' => $tipo['nome'],
                    'slug' => $tipo['slug'],
                    'icone' => $tipo['icone'],
                    'cor' => $tipo['cor'],
                    'descricao' => $this->getDescricaoPorTipo($tipo['nome']),
                    'ordem' => $tipo['ordem'],
                    'ativo' => true,
                ]
            );
        }

        $this->command->info('✅ ServicoTipos seeded successfully! Total: ' . count($tipos));
    }

    /**
     * Gerar descrição para cada tipo de serviço
     */
    private function getDescricaoPorTipo(string $nome): string
    {
        $descricoes = [
            'Eletricista' => 'Serviços de instalação, reparação e manutenção elétrica residencial e comercial.',
            'Canalizador' => 'Reparação e instalação de sistemas hidráulicos, canalizações e equipamentos sanitários.',
            'Pintor' => 'Pintura de interiores e exteriores, acabamentos e texturização de paredes.',
            'Informático' => 'Assistência técnica em informática, reparação de computadores e redes.',
            'Cabeleireiro' => 'Cortes de cabelo, penteados, coloração e tratamentos capilares.',
            'Manicure' => 'Cuidados com as unhas, alongamento, esmaltação e design de unhas.',
            'Limpeza' => 'Serviços de limpeza residencial, comercial e pós-obra.',
            'Baby-sitter' => 'Cuidados e supervisão de crianças, acompanhamento em atividades.',
            'Motorista' => 'Serviços de transporte, motorista particular e entregas.',
            'Costureira' => 'Alterações de roupa, costura sob medida e reparos em tecidos.',
            'Jardinagem' => 'Manutenção de jardins, poda de árvores e paisagismo.',
            'Fotógrafo' => 'Fotografia profissional para eventos, retratos e ensaios.',
            'Personal Trainer' => 'Treinamento físico personalizado e acompanhamento de exercícios.',
            'Professor' => 'Aulas particulares e reforço escolar em diversas disciplinas.',
            'Mecânico' => 'Reparação e manutenção de veículos automóveis.',
            'Eletrodomésticos' => 'Reparação de eletrodomésticos e equipamentos.',
            'Marceneiro' => 'Trabalhos em madeira, móveis sob medida e reparos.',
            'Serralheiro' => 'Trabalhos em metal, grades, portões e estruturas metálicas.',
            'Vidraceiro' => 'Instalação e reparação de vidros e espelhos.',
            'Dentista' => 'Atendimento odontológico e cuidados com a saúde bucal.',
            'Fisioterapeuta' => 'Tratamentos de fisioterapia e reabilitação.',
            'Psicólogo' => 'Acompanhamento psicológico e terapia.',
            'Massagista' => 'Massagens terapêuticas e relaxantes.',
            'Tradutor' => 'Tradução de documentos e interpretação.',
            'Arquiteto' => 'Projetos arquitetônicos e design de interiores.',
            'Engenheiro' => 'Consultoria e projetos de engenharia civil e estrutural.',
            'Advogado' => 'Consultoria jurídica e assessoria legal.',
            'Contabilista' => 'Serviços de contabilidade e gestão financeira.',
            'Segurança' => 'Serviços de segurança patrimonial e vigilância.',
            'Entregador' => 'Serviços de entrega rápida e logística.',
        ];

        return $descricoes[$nome] ?? 'Serviço profissional de ' . strtolower($nome);
    }
}
