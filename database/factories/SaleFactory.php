<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'total' => fake()->randomFloat(2, 10, 500),
            'status' => 'completada',
        ];
    }

    public function anulada(): static
    {
        return $this->state(['status' => 'anulada', 'cancel_reason' => 'Test']);
    }
}
