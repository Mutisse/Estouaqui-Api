<?php
// database/seeders/PrestadorDisponibilidadeSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\PrestadorDisponibilidade;

class PrestadorDisponibilidadeSeeder extends Seeder
{
    public function run(): void
    {
        // Criar disponibilidade para prestadores existentes
        $prestadores = User::where('tipo', 'prestador')->get();

        foreach ($prestadores as $prestador) {
            // Verificar se já existe
            $existe = PrestadorDisponibilidade::where('prestador_id', $prestador->id)->exists();

            if (!$existe) {
                PrestadorDisponibilidade::create([
                    'prestador_id' => $prestador->id,
                    'configuracoes' => PrestadorDisponibilidade::getDefaultConfiguracoes(),
                    'horarios_padrao' => PrestadorDisponibilidade::getDefaultHorariosPadrao(),
                    'intervalos_padrao' => PrestadorDisponibilidade::getDefaultIntervalosPadrao(),
                    'ativo' => true,
                ]);
            }
        }

        $this->command->info('Disponibilidade criada para ' . $prestadores->count() . ' prestadores');
    }
}
