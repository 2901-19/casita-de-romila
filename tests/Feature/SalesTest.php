<?php

namespace Tests\Feature;

use App\Models\Combo;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_loads_without_errors(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/sales');

        $response->assertStatus(200);
        $response->assertViewIs('sales.index');
    }

    public function test_shows_empty_state(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/sales');

        $response->assertStatus(200);
        $response->assertSee('No hay ventas registradas');
    }

    public function test_shows_sales_list(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $sale = Sale::factory()->create(['user_id' => $user->id, 'status' => 'completada', 'total' => 10.00]);
        SaleItem::factory()->create(['sale_id' => $sale->id, 'product_name' => 'Refresco']);
        SalePayment::factory()->create(['sale_id' => $sale->id, 'method' => 'efectivo', 'amount' => 10.00]);

        $response = $this->get('/sales');

        $response->assertStatus(200);
        $response->assertSee('#' . $sale->id);
        $response->assertSee('1 producto');
        $response->assertSee('Bs 10,00');
    }

    public function test_show_loads_sale_detail(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $sale = Sale::factory()->create(['user_id' => $user->id, 'status' => 'completada']);
        SaleItem::factory()->create(['sale_id' => $sale->id, 'product_name' => 'Torta', 'quantity' => 2, 'unit_price' => 4.00, 'subtotal' => 8.00]);
        SalePayment::factory()->create(['sale_id' => $sale->id, 'method' => 'efectivo', 'amount' => 8.00]);

        $response = $this->get("/sales/{$sale->id}");

        $response->assertStatus(200);
        $response->assertViewIs('sales.show');
        $response->assertSee('Torta');
        $response->assertSee('2');
        $response->assertSee('8,00');
    }

    public function test_filter_by_status_completada(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $sale1 = Sale::factory()->create(['user_id' => $user->id, 'status' => 'completada']);
        $sale2 = Sale::factory()->create(['user_id' => $user->id, 'status' => 'anulada', 'cancel_reason' => 'Test']);
        SalePayment::factory()->create(['sale_id' => $sale1->id]);

        $response = $this->get('/sales?status=completada');

        $response->assertStatus(200);
        $response->assertSee('#' . $sale1->id);
        $response->assertDontSee('#' . $sale2->id);
    }

    public function test_filter_by_status_anulada(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $sale1 = Sale::factory()->create(['user_id' => $user->id, 'status' => 'completada']);
        $sale2 = Sale::factory()->create(['user_id' => $user->id, 'status' => 'anulada', 'cancel_reason' => 'Test']);
        SalePayment::factory()->create(['sale_id' => $sale2->id]);

        $response = $this->get('/sales?status=anulada');

        $response->assertStatus(200);
        $response->assertSee('Anulada');
    }

    public function test_destroy_anula_sale_and_restores_stock(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create([
            'control_type' => 'inventariable',
            'stock_current' => 20,
        ]);
        $sale = Sale::factory()->create(['user_id' => $user->id, 'status' => 'completada']);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 5,
            'unit_price' => 10.00,
            'subtotal' => 50.00,
        ]);
        SalePayment::factory()->create(['sale_id' => $sale->id, 'method' => 'efectivo', 'amount' => 50.00]);

        $response = $this->delete("/sales/{$sale->id}", [
            'cancel_reason' => 'Error en venta',
        ]);

        $response->assertRedirect('/sales');
        $response->assertSessionHas('success');

        $sale->refresh();
        $product->refresh();
        $this->assertEquals('anulada', $sale->status);
        $this->assertEquals('Error en venta', $sale->cancel_reason);
        $this->assertEquals(25, $product->stock_current);
    }

    public function test_cannot_anulate_already_anulada(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $sale = Sale::factory()->create([
            'user_id' => $user->id,
            'status' => 'anulada',
            'cancel_reason' => 'Primera anulación',
        ]);

        $response = $this->delete("/sales/{$sale->id}", [
            'cancel_reason' => 'Segunda anulación',
        ]);

        $response->assertRedirect('/sales');
        $response->assertSessionHas('error', 'Esta venta ya está anulada.');
    }

    public function test_destroy_restores_stock_for_produccion_product(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create([
            'control_type' => 'produccion',
            'stock_current' => 20,
        ]);
        $sale = Sale::factory()->create(['user_id' => $user->id, 'status' => 'completada']);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 5,
            'unit_price' => 10.00,
            'subtotal' => 50.00,
        ]);
        SalePayment::factory()->create(['sale_id' => $sale->id, 'method' => 'efectivo', 'amount' => 50.00]);

        $this->delete("/sales/{$sale->id}", ['cancel_reason' => 'Error']);

        $product->refresh();
        $this->assertEquals(25, $product->stock_current);
    }

    public function test_destroy_does_not_restore_stock_for_demanda_product(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create([
            'control_type' => 'demanda',
            'stock_current' => 100,
        ]);
        $sale = Sale::factory()->create(['user_id' => $user->id, 'status' => 'completada']);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 5,
            'unit_price' => 10.00,
            'subtotal' => 50.00,
        ]);
        SalePayment::factory()->create(['sale_id' => $sale->id, 'method' => 'efectivo', 'amount' => 50.00]);

        $this->delete("/sales/{$sale->id}", ['cancel_reason' => 'Error']);

        $product->refresh();
        $this->assertEquals(100, $product->stock_current);
    }

    public function test_destroy_restores_combo_component_stock(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $component = Product::factory()->create([
            'control_type' => 'inventariable',
            'stock_current' => 50,
        ]);
        $combo = Combo::factory()->create(['sale_price' => 10.00]);
        $combo->products()->attach($component->id, ['quantity' => 2]);

        $sale = Sale::factory()->create(['user_id' => $user->id, 'status' => 'completada']);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => null,
            'combo_id' => $combo->id,
            'product_name' => $combo->name,
            'quantity' => 3,
            'unit_price' => 10.00,
            'subtotal' => 30.00,
        ]);
        SalePayment::factory()->create(['sale_id' => $sale->id, 'method' => 'efectivo', 'amount' => 30.00]);

        $this->delete("/sales/{$sale->id}", ['cancel_reason' => 'Error']);

        $component->refresh();
        // Was 50, combo sold 3 units x 2 components each = 6 deducted, now restored
        $this->assertEquals(56, $component->stock_current);
    }

    public function test_destroy_does_not_restore_demanda_combo_component(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $component = Product::factory()->create([
            'control_type' => 'demanda',
            'stock_current' => 100,
        ]);
        $combo = Combo::factory()->create(['sale_price' => 10.00]);
        $combo->products()->attach($component->id, ['quantity' => 2]);

        $sale = Sale::factory()->create(['user_id' => $user->id, 'status' => 'completada']);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => null,
            'combo_id' => $combo->id,
            'product_name' => $combo->name,
            'quantity' => 3,
            'unit_price' => 10.00,
            'subtotal' => 30.00,
        ]);
        SalePayment::factory()->create(['sale_id' => $sale->id, 'method' => 'efectivo', 'amount' => 30.00]);

        $this->delete("/sales/{$sale->id}", ['cancel_reason' => 'Error']);

        $component->refresh();
        $this->assertEquals(100, $component->stock_current);
    }
}
