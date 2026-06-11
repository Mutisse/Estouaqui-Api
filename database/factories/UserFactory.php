<?php
// database/factories/UserFactory.php

namespace Database\Factories;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        // Buscar roles disponíveis
        $roleCliente = Role::where('nome', 'cliente')->first();
        $rolePrestador = Role::where('nome', 'prestador')->first();

        $tipo = $this->faker->randomElement(['cliente', 'prestador']);
        $roleId = $tipo === 'cliente' ? $roleCliente?->id : $rolePrestador?->id;

        return [
            'nome' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'telefone' => '84' . $this->faker->unique()->numberBetween(1000000, 9999999),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role_id' => $roleId,
            'tipo' => $tipo,
            'foto' => null,
            'disponivel' => $tipo === 'prestador' ? $this->faker->boolean(80) : true,
            'verificado' => $this->faker->boolean(70),
            'profissao' => $tipo === 'prestador' ? $this->faker->randomElement([
                'Eletricista', 'Encanador', 'Pintor', 'Pedreiro',
                'Carpinteiro', 'Jardineiro', 'Técnico de Ar Condicionado',
                'Serralheiro', 'Vidraceiro', 'Ladrilhador'
            ]) : null,
            'sobre' => $tipo === 'prestador' ? $this->faker->paragraph() : null,
            'media_avaliacao' => $tipo === 'prestador' ? $this->faker->randomFloat(1, 3, 5) : 0,
            'total_avaliacoes' => $tipo === 'prestador' ? $this->faker->numberBetween(0, 50) : 0,
            'latitude' => $tipo === 'prestador' ? $this->faker->latitude(-26, -25.5) : null,
            'longitude' => $tipo === 'prestador' ? $this->faker->longitude(32, 32.8) : null,
            'raio_atendimento' => $tipo === 'prestador' ? $this->faker->numberBetween(5, 30) : 10,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function cliente(): static
    {
        $role = Role::where('nome', 'cliente')->first();

        return $this->state(fn (array $attributes) => [
            'role_id' => $role?->id,
            'tipo' => 'cliente',
            'profissao' => null,
            'sobre' => null,
        ]);
    }

    public function prestador(): static
    {
        $role = Role::where('nome', 'prestador')->first();

        return $this->state(fn (array $attributes) => [
            'role_id' => $role?->id,
            'tipo' => 'prestador',
            'disponivel' => true,
        ]);
    }

    public function admin(): static
    {
        $role = Role::where('nome', 'admin')->first();

        return $this->state(fn (array $attributes) => [
            'role_id' => $role?->id,
            'tipo' => 'admin',
            'profissao' => null,
            'disponivel' => true,
            'verificado' => true,
        ]);
    }

    public function root(): static
    {
        $role = Role::where('nome', 'root')->first();

        return $this->state(fn (array $attributes) => [
            'role_id' => $role?->id,
            'tipo' => 'root',
            'profissao' => null,
            'disponivel' => true,
            'verificado' => true,
        ]);
    }
}
