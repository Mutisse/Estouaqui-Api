<?php
// database/seeders/UserSeeder.php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Buscar os roles
        $roleRoot = Role::where('nome', 'root')->first();
        $roleAdmin = Role::where('nome', 'admin')->first();
        $roleCliente = Role::where('nome', 'cliente')->first();
        $rolePrestador = Role::where('nome', 'prestador')->first();

        // 1. ROOT - Acesso total
        User::updateOrCreate(
            ['email' => 'root@estouaqui.com'],
            [
                'nome' => 'Super Root',
                'telefone' => '840000000',
                'password' => Hash::make('Root@2026'),
                'role_id' => $roleRoot?->id,
                'tipo' => 'root',
                'verificado' => true,
                'email_verified_at' => now(),
                'disponivel' => true,
            ]
        );

        // 2. ADMIN - Gestão do sistema
        User::updateOrCreate(
            ['email' => 'admin@estouaqui.com'],
            [
                'nome' => 'Administrador',
                'telefone' => '840000001',
                'password' => Hash::make('@dm!n321'),
                'role_id' => $roleAdmin?->id,
                'tipo' => 'admin',
                'verificado' => true,
                'email_verified_at' => now(),
                'disponivel' => true,
            ]
        );

        // 3. CLIENTE - Contrata serviços
        User::updateOrCreate(
            ['email' => 'cliente@email.com'],
            [
                'nome' => 'João Cliente',
                'telefone' => '840000002',
                'password' => Hash::make('cl!&nt&'),
                'role_id' => $roleCliente?->id,
                'tipo' => 'cliente',
                'verificado' => true,
                'email_verified_at' => now(),
                'disponivel' => true,
            ]
        );

        // 4. PRESTADOR - Oferece serviços
        User::updateOrCreate(
            ['email' => 'prestador@email.com'],
            [
                'nome' => 'Maria Prestadora',
                'telefone' => '840000003',
                'password' => Hash::make('pr&st@dor'),
                'role_id' => $rolePrestador?->id,
                'tipo' => 'prestador',
                'profissao' => 'Eletricista',
                'sobre' => 'Profissional com 10 anos de experiência em serviços elétricos',
                'disponivel' => true,
                'verificado' => true,
                'media_avaliacao' => 4.8,
                'total_avaliacoes' => 25,
                'latitude' => -25.9692,
                'longitude' => 32.5732,
                'raio_atendimento' => 15,
                'email_verified_at' => now(),
            ]
        );
    }
}
