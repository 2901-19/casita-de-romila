<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'category_id' => null,
            'control_type' => 'inventariable',
            'cost_price' => 1.00,
            'margin_percent' => 50.00,
            'sale_price' => 2.00,
            'price_override' => false,
            'stock_min' => 5,
            'stock_current' => 20,
            'schedule' => 'ambos',
            'is_active' => true,
        ];
    }

    public function produccion(): static
    {
        return $this->state(['control_type' => 'produccion']);
    }

    public function demanda(): static
    {
        return $this->state(['control_type' => 'demanda']);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
