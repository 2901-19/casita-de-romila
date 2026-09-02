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

    public function test_report_date_filter_presets_preserve_days_in_slow_movers(): void
    {
        $user = $this->gerente();
        $this->actingAs($user);

        $response = $this->get('/reports/slow-movers?days=60');

        $response->assertStatus(200);
        $response->assertSee('name="days"', false);
        $response->assertSee('value="60"', false);
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
            return $p && $p->profit == 70.00 && $p->sold == 10;
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

    // ─── Access control ─────────────────────────────────────

    public function test_recepcionista_cannot_access_reports(): void
    {
        $user = User::factory()->recepcionista()->create();
        $this->actingAs($user);

        $response = $this->get('/reports');

        $response->assertStatus(403);
    }
}
