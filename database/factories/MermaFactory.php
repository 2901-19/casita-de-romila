<?php

namespace Database\Factories;

use App\Models\Merma;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MermaFactory extends Factory
{
    protected $model = Merma::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'cost' => fake()->randomFloat(2, 1, 20),
            'reason' => fake()->randomElement(['vencido', 'danado', 'otro']),
            'type' => 'merma',
            'notes' => fake()->sentence(),
        ];
    }

    public function consumption(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'consumo',
            'reason' => 'otro',
        ]);
    }
}
