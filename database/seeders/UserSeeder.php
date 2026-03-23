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
        // 1. USUÁRIOS ESPECÍFICOS (dados reais)
        // ==========================================

        // Admin principal
        User::create([
            'nome' => 'Administrador',
            'email' => 'admin@estouaqui.com',
            'telefone' => '841234567',
            'endereco' => 'Maputo',
            'foto' => null,
            'password' => Hash::make('admin123'),
            'tipo' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Cliente de teste
        User::create([
            'nome' => 'João Silva',
            'email' => 'cliente@email.com',
            'telefone' => '841234568',
            'endereco' => 'Matola',
            'foto' => null,
            'password' => Hash::make('cliente123'),
            'tipo' => 'cliente',
            'email_verified_at' => now(),
        ]);

        // Prestador de teste
        User::create([
            'nome' => 'Maria Santos',
            'email' => 'prestador@email.com',
            'telefone' => '841234569',
            'endereco' => 'Boane',
            'foto' => null,
            'password' => Hash::make('prestador123'),
            'tipo' => 'prestador',
            'email_verified_at' => now(),
        ]);

        // ==========================================
        // 2. USUÁRIOS ALEATÓRIOS (usando Factory)
        // ==========================================

        // Criar 10 clientes aleatórios
        User::factory()
            ->count(10)
            ->cliente()
            ->create();

        // Criar 5 prestadores aleatórios
        User::factory()
            ->count(5)
            ->prestador()
            ->create();

        // Criar 2 administradores aleatórios
        User::factory()
            ->count(2)
            ->admin()
            ->create();
    }
}
