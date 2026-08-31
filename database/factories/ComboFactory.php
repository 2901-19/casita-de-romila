<?php

namespace Database\Factories;

use App\Models\Combo;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ComboFactory extends Factory
{
    protected $model = Combo::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'sale_price' => $this->faker->randomFloat(2, 5, 50),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withRandomProducts(int $count = 3): static
    {
        return $this->afterCreating(function (Combo $combo) use ($count) {
            $products = Product::inRandomOrder()->limit($count)->get();
            foreach ($products as $product) {
                $combo->products()->attach($product->id, [
                    'quantity' => $this->faker->numberBetween(1, 3),
                ]);
            }
        });
    }
}
