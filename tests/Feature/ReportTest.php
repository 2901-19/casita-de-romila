<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Merma;
use App\Models\Product;
use App\Models\Production;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function gerente(): User
    {
        return User::factory()->gerente()->create();
    }

    // ─── Index ──────────────────────────────────────────────

    public function test_index_loads_without_errors(): void
    {
        $this->actingAs($this->gerente());

        $response = $this->get('/reports');

        $response->assertStatus(200);
        $response->assertViewIs('reports.index');
        $response->assertSee('Ventas');
        $response->assertSee('Productos');
    }

    public function test_index_shows_kpis(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
            'total' => 100.00,
            'created_at' => now(),
        ]);
        Product::factory()->create(['is_active' => true]);

        $response = $this->get('/reports');

        $response->assertStatus(200);
        $response->assertViewHas('monthRevenue');
        $response->assertViewHas('activeProducts');
    }

    // ─── Ventas ─────────────────────────────────────────────

    public function test_sales_report_loads(): void
    {
        $this->actingAs($this->gerente());

        $response = $this->get('/reports/sales');

        $response->assertStatus(200);
        $response->assertViewIs('reports.sales');
    }

    public function test_report_date_filter_shows_preset_buttons(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        $response = $this->get('/reports/sales');

        $response->assertStatus(200);
        $response->assertSee('>Hoy</a>', false);
        $response->assertSee('>Semana</a>', false);
        $response->assertSee('>Mes</a>', false);
    }

    public function test_slow_movers_uses_period_range_instead_of_days(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        $response = $this->get('/reports/slow-movers');

        $response->assertStatus(200);
        $response->assertSee('Sin ventas en el periodo');
        $response->assertDontSee('name="days"', false);
    }

    public function test_sales_report_shows_data(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        $sale = Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
            'total' => 8.00,
            'created_at' => now(),
        ]);
        SaleItem::factory()->create(['sale_id' => $sale->id, 'product_name' => 'Torta', 'quantity' => 2, 'subtotal' => 8.00]);
        SalePayment::factory()->create(['sale_id' => $sale->id, 'method' => 'efectivo', 'amount' => 8.00]);

        $response = $this->get('/reports/sales');

        $response->assertStatus(200);
        $response->assertSee('8,00');
        $response->assertSee('Efectivo');
    }

    public function test_sales_report_counts_settled_credit(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        $creditSale = Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
            'payment_method' => 'credito',
            'total' => 320.00,
            'created_at' => now(),
        ]);

        $cashSale = Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
            'total' => 180.00,
            'created_at' => now(),
        ]);
        SalePayment::factory()->create([
            'sale_id' => $cashSale->id,
            'method' => 'efectivo',
            'amount' => 180.00,
        ]);

        $response = $this->get('/reports/sales');

        $response->assertStatus(200);
        $response->assertViewHas('totalRevenue', fn ($t) => abs((float) $t - 500.0) < 0.01);
        $response->assertSee('Credito');
        $response->assertSee('320,00');
    }

    public function test_sales_report_filters_by_date_range(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
            'created_at' => now()->subDays(5),
        ]);
        Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
            'created_at' => now()->subMonths(2),
        ]);

        $from = now()->subDays(7)->format('Y-m-d');
        $to = now()->format('Y-m-d');

        $response = $this->get("/reports/sales?from={$from}&to={$to}");

        $response->assertStatus(200);
    }

    public function test_sales_report_has_pagination(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        Sale::factory()->count(5)->create([
            'user_id' => $user->id,
            'status' => 'completada',
        ]);

        $response = $this->get('/reports/sales');

        $response->assertStatus(200);
        $response->assertViewHas('sales');
    }

    public function test_sales_export_returns_csv(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
            'total' => 25.00,
        ]);

        $response = $this->get('/reports/sales/csv');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    // ─── Productos ──────────────────────────────────────────

    public function test_products_report_loads(): void
    {
        $this->actingAs($this->gerente());

        $response = $this->get('/reports/products');

        $response->assertStatus(200);
        $response->assertViewIs('reports.products');
    }

    public function test_products_report_shows_profit(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        $product = Product::factory()->create([
            'cost_price' => 5.00,
            'sale_price' => 10.00,
            'stock_current' => 20,
        ]);

        $sale = Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
        ]);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'unit_price' => 10.00,
            'subtotal' => 40.00,
        ]);

        $response = $this->get('/reports/products');

        $response->assertStatus(200);
        $response->assertViewHas('products', function ($products) {
            $p = $products->first();
            return $p && $p->profit == 20.00;
        });
    }

    public function test_products_export_returns_csv(): void
    {
        $this->actingAs($this->gerente());

        $response = $this->get('/reports/products/csv');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    // ─── Creditos ───────────────────────────────────────────

    public function test_credits_report_loads(): void
    {
        $this->actingAs($this->gerente());

        $response = $this->get('/reports/credits');

        $response->assertStatus(200);
        $response->assertViewIs('reports.credits');
    }

    public function test_credits_report_filters_by_date(): void
    {
        $this->actingAs($this->gerente());

        $customer = \App\Models\Customer::factory()->create(['balance' => -50]);
        $creditSale = Sale::factory()->create([
            'status' => 'completada',
            'payment_method' => 'credito',
            'customer_id' => $customer->id,
            'created_at' => now()->subMonths(3),
        ]);

        $from = now()->subDays(7)->format('Y-m-d');
        $to = now()->format('Y-m-d');

        $response = $this->get("/reports/credits?from={$from}&to={$to}");

        $response->assertStatus(200);
        $response->assertViewHas('customers', fn ($c) => $c->isEmpty());
    }

    public function test_credits_export_returns_csv(): void
    {
        $this->actingAs($this->gerente());

        $response = $this->get('/reports/credits/csv');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    // ─── Dias con mas ventas ────────────────────────────────

    public function test_top_days_report_loads(): void
    {
        $this->actingAs($this->gerente());

        $response = $this->get('/reports/top-days');

        $response->assertStatus(200);
        $response->assertViewIs('reports.top-days');
    }

    public function test_top_days_report_shows_data(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
            'total' => 100.00,
            'created_at' => now(),
        ]);

        $response = $this->get('/reports/top-days');

        $response->assertStatus(200);
        $response->assertViewHas('topDays', fn ($d) => $d->count() > 0);
    }

    public function test_top_days_export_returns_csv(): void
    {
        $this->actingAs($this->gerente());

        $response = $this->get('/reports/top-days/csv');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    // ─── Mermas ─────────────────────────────────────────────

    public function test_waste_report_loads(): void
    {
        $this->actingAs($this->gerente());

        $response = $this->get('/reports/waste');

        $response->assertStatus(200);
        $response->assertViewIs('reports.waste');
    }

    public function test_waste_report_shows_data(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        $product = Product::factory()->create();
        Merma::factory()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'quantity' => 5,
            'reason' => 'vencido',
        ]);

        $response = $this->get('/reports/waste');

        $response->assertStatus(200);
        $response->assertViewHas('waste', fn ($w) => $w->count() > 0);
    }

    public function test_waste_export_returns_csv(): void
    {
        $this->actingAs($this->gerente());

        $response = $this->get('/reports/waste/csv');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    // ─── Margen de ganancia ─────────────────────────────────

    public function test_profit_margin_report_loads(): void
    {
        $this->actingAs($this->gerente());

        $response = $this->get('/reports/profit-margin');

        $response->assertStatus(200);
        $response->assertViewIs('reports.profit-margin');
    }

    public function test_profit_margin_calculates_correctly(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        $product = Product::factory()->create([
            'cost_price' => 3.00,
            'sale_price' => 10.00,
        ]);

        $sale = Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
        ]);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 10.00,
            'subtotal' => 100.00,
        ]);

        $response = $this->get('/reports/profit-margin');

        $response->assertStatus(200);
        $response->assertViewHas('products', function ($products) {
            $p = $products->first();
            return $p && $p->profit == 70.00 && $p->total_sold == 10;
        });
    }

    public function test_profit_margin_export_returns_csv(): void
    {
        $this->actingAs($this->gerente());

        $response = $this->get('/reports/profit-margin/csv');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    // ─── Ventas por horario ─────────────────────────────────

    public function test_sales_by_schedule_report_loads(): void
    {
        $this->actingAs($this->gerente());

        $response = $this->get('/reports/sales-by-schedule');

        $response->assertStatus(200);
        $response->assertViewIs('reports.sales-by-schedule');
    }

    public function test_sales_by_schedule_separates_morning_and_night(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
            'total' => 50.00,
            'created_at' => now()->setTime(10, 0),
        ]);
        Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
            'total' => 80.00,
            'created_at' => now()->setTime(20, 0),
        ]);

        $response = $this->get('/reports/sales-by-schedule');

        $response->assertStatus(200);
        $response->assertViewHas('manana');
        $response->assertViewHas('noche');
    }

    public function test_sales_by_schedule_export_returns_csv(): void
    {
        $this->actingAs($this->gerente());

        $response = $this->get('/reports/sales-by-schedule/csv');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    // ─── Productos de lento movimiento ──────────────────────

    public function test_slow_movers_report_loads(): void
    {
        $this->actingAs($this->gerente());

        $response = $this->get('/reports/slow-movers');

        $response->assertStatus(200);
        $response->assertViewIs('reports.slow-movers');
    }

    public function test_slow_movers_shows_inactive_products(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        $product = Product::factory()->create(['is_active' => true, 'stock_current' => 10]);

        $response = $this->get('/reports/slow-movers?days=30');

        $response->assertStatus(200);
        $response->assertViewHas('products', function ($products) use ($product) {
            return $products->contains('id', $product->id);
        });
    }

    public function test_slow_movers_export_returns_csv(): void
    {
        $this->actingAs($this->gerente());

        $response = $this->get('/reports/slow-movers/csv');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    // ─── Rendimiento por dia de la semana ───────────────────

    public function test_weekly_performance_report_loads(): void
    {
        $this->actingAs($this->gerente());

        $response = $this->get('/reports/weekly-performance');

        $response->assertStatus(200);
        $response->assertViewIs('reports.weekly-performance');
    }

    public function test_weekly_performance_groups_by_day(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
            'total' => 40.00,
            'created_at' => now()->modify('next monday'),
        ]);

        $response = $this->get('/reports/weekly-performance');

        $response->assertStatus(200);
        $response->assertViewHas('byDay');
    }

    public function test_weekly_performance_export_returns_csv(): void
    {
        $this->actingAs($this->gerente());

        $response = $this->get('/reports/weekly-performance/csv');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    // ─── Produccion vs Ventas vs Mermas ─────────────────────

    public function test_production_vs_sales_report_loads(): void
    {
        $this->actingAs($this->gerente());

        $response = $this->get('/reports/production-vs-sales');

        $response->assertStatus(200);
        $response->assertViewIs('reports.production-vs-sales');
    }

    public function test_production_vs_sales_shows_comparison(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        $product = Product::factory()->produccion()->create();

        Production::factory()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'quantity' => 20,
        ]);

        $sale = Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
        ]);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 15,
        ]);

        Merma::factory()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'quantity' => 2,
        ]);

        $response = $this->get('/reports/production-vs-sales');

        $response->assertStatus(200);
        $response->assertViewHas('comparison', function ($c) use ($product) {
            $item = $c->firstWhere('id', $product->id);
            return $item
                && $item->produced == 20
                && $item->sold == 15
                && $item->wasted == 2
                && $item->efficiency == 75.0;
        });
    }

    public function test_production_vs_sales_export_returns_csv(): void
    {
        $this->actingAs($this->gerente());

        $response = $this->get('/reports/production-vs-sales/csv');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    // ─── Combos incluidos ──────────────────────────────────

    public function test_products_report_includes_combos(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        $combo = \App\Models\Combo::factory()->create(['name' => 'Combo Familiar', 'sale_price' => 12.00]);
        $sale = Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
        ]);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'combo_id' => $combo->id,
            'product_id' => null,
            'product_name' => $combo->name,
            'quantity' => 2,
            'subtotal' => 2400.00,
        ]);

        $response = $this->get('/reports/products');

        $response->assertStatus(200);
        $response->assertViewHas('products', function ($products) use ($combo) {
            $row = $products->first(fn ($p) => $p->name === 'Combo Familiar');
            return $row && $row->control_type === 'combo' && $row->total_sold == 2;
        });
    }

    public function test_profit_margin_includes_combos(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        $combo = \App\Models\Combo::factory()->create(['name' => 'Combo Lunch', 'sale_price' => 8.00]);
        $sale = Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
        ]);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'combo_id' => $combo->id,
            'product_id' => null,
            'product_name' => $combo->name,
            'quantity' => 1,
            'subtotal' => 1600.00,
        ]);

        $response = $this->get('/reports/profit-margin');

        $response->assertStatus(200);
        $response->assertViewHas('products', function ($products) use ($combo) {
            $row = $products->first(fn ($p) => $p->name === 'Combo Lunch');
            // rate=1 (sin tasa) -> cost = 8 * 1 = 8, revenue 1600 -> profit 1592, margen alto
            return $row && $row->total_sold == 1 && $row->profit == 1592.0;
        });
    }

    public function test_production_vs_sales_includes_combos(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        $combo = \App\Models\Combo::factory()->create(['name' => 'Combo Noche']);
        $sale = Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
        ]);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'combo_id' => $combo->id,
            'product_id' => null,
            'quantity' => 3,
        ]);

        $response = $this->get('/reports/production-vs-sales');

        $response->assertStatus(200);
        $response->assertViewHas('comparison', function ($comparison) use ($combo) {
            $row = $comparison->firstWhere('name', $combo->name);
            return $row && $row->sold == 3 && $row->produced == 0 && $row->efficiency == 0.0;
        });
    }

    // ─── Costos convertidos a Bs ───────────────────────────

    public function test_products_report_converts_cost_to_bs_at_current_rate(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        \App\Models\ExchangeRate::factory()->create(['rate' => 100]);

        $product = Product::factory()->create([
            'cost_price' => 5.00,
            'sale_price' => 10.00,
            'stock_current' => 5,
        ]);

        $sale = Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
        ]);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'subtotal' => 2000.00,
        ]);

        $response = $this->get('/reports/products');

        $response->assertStatus(200);
        $response->assertViewHas('products', function ($products) use ($product) {
            $row = $products->first(fn ($p) => $p->name === $product->name);
            // revenue 2000 Bs, costo USD 10 -> 1000 Bs -> ganancia 1000
            return $row && $row->cost == 1000.00 && $row->profit == 1000.00;
        });
    }

    // ─── Mermas export ──────────────────────────────────────

    public function test_waste_export_includes_reason(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        $product = Product::factory()->create();
        Merma::factory()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'quantity' => 3,
            'reason' => 'vencido',
        ]);

        $response = $this->get('/reports/waste/csv');

        $response->assertStatus(200);
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Vencido', $csv);
    }

    // ─── Creditos por periodo ───────────────────────────────

    public function test_credits_report_shows_period_net_in_usd_and_bs(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        \App\Models\ExchangeRate::factory()->create(['rate' => 100]);
        $customer = \App\Models\Customer::factory()->create(['name' => 'Ana Perez']);

        \App\Models\CreditMovement::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'type' => 'cargo',
            'amount' => 60.00,
            'created_at' => now(),
        ]);
        \App\Models\CreditMovement::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'type' => 'pago',
            'amount' => 10.00,
            'created_at' => now(),
        ]);

        $response = $this->get('/reports/credits');

        $response->assertStatus(200);
        $response->assertViewHas('customers', function ($customers) use ($customer) {
            $row = $customers->first(fn ($c) => $c->id === $customer->id);
            return $row
                && $row->period_cargos == 60.00
                && $row->period_pagos == 10.00
                && $row->period_net_usd == 50.00
                && $row->period_net_bs == 5000.00;
        });
        $response->assertViewHas('totalDebtUsd', fn ($t) => $t == 50.00);
        $response->assertViewHas('totalDebtBs', fn ($t) => $t == 5000.00);
        // La etiqueta ya no dice "Bs" sobre una cifra USD
        $response->assertDontSee('Saldo (Bs)', false);
    }

    // ─── Lento movimiento respeta rango ─────────────────────

    public function test_slow_movers_respects_selected_range(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        $soldInRange = Product::factory()->create(['is_active' => true, 'stock_current' => 5]);
        $soldOutside = Product::factory()->create(['is_active' => true, 'stock_current' => 5]);

        $sale = Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
            'created_at' => now(),
        ]);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $soldInRange->id,
            'quantity' => 1,
        ]);

        $oldSale = Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
            'created_at' => now()->subMonths(2),
        ]);
        $oldSale->items()->create([
            'product_id' => $soldOutside->id,
            'product_name' => $soldOutside->name,
            'quantity' => 1,
            'unit_price' => 5.00,
            'subtotal' => 5.00,
        ]);

        $from = now()->subWeek()->format('Y-m-d');
        $to = now()->format('Y-m-d');

        $response = $this->get("/reports/slow-movers?from={$from}&to={$to}");

        $response->assertStatus(200);
        $response->assertViewHas('products', function ($products) use ($soldInRange, $soldOutside) {
            $ids = $products->pluck('id');
            return $ids->contains($soldOutside->id) && ! $ids->contains($soldInRange->id);
        });
    }

    // ─── Horario ────────────────────────────────────────────

    public function test_sales_by_schedule_sums_revenue_by_period(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
            'total' => 50.00,
            'created_at' => now()->setTime(10, 0),
        ]);
        Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
            'total' => 80.00,
            'created_at' => now()->setTime(20, 0),
        ]);

        $response = $this->get('/reports/sales-by-schedule');

        $response->assertStatus(200);
        $response->assertViewHas('manana', fn ($m) => abs((float) ($m->revenue ?? 0) - 50.0) < 0.01);
        $response->assertViewHas('noche', fn ($n) => abs((float) ($n->revenue ?? 0) - 80.0) < 0.01);
    }

    // ─── Rendimiento semanal ────────────────────────────────

    public function test_weekly_performance_maps_days_from_monday(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        $monday = now()->modify('next monday')->startOfDay();

        Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'completada',
            'total' => 40.00,
            'created_at' => $monday,
        ]);

        $from = $monday->format('Y-m-d');
        $to = $monday->format('Y-m-d');

        $response = $this->get("/reports/weekly-performance?from={$from}&to={$to}");

        $response->assertStatus(200);
        $response->assertViewHas('byDay', function ($byDay) {
            $lunes = $byDay->firstWhere('day_name', 'Lunes');
            return $lunes && (int) $lunes->dow === 0 && $lunes->revenue == 40.00;
        });
    }

    // ─── Index ──────────────────────────────────────────────

    public function test_index_fixes_credit_kpi_currency(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        \App\Models\ExchangeRate::factory()->create(['rate' => 100]);
        \App\Models\Customer::factory()->create(['balance' => -25.00]);

        $response = $this->get('/reports');

        $response->assertStatus(200);
        $response->assertViewHas('pendingCreditUsd', fn ($v) => (abs((float) $v) - 25.0) < 0.01);
        $response->assertViewHas('pendingCreditBs', fn ($v) => (abs((float) $v) - 2500.0) < 0.01);
    }

    // ─── Access control ─────────────────────────────────────

    public function test_recepcionista_cannot_access_reports(): void
    {
        $user = User::factory()->recepcionista()->create();
        $this->actingAs($user);

        $response = $this->get('/reports');

        $response->assertStatus(403);
    }
}
