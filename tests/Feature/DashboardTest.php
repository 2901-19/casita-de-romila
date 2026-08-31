<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_loads_without_errors(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertViewIs('dashboard');
    }

    public function test_dashboard_shows_kpis_with_no_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Ventas del Día');
        $response->assertSee('Productos Activos');
        $response->assertSee('Tasa BCV');
    }

    public function test_dashboard_shows_sales_today(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $sale = Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
            'created_at' => now(),
        ]);
        SalePayment::factory()->create([
            'sale_id' => $sale->id,
            'method' => 'efectivo',
            'amount' => 100,
        ]);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Ventas del Día');
    }

    public function test_metrics_count_settled_credit_and_exclude_pending(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
            'payment_method' => 'credito',
            'total' => 1584.56,
            'created_at' => now()->subDay(),
        ]);

        Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'pendiente',
            'payment_method' => 'credito',
            'total' => 777.77,
        ]);

        $ventaHoy = Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
            'total' => 3200.00,
        ]);
        SalePayment::factory()->create([
            'sale_id' => $ventaHoy->id,
            'method' => 'efectivo',
            'amount' => 3200.00,
        ]);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertViewHas('totalToday', fn ($t) => abs((float) $t - 3200.0) < 0.01);
        $response->assertViewHas('totalYesterday', fn ($t) => abs((float) $t - 1584.56) < 0.01);
        $response->assertViewHas('weeklySales', fn ($w) => abs((float) $w->sum() - (3200.0 + 1584.56)) < 0.01);
        $response->assertViewHas('paymentTotals', fn ($pt) => abs((float) ($pt['efectivo'] ?? 0) - 3200.0) < 0.01);
        $response->assertDontSee('777,77');
    }

    public function test_payment_card_shows_credito_row_for_settled_credit(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
            'payment_method' => 'credito',
            'total' => 500.00,
        ]);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Crédito');
        $response->assertViewHas('paymentTotals', function ($pt) {
            return abs((float) ($pt['credito'] ?? 0) - 500.0) < 0.01;
        });
    }

    public function test_dashboard_renders_chart_canvases(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('data-chart', escape: false);
        $response->assertSee('Crédito');
    }

    public function test_dashboard_nueva_venta_button(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertSee('Nueva Venta');
        $response->assertSee(route('pos.index'));
    }
}
