<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ==========================================
        // 1. USUÁRIOS ESPECÍFICOS (com verificação)
        // ==========================================

        // ✅ ADMIN ROOT - Para monitoramento do sistema
        User::firstOrCreate(
            ['email' => 'root@estouaqui.com'],
            [
                'nome' => 'Admin_Root',
                'telefone' => '+258840000000',
                'endereco' => 'Maputo',
                'foto' => null,
                'password' => Hash::make('Root@2026'),
                'tipo' => 'admin',
                'verificado' => true,
                'ativo' => true,
                'email_verified_at' => now(),
            ]
        );

        // Admin principal
        User::firstOrCreate(
            ['email' => 'admin@estouaqui.com'],
            [
                'nome' => 'Administrador',
                'telefone' => '841234567',
                'endereco' => 'Maputo',
                'foto' => null,
                'password' => Hash::make('@dm!n12&'),
                'tipo' => 'admin',
                'verificado' => true,
                'ativo' => true,
                'email_verified_at' => now(),
            ]
        );

        // Cliente de teste
        User::firstOrCreate(
            ['email' => 'cliente@email.com'],
            [
                'nome' => 'João Silva',
                'telefone' => '841234568',
                'endereco' => 'Matola',
                'foto' => null,
                'password' => Hash::make('cliente123'),
                'tipo' => 'cliente',
                'verificado' => true,
                'ativo' => true,
                'email_verified_at' => now(),
            ]
        );

        // Prestador de teste
        User::firstOrCreate(
            ['email' => 'prestador@email.com'],
            [
                'nome' => 'Maria Santos',
                'telefone' => '841234569',
                'endereco' => 'Boane',
                'foto' => null,
                'password' => Hash::make('prestador123'),
                'tipo' => 'prestador',
                'verificado' => true,
                'ativo' => true,
                'email_verified_at' => now(),
            ]
        );

        // ==========================================
        // 2. USUÁRIOS ALEATÓRIOS (apenas se não existirem muitos)
        // ==========================================

        $clientesCount = User::where('tipo', 'cliente')->count();
        $prestadoresCount = User::where('tipo', 'prestador')->count();
        $adminsCount = User::where('tipo', 'admin')->count();

        // Criar 10 clientes aleatórios (apenas se tiver menos de 10)
        if ($clientesCount < 10) {
            User::factory()
                ->count(10 - $clientesCount)
                ->cliente()
                ->create();
            $this->command->info("✅ Criados " . (10 - $clientesCount) . " clientes aleatórios");
        }

        // Criar 5 prestadores aleatórios (apenas se tiver menos de 5)
        if ($prestadoresCount < 5) {
            User::factory()
                ->count(5 - $prestadoresCount)
                ->prestador()
                ->create();
            $this->command->info("✅ Criados " . (5 - $prestadoresCount) . " prestadores aleatórios");
        }

        // Criar 2 administradores aleatórios (apenas se tiver menos de 2)
        if ($adminsCount < 2) {
            User::factory()
                ->count(2 - $adminsCount)
                ->admin()
                ->create();
            $this->command->info("✅ Criados " . (2 - $adminsCount) . " admins aleatórios");
        }
    }
}
