<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['nome' => 'root', 'display_name' => 'Root', 'descricao' => 'Acesso total ao sistema'],
            ['nome' => 'admin', 'display_name' => 'Administrador', 'descricao' => 'Gestão da plataforma'],
            ['nome' => 'cliente', 'display_name' => 'Cliente', 'descricao' => 'Contrata serviços'],
            ['nome' => 'prestador', 'display_name' => 'Prestador', 'descricao' => 'Oferece serviços'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['nome' => $role['nome']],
                [
                    'display_name' => $role['display_name'],
                    'descricao' => $role['descricao'],
                    'ativo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
