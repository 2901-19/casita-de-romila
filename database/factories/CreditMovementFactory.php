<?php

namespace Database\Factories;

namespace Database\Factories;

use App\Models\CreditMovement;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CreditMovementFactory extends Factory
{
    protected $model = CreditMovement::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'sale_id' => null,
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['cargo', 'abono', 'pago']),
            'amount' => fake()->randomFloat(2, 10, 500),
            'notes' => fake()->sentence(),
        ];
    }
}
