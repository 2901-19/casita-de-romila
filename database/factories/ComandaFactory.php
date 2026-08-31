<?php

namespace Database\Factories;

use App\Models\Comanda;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ComandaFactory extends Factory
{
    protected $model = Comanda::class;

    public function definition(): array
    {
        return [
            'comanda_number' => '0001',
            'user_id' => User::factory(),
            'status' => Comanda::STATUS_MONTADA,
            'order_type' => Comanda::ORDER_LOCAL,
        ];
    }

    public function entregada(): static
    {
        return $this->state(['status' => Comanda::STATUS_ENTREGADA]);
    }

    public function cobrada(): static
    {
        return $this->state(['status' => Comanda::STATUS_COBRADA]);
    }

    public function delivery(): static
    {
        return $this->state(['order_type' => Comanda::ORDER_DELIVERY]);
    }
}
