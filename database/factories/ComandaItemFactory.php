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
            'order_type' => ComandaItem::ORDER_LOCAL,
            'note' => null,
            'quantity' => 1,
            'unit_price' => fake()->randomFloat(2, 1, 100),
            'subtotal' => fn (array $attrs) => round((float) ($attrs['unit_price'] ?? 1) * ($attrs['quantity'] ?? 1), 2),
            'delivered_quantity' => 0,
            'delivered_at' => null,
            'collected' => false,
        ];
    }
}