<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_loads_without_errors(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->get('/reports');

        $response->assertStatus(200);
        $response->assertViewIs('reports.index');
        $response->assertSee('Ventas');
        $response->assertSee('Productos');
    }

    public function test_sales_report_loads(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->get('/reports/sales');

        $response->assertStatus(200);
        $response->assertViewIs('reports.sales');
    }

    public function test_sales_report_shows_data(): void
    {
        $user = User::factory()->gerente()->create();
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
        $user = User::factory()->gerente()->create();
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
        $response->assertViewHas('byMethod', function ($m) {
            return abs((float) ($m['credito'] ?? 0) - 320.0) < 0.01
                && abs((float) ($m['efectivo'] ?? 0) - 180.0) < 0.01;
        });
        $response->assertSee('Crédito');
        $response->assertSee('320,00');
    }

    public function test_sales_report_filters_by_date_range(): void
    {
        $user = User::factory()->gerente()->create();
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
        SalePayment::factory()->create(['sale_id' => 1]);

        $from = now()->subDays(7)->format('Y-m-d');
        $to = now()->format('Y-m-d');

        $response = $this->get("/reports/sales?from={$from}&to={$to}");

        $response->assertStatus(200);
    }

    public function test_products_report_loads(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->get('/reports/products');

        $response->assertStatus(200);
        $response->assertViewIs('reports.products');
    }

    public function test_credits_report_loads(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->get('/reports/credits');

        $response->assertStatus(200);
        $response->assertViewIs('reports.credits');
    }
}
