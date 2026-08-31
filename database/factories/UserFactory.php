<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => strtolower(fake()->unique()->userName()),
            'password' => static::$password ??= Hash::make('password'),
            'role_id' => fn () => Role::where('slug', 'recepcionista')->firstOrCreate(
                ['name' => 'Recepcionista', 'is_system' => false]
            )->id,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function gerente(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => fn () => Role::where('slug', 'gerente')->firstOrCreate(
                ['name' => 'Gerente', 'is_system' => true]
            )->id,
        ]);
    }

    public function recepcionista(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => fn () => Role::where('slug', 'recepcionista')->firstOrCreate(
                ['name' => 'Recepcionista', 'is_system' => false]
            )->id,
        ]);
    }
}
