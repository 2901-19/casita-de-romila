<?php

namespace Database\Factories;

use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    public function definition(): array
    {
        return [
            'rate' => fake()->randomFloat(2, 30, 50),
            'source' => fake()->randomElement(['bcv', 'paralelo', 'binance', 'enzona', 'manual']),
            'user_id' => User::factory(),
        ];
    }
}
