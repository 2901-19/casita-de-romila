<?php

namespace Database\Factories;

use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryAdjustmentFactory extends Factory
{
    protected $model = InventoryAdjustment::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['entrada', 'salida']),
            'quantity' => fake()->numberBetween(1, 20),
            'reason' => fake()->randomElement(['compra', 'merma', 'ajuste', 'venta', 'devolucion', 'produccion']),
            'notes' => fake()->sentence(),
        ];
    }
}
