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
            'reason' => fake()->randomElement(['vencido', 'danado', 'otro']),
            'notes' => fake()->sentence(),
        ];
    }
}
