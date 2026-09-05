<?php

namespace Tests\Unit;

use App\Support\Pricing;
use PHPUnit\Framework\TestCase;

class PricingTest extends TestCase
{
    public function test_bs_multiplies_usd_by_rate(): void
    {
        $this->assertSame(1017.00, Pricing::bs(10.17, 100));
    }

    public function test_bs_rounds_up_to_nearest_step(): void
    {
        $this->assertSame(1020.00, Pricing::bs(10.17, 100, 5));
        $this->assertSame(1025.00, Pricing::bs(10.17, 100, 25));
        $this->assertSame(1100.00, Pricing::bs(10.17, 100, 100));
    }

    public function test_bs_rounds_up_when_exactly_at_step_is_kept(): void
    {
        $this->assertSame(1020.00, Pricing::bs(10.20, 100, 5));
        $this->assertSame(1000.00, Pricing::bs(10.00, 100, 5));
    }

    public function test_bs_with_zero_step_keeps_exact_value(): void
    {
        $this->assertSame(1017.00, Pricing::bs(10.17, 100, 0));
        $this->assertSame(1017.00, Pricing::bs(10.17, 100, null));
    }
}