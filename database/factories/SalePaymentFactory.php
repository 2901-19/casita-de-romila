<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalePaymentFactory extends Factory
{
    protected $model = SalePayment::class;

    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'method' => fake()->randomElement(['efectivo', 'biopago', 'transferencia', 'pago_movil', 'pdv']),
            'amount' => fake()->randomFloat(2, 10, 500),
        ];
    }
}
