<?php

namespace Database\Factories;

use App\Models\Production;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionFactory extends Factory
{
    protected $model = Production::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory()->produccion(),
            'user_id' => User::factory(),
            'quantity' => fake()->numberBetween(1, 50),
            'notes' => fake()->sentence(),
        ];
    }
}
