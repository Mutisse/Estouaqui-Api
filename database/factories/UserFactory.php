<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nome' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'telefone' => '84' . fake()->numberBetween(1000000, 9999999),
            'endereco' => fake()->address(),
            'foto' => null,
            'password' => static::$password ??= Hash::make('password'),
            'tipo' => 'cliente',
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => 'admin',
        ]);
    }

    public function prestador(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => 'prestador',
        ]);
    }

    public function cliente(): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo' => 'cliente',
        ]);
    }
}
