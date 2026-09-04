<?php

namespace Database\Factories;

use App\Models\Comanda;
use App\Models\ComandaPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ComandaPaymentFactory extends Factory
{
    protected $model = ComandaPayment::class;

    public function definition(): array
    {
        return [
            'comanda_id' => Comanda::factory(),
            'amount' => fake()->randomFloat(2, 1, 500),
            'method' => 'efectivo',
            'customer_id' => null,
            'user_id' => User::factory(),
        ];
    }
}
