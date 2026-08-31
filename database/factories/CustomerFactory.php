<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'balance' => 0,
            'is_active' => true,
        ];
    }

    public function withDebt(): static
    {
        return $this->state(['balance' => -fake()->randomFloat(2, 10, 500)]);
    }

    public function withCredit(): static
    {
        return $this->state(['balance' => fake()->randomFloat(2, 10, 500)]);
    }
}
