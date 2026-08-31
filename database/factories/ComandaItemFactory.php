<?php

namespace Database\Factories;

use App\Models\Comanda;
use App\Models\ComandaItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ComandaItemFactory extends Factory
{
    protected $model = ComandaItem::class;

    public function definition(): array
    {
        return [
            'comanda_id' => Comanda::factory(),
            'product_id' => Product::factory(),
            'combo_id' => null,
            'product_name' => fake()->word(),
            'quantity' => 1,
            'unit_price' => fake()->randomFloat(2, 1, 100),
            'subtotal' => 1,
            'delivered' => false,
        ];
    }
}
