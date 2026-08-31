<?php

namespace Tests\Feature;

use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_loads_without_errors(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->get('/inventory');

        $response->assertStatus(200);
        $response->assertViewIs('inventory.index');
    }

    public function test_shows_empty_state(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->get('/inventory');

        $response->assertStatus(200);
        $response->assertSee('No hay movimientos registrados');
    }

    public function test_stores_entrada_adjustment(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['stock_current' => 10]);

        $response = $this->post('/inventory', [
            'product_id' => $product->id,
            'type' => 'entrada',
            'quantity' => 5,
            'reason' => 'compra',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('inventory_adjustments', [
            'product_id' => $product->id,
            'type' => 'entrada',
            'quantity' => 5,
            'reason' => 'compra',
            'user_id' => $user->id,
        ]);
        $product->refresh();
        $this->assertEquals(15, $product->stock_current);
    }

    public function test_stores_salida_adjustment(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['stock_current' => 20]);

        $response = $this->post('/inventory', [
            'product_id' => $product->id,
            'type' => 'salida',
            'quantity' => 8,
            'reason' => 'ajuste',
        ]);

        $response->assertSessionHas('success');
        $product->refresh();
        $this->assertEquals(12, $product->stock_current);
    }

    public function test_validates_product_required(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->post('/inventory', [
            'product_id' => '',
            'type' => 'entrada',
            'quantity' => 5,
            'reason' => 'compra',
        ]);

        $response->assertSessionHasErrors('product_id');
    }

    public function test_validates_quantity_min(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create();

        $response = $this->post('/inventory', [
            'product_id' => $product->id,
            'type' => 'entrada',
            'quantity' => 0,
            'reason' => 'compra',
        ]);

        $response->assertSessionHasErrors('quantity');
    }

    public function test_filters_by_product(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();
        InventoryAdjustment::factory()->create(['product_id' => $product1->id]);
        InventoryAdjustment::factory()->create(['product_id' => $product2->id]);

        $response = $this->get("/inventory?product_id={$product1->id}");

        $response->assertStatus(200);
        $response->assertSee($product1->name);
    }

    public function test_filters_by_type(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        InventoryAdjustment::factory()->create(['type' => 'entrada']);
        InventoryAdjustment::factory()->create(['type' => 'salida']);

        $response = $this->get('/inventory?type=entrada');

        $response->assertStatus(200);
        $response->assertSee('Entrada');
    }

    public function test_rejects_module_reasons_in_manual_adjustment(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create();

        foreach (['merma', 'venta', 'produccion'] as $reason) {
            $response = $this->post('/inventory', [
                'product_id' => $product->id,
                'type' => 'salida',
                'quantity' => 1,
                'reason' => $reason,
            ]);

            $response->assertSessionHasErrors('reason');
        }

        $this->assertDatabaseCount('inventory_adjustments', 0);
    }

    public function test_rejects_non_inventariable_product(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $produccion = Product::factory()->produccion()->create(['stock_current' => 0]);
        $demanda = Product::factory()->demanda()->create(['stock_current' => 0]);

        foreach ([$produccion, $demanda] as $product) {
            $response = $this->post('/inventory', [
                'product_id' => $product->id,
                'type' => 'entrada',
                'quantity' => 5,
                'reason' => 'compra',
            ]);

            $response->assertSessionHasErrors('product_id');
        }

        $this->assertDatabaseCount('inventory_adjustments', 0);
        $this->assertEquals(0, $produccion->refresh()->stock_current);
    }

    public function test_rejects_salida_exceeding_stock(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['stock_current' => 5]);

        $response = $this->post('/inventory', [
            'product_id' => $product->id,
            'type' => 'salida',
            'quantity' => 10,
            'reason' => 'ajuste',
        ]);

        $response->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('inventory_adjustments', 0);
        $product->refresh();
        $this->assertEquals(5, $product->stock_current);
    }

    public function test_store_rejects_nonexistent_product(): void
    {
        $user = User::factory()->gerente()->create();
        $this->actingAs($user);

        $response = $this->post('/inventory', [
            'product_id' => 99999,
            'type' => 'salida',
            'quantity' => 1,
            'reason' => 'ajuste',
        ]);

        $response->assertSessionHasErrors('product_id');
        $this->assertDatabaseCount('inventory_adjustments', 0);
    }
}
